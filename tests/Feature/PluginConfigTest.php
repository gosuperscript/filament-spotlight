<?php

declare(strict_types=1);

use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\Providers\GlobalSearchCommandProvider;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Superscript\FilamentSpotlight\Tests\Fixtures\RecentDocumentsProvider;

it('has sensible defaults', function () {
    $plugin = SpotlightPlugin::get();

    expect($plugin->getKeybindings())->toBe(['mod+k'])
        ->and($plugin->hasNavigation())->toBeTrue()
        ->and($plugin->hasGlobalSearch())->toBeTrue()
        ->and($plugin->getPlaceholder())->toBe('Search or run a command…')
        ->and($plugin->getGroups())->toBe([]);
});

it('can be configured fluently', function () {
    $plugin = SpotlightPlugin::get()
        ->keybindings(['mod+p', 'ctrl+space'])
        ->placeholder(fn (): string => 'Type away')
        ->navigation(false)
        ->globalSearch(false);

    expect($plugin->getKeybindings())->toBe(['mod+p', 'ctrl+space'])
        ->and($plugin->getPlaceholder())->toBe('Type away')
        ->and($plugin->hasNavigation())->toBeFalse()
        ->and($plugin->hasGlobalSearch())->toBeFalse();
});

it('registers groups by name', function () {
    $plugin = SpotlightPlugin::get()->groups([
        CommandGroup::make('maintenance')->label('Maintenance')->sort(50),
    ]);

    expect($plugin->getGroups())->toHaveKey('maintenance')
        ->and($plugin->getGroups()['maintenance']->getLabel())->toBe('Maintenance')
        ->and($plugin->getGroups()['maintenance']->getSort())->toBe(50);
});

it('resolves providers and appends the global search provider when enabled', function () {
    $plugin = SpotlightPlugin::get()->providers([RecentDocumentsProvider::class]);

    $providers = $plugin->getCommandProviders();

    expect($providers)->toHaveCount(2)
        ->and($providers[0])->toBeInstanceOf(RecentDocumentsProvider::class)
        ->and($providers[1])->toBeInstanceOf(GlobalSearchCommandProvider::class);

    $plugin->globalSearch(false);

    expect($plugin->getCommandProviders())->toHaveCount(1);
});
