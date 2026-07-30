<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Superscript\FilamentSpotlight\Livewire\Spotlight;

class SpotlightServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-spotlight';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        // Assets are registered here rather than in the plugin so they are
        // registered exactly once, no matter how many panels use the plugin.
        FilamentAsset::register([
            Js::make('spotlight', __DIR__.'/../dist/spotlight.js'),
            Css::make('spotlight', __DIR__.'/../dist/spotlight.css'),
        ], package: 'gosuperscript/filament-spotlight');

        Livewire::component('filament-spotlight', Spotlight::class);
    }
}
