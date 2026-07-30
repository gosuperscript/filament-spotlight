<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->pages([
                Dashboard::class,
                Pages\Settings::class,
            ])
            ->resources([
                UserResource::class,
            ])
            ->plugin(SpotlightPlugin::make());
    }
}
