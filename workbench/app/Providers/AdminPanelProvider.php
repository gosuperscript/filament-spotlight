<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Workbench\App\Filament\Pages\Settings;
use Workbench\App\Filament\Resources\UserResource;
use Workbench\App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        config()->set('auth.providers.users.model', User::class);

        parent::register();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Acme Inc.')
            // Unlike the test fixtures (where actingAs() sets the guard
            // directly), the served demo needs real cookie sessions, so the
            // full middleware stack from a stock panel provider is required.
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->colors(['primary' => Color::Indigo])
            ->pages([
                Dashboard::class,
                Settings::class,
            ])
            ->resources([
                UserResource::class,
            ])
            ->plugin(
                SpotlightPlugin::make()
                    ->groups([
                        CommandGroup::make('maintenance')->label('Maintenance')->sort(50),
                    ])
                    ->commands([
                        Command::make('invite-teammate')
                            ->label('Invite a teammate')
                            ->icon(Heroicon::OutlinedUserPlus)
                            ->url('#'),
                        Command::make('documentation')
                            ->label('Documentation')
                            ->icon(Heroicon::OutlinedBookOpen)
                            ->url('https://github.com/gosuperscript/filament-spotlight', shouldOpenInNewTab: true),
                        Command::make('clear-cache')
                            ->label('Clear cache')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->keywords(['flush', 'artisan'])
                            ->group('maintenance')
                            ->action(function (): void {
                                Notification::make()->title('Cache cleared')->success()->send();
                            }),
                        Command::make('horizon')
                            ->label('Open Horizon')
                            ->icon(Heroicon::OutlinedQueueList)
                            ->group('maintenance')
                            ->url('#', shouldOpenInNewTab: true),
                    ]),
            );
    }
}
