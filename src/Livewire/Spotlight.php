<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Livewire;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\PageContext;
use Superscript\FilamentSpotlight\SearchContext;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Superscript\FilamentSpotlight\Support\CommandPayload;
use Superscript\FilamentSpotlight\Support\PageContextResolver;

/**
 * Deliberately stateless: no command definitions or results are kept in
 * Livewire state. Every request rebuilds the registry from panel code, so the
 * client only ever holds command IDs and display payloads — and there is
 * nothing to tamper with or go stale.
 */
class Spotlight extends Component
{
    #[Locked]
    public string $panelId;

    public function mount(): void
    {
        $this->panelId = Filament::getCurrentPanel()->getId();
    }

    /**
     * The full static command index for the current user, fetched when the
     * menu first opens on a page and filtered client-side. The URL is where
     * the client says it is — used to offer page/record commands, while
     * execution always re-checks visibility and authorization. The context
     * chip is only sent when it actually scopes something.
     *
     * @return array{context: array{badge: string | null, label: string} | null, commands: array<array<string, mixed>>}
     */
    #[Renderless]
    public function getStaticCommands(?string $url = null): array
    {
        $panel = $this->getPanel();
        $plugin = $this->getPlugin();
        $pageContext = $this->resolvePageContext($url, $panel);

        $commands = array_map(CommandPayload::fromCommand(...), $plugin->buildStaticRegistry($panel, $pageContext)->visible());

        $hasContextualCommands = array_filter($commands, fn (array $command): bool => (bool) $command['contextual']) !== [];

        return [
            'context' => $pageContext !== null && $hasContextualCommands ? $plugin->getContextChip($pageContext) : null,
            'commands' => $commands,
        ];
    }

    /**
     * Query the dynamic providers (global search, custom CommandProviders).
     *
     * @return array<array<string, mixed>>
     */
    #[Renderless]
    public function search(string $query, ?string $url = null): array
    {
        $panel = $this->getPanel();

        $context = new SearchContext(trim($query), $panel, Filament::auth()->user(), $this->resolvePageContext($url, $panel));

        $payloads = [];

        foreach ($this->getPlugin()->getCommandProviders() as $provider) {
            foreach ($provider->search($context) as $command) {
                if ($command->isVisible() && $command->isAuthorized()) {
                    $payloads[] = CommandPayload::fromCommand($command);
                }
            }
        }

        return $payloads;
    }

    /**
     * Execute a command by ID. The registry is rebuilt and visibility and
     * authorization are re-checked here — hiding a command client-side is
     * presentation only, this is the enforcement point.
     *
     * @param  array<string, mixed>  $context  Untrusted client echo; 'query' re-materializes
     *                                         provider commands, 'url' re-materializes page/record commands.
     * @return array{redirect: string} | null
     */
    #[Renderless]
    public function execute(string $id, array $context = []): ?array
    {
        $panel = $this->getPanel();
        $plugin = $this->getPlugin();

        $url = $context['url'] ?? null;
        $pageContext = $this->resolvePageContext(is_string($url) ? $url : null, $panel);

        $command = $plugin->buildStaticRegistry($panel, $pageContext)->find($id)
            ?? $this->findProviderCommand($plugin, $panel, $id, (string) ($context['query'] ?? ''), $pageContext);

        abort_if($command === null, 404);
        abort_unless($command->isVisible() && $command->isAuthorized(), 403);

        if ($command->hasAction()) {
            $result = $command->callAction([
                'livewire' => $this,
            ]);

            return is_string($result) ? ['redirect' => $result] : null;
        }

        if ($command->hasUrl()) {
            return ['redirect' => (string) $command->getUrl()];
        }

        return null;
    }

    public function render(): View
    {
        return view('filament-spotlight::spotlight', [
            'config' => $this->getClientConfig(),
        ]);
    }

    protected function findProviderCommand(SpotlightPlugin $plugin, Panel $panel, string $id, string $query, ?PageContext $pageContext = null): ?Command
    {
        $context = new SearchContext($query, $panel, Filament::auth()->user(), $pageContext);

        foreach ($plugin->getCommandProviders() as $provider) {
            foreach ($provider->search($context) as $command) {
                if ($command->getName() === $id) {
                    return $command;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getClientConfig(): array
    {
        $panel = $this->getPanel();
        $plugin = $this->getPlugin();

        $groups = array_map(
            fn (CommandGroup $group): array => $group->toPayload(),
            array_values($plugin->getGroups()),
        );

        usort($groups, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return [
            'keybindings' => $plugin->getKeybindings(),
            'keybindingItems' => $this->getKeybindingItems($plugin, $panel, $this->resolvePageContext(request()->fullUrl(), $panel)),
            'placeholder' => $plugin->getPlaceholder(),
            'spaEnabled' => $panel->hasSpaMode(),
            'groups' => $groups,
            'i18n' => [
                'empty' => __('filament-spotlight::spotlight.empty'),
                'loading' => __('filament-spotlight::spotlight.loading'),
            ],
        ];
    }

    /**
     * Refresh the keybound command payloads for the page the client reports
     * being on. Full page loads get them with the initial config; SPA
     * navigation calls this so contextual shortcuts follow the page.
     *
     * @return array<array<string, mixed>>
     */
    #[Renderless]
    public function getKeybindingCommands(?string $url = null): array
    {
        $panel = $this->getPanel();

        return $this->getKeybindingItems($this->getPlugin(), $panel, $this->resolvePageContext($url, $panel));
    }

    /**
     * Full payloads for keybound commands, shipped with the page so their
     * shortcuts work before the menu has ever been opened. Filtering on the
     * keybinding first keeps visibility/authorization closures (which may hit
     * gates or the database) off the render path for everything else —
     * execute() re-checks them anyway.
     *
     * @return array<array<string, mixed>>
     */
    protected function getKeybindingItems(SpotlightPlugin $plugin, Panel $panel, ?PageContext $pageContext = null): array
    {
        $commands = array_filter(
            $plugin->buildContributedRegistry($panel, $pageContext)->all(),
            fn (Command $command): bool => $command->getKeybinding() !== null,
        );

        $commands = array_filter(
            $commands,
            fn (Command $command): bool => $command->isVisible() && $command->isAuthorized(),
        );

        return array_map(CommandPayload::fromCommand(...), array_values($commands));
    }

    protected function resolvePageContext(?string $url, Panel $panel): ?PageContext
    {
        return app(PageContextResolver::class)->resolve($url, $panel);
    }

    protected function getPanel(): Panel
    {
        /** @var Panel $panel */
        $panel = Filament::getPanel($this->panelId);

        // Providers like Filament's DefaultGlobalSearchProvider read the
        // ambient current panel, so make it explicit for this request.
        Filament::setCurrentPanel($panel);

        return $panel;
    }

    protected function getPlugin(): SpotlightPlugin
    {
        /** @var SpotlightPlugin $plugin */
        $plugin = $this->getPanel()->getPlugin(SpotlightPlugin::ID);

        return $plugin;
    }
}
