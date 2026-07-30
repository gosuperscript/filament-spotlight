<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\Contracts\CommandProvider;
use Superscript\FilamentSpotlight\Contracts\HasSpotlightCommands;
use Superscript\FilamentSpotlight\Providers\GlobalSearchCommandProvider;
use Superscript\FilamentSpotlight\Providers\NavigationCommandProvider;

class SpotlightPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'filament-spotlight';

    /**
     * @var array<string>
     */
    protected array $keybindings = ['mod+k'];

    protected string|Closure|null $placeholder = null;

    protected bool|Closure $hasNavigation = true;

    protected bool|Closure $hasGlobalSearch = true;

    /**
     * @var array<array<Commands\Command> | Closure>
     */
    protected array $commands = [];

    /**
     * @var array<string, CommandGroup>
     */
    protected array $groups = [];

    /**
     * @var array<class-string<CommandProvider> | CommandProvider>
     */
    protected array $providers = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Filament::auth()->check()
                ? Blade::render("@persist('filament-spotlight') @livewire('filament-spotlight') @endpersist")
                : '',
        );
    }

    public function boot(Panel $panel): void {}

    /**
     * Keyboard shortcuts that open the menu. Use 'mod' for Cmd on macOS and
     * Ctrl elsewhere, e.g. ['mod+k', 'mod+/'].
     *
     * @param  array<string>  $keybindings
     */
    public function keybindings(array $keybindings): static
    {
        $this->keybindings = $keybindings;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getKeybindings(): array
    {
        return $this->keybindings;
    }

    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): string
    {
        return $this->evaluate($this->placeholder) ?? __('filament-spotlight::spotlight.placeholder');
    }

    /**
     * Include the panel's navigation (pages and resources) as commands.
     */
    public function navigation(bool|Closure $condition = true): static
    {
        $this->hasNavigation = $condition;

        return $this;
    }

    public function hasNavigation(): bool
    {
        return (bool) $this->evaluate($this->hasNavigation);
    }

    /**
     * Include the panel's global search results (records) while searching.
     */
    public function globalSearch(bool|Closure $condition = true): static
    {
        $this->hasGlobalSearch = $condition;

        return $this;
    }

    public function hasGlobalSearch(): bool
    {
        return (bool) $this->evaluate($this->hasGlobalSearch);
    }

    /**
     * Register commands. May be called multiple times — including by other
     * plugins or packages via SpotlightPlugin::get()->commands([...]).
     * Closures are evaluated lazily per request with $panel and $user
     * available for injection.
     *
     * @param  array<Commands\Command> | Closure  $commands
     */
    public function commands(array|Closure $commands): static
    {
        $this->commands[] = $commands;

        return $this;
    }

    /**
     * @param  array<CommandGroup>  $groups
     */
    public function groups(array $groups): static
    {
        foreach ($groups as $group) {
            $this->groups[$group->getName()] = $group;
        }

        return $this;
    }

    /**
     * @return array<string, CommandGroup>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Register dynamic command providers, queried server-side while the user
     * types.
     *
     * @param  array<class-string<CommandProvider> | CommandProvider>  $providers
     */
    public function providers(array $providers): static
    {
        $this->providers = [...$this->providers, ...$providers];

        return $this;
    }

    /**
     * @return array<CommandProvider>
     */
    public function getCommandProviders(): array
    {
        $providers = array_map(
            fn (string|CommandProvider $provider): CommandProvider => is_string($provider) ? app($provider) : $provider,
            $this->providers,
        );

        if ($this->hasGlobalSearch()) {
            $providers[] = app(GlobalSearchCommandProvider::class);
        }

        return $providers;
    }

    /**
     * Build the static command index for a panel: plugin-registered commands,
     * pages and resources implementing HasSpotlightCommands, and the panel's
     * navigation. Rebuilt deterministically on every request, so a command ID
     * is all the client ever needs to hold.
     */
    public function buildStaticRegistry(Panel $panel): CommandRegistry
    {
        $registry = new CommandRegistry;

        $user = Filament::auth()->user();

        foreach ($this->commands as $commands) {
            $registry->add(...$this->evaluate($commands, [
                'panel' => $panel,
                'user' => $user,
            ], [
                Panel::class => $panel,
                ...($user ? [$user::class => $user] : []),
            ]));
        }

        foreach ([...$panel->getPages(), ...$panel->getResources()] as $component) {
            if (is_a($component, HasSpotlightCommands::class, true)) {
                $registry->add(...$component::getSpotlightCommands());
            }
        }

        if ($this->hasNavigation()) {
            $registry->add(...app(NavigationCommandProvider::class)->commands($panel));
        }

        return $registry;
    }
}
