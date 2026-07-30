# Filament Spotlight

A ⌘K command menu for [Filament 5](https://filamentphp.com) panels — search and quick actions, built on [cmdk](https://github.com/pacocoursey/cmdk) with a fluent, hookable PHP API.

- **Command menu** opened with <kbd>⌘K</kbd> / <kbd>Ctrl+K</kbd>, with fuzzy matching, keyboard navigation, and grouped results.
- **Navigation index**: every page and resource in your panel is instantly searchable, respecting visibility and authorization.
- **Global search**: records from your resources' [global search](https://filamentphp.com/docs/panels/resources/global-search) appear while you type.
- **Custom commands**: register actions with a fluent API — navigate to URLs, dispatch Livewire events, or run server-side closures.
- **Hookable**: pages, resources, plugins, and packages can all contribute commands.

The UI ships as a self-contained React island (react + cmdk bundled, ~64 KB gzipped, loaded once and cached). Your app needs **no Node build step** and no theme changes. All command definitions, search, and authorization stay server-side — the browser only ever sees display payloads and command IDs.

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- Filament ^5.0

## Installation

```bash
composer require superscript/filament-spotlight
php artisan filament:assets
```

Register the plugin in your panel provider:

```php
use Superscript\FilamentSpotlight\SpotlightPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SpotlightPlugin::make());
}
```

That's it — press <kbd>⌘K</kbd> in your panel. Navigation and global search work out of the box.

## Registering commands

```php
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\SpotlightPlugin;

SpotlightPlugin::make()
    ->groups([
        CommandGroup::make('maintenance')->label('Maintenance')->sort(50),
    ])
    ->commands([
        // Navigate to a URL
        Command::make('open-horizon')
            ->label('Open Horizon')
            ->icon(Heroicon::OutlinedQueueList)
            ->url(fn (): string => route('horizon.index'), shouldOpenInNewTab: true),

        // Run a closure on the server
        Command::make('clear-cache')
            ->label('Clear cache')
            ->keywords(['flush', 'artisan'])
            ->group('maintenance')
            ->visible(fn (User $user): bool => $user->isAdmin())
            ->action(function () {
                Artisan::call('cache:clear');

                Notification::make()->title('Cache cleared')->success()->send();
            }),

        // Dispatch a Livewire event in the browser (no server roundtrip)
        Command::make('toggle-sidebar')
            ->label('Toggle sidebar')
            ->dispatch('sidebar-toggle'),
    ]);
```

A command defines exactly one behaviour: `action()` (server-side closure), `url()`, or `dispatch()`. An `action()` closure that returns a string redirects the browser to it.

### Closures everywhere

Almost every method accepts a closure, evaluated lazily on the server with Filament-style named and typed parameter injection — `$command`, `$panel`, `$user`, and `$context` are available:

```php
->commands(fn (User $user, Panel $panel): array => [
    Command::make('my-account')
        ->label("Signed in as {$user->name}")
        ->url(fn (): string => ProfilePage::getUrl()),
])
```

`commands()` can be called multiple times; other plugins and packages can contribute too:

```php
SpotlightPlugin::get()->commands([...]);
```

### Visibility & authorization

```php
Command::make('danger-zone')
    ->visible(fn (User $user): bool => $user->isAdmin())  // or ->hidden(...)
    ->authorize('manage-settings')                        // Gate ability
    ->action(...);
```

Both checks run when the index is built **and again when a command is executed** — hiding a command client-side is presentation only; the server is the enforcement point (403).

## Commands from pages & resources

Any panel page or resource can contribute commands by implementing `HasSpotlightCommands`:

```php
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\HasSpotlightCommands;

class Settings extends Page implements HasSpotlightCommands
{
    public static function getSpotlightCommands(): array
    {
        return [
            Command::make('settings:reset')
                ->label('Reset settings')
                ->action(fn () => static::reset()),
        ];
    }
}
```

## Dynamic commands (search providers)

For results that depend on what the user types (recent documents, external APIs, …), implement `CommandProvider`. Providers are queried server-side on every debounced keystroke:

```php
use Superscript\FilamentSpotlight\Contracts\CommandProvider;
use Superscript\FilamentSpotlight\SearchContext;

class DocumentProvider implements CommandProvider
{
    public function search(SearchContext $context): array
    {
        return Document::search($context->query)
            ->take(5)
            ->get()
            ->map(fn (Document $document) => Command::make("documents:{$document->id}")
                ->label($document->title)
                ->group('documents')
                ->url($document->url))
            ->all();
    }
}

SpotlightPlugin::make()->providers([DocumentProvider::class]);
```

> [!IMPORTANT]
> Provider command names must be **deterministic** (e.g. `documents:{id}`): when a provider command is executed, the provider is re-run with the same query to re-materialize the command by its name, and visibility/authorization are re-checked before anything runs.

## Configuration

```php
SpotlightPlugin::make()
    ->keybindings(['mod+k', 'mod+/']) // 'mod' = ⌘ on macOS, Ctrl elsewhere
    ->placeholder('What do you need?')
    ->navigation(false)               // drop pages/resources from the index
    ->globalSearch(false);            // drop record results
```

## Opening the menu programmatically

From PHP (any Livewire component):

```php
$this->dispatch('filament-spotlight:open'); // also :close, :toggle
```

From JavaScript:

```js
window.FilamentSpotlight.open() // .close(), .toggle()
```

## Theming

The menu is styled with Filament's CSS custom properties (`--gray-*`, `--primary-*`), so it follows your panel's palette and dark mode automatically — nothing to configure. To restyle it, target the `[cmdk-*]` attributes under `.fi-spotlight` in your own CSS.

## Development

```bash
composer install && npm install
npm run build          # bundle resources/js + css into dist/
composer test          # Pest
composer analyse       # PHPStan
composer format        # Pint
```

`dist/` is committed so installing apps never need Node.

## License

MIT
