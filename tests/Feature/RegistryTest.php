<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Superscript\FilamentSpotlight\CommandRegistry;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Exceptions\DuplicateCommandException;
use Superscript\FilamentSpotlight\SpotlightPlugin;

it('throws on duplicate command names', function () {
    (new CommandRegistry)->add(
        Command::make('twice')->url('/a'),
        Command::make('twice')->url('/b'),
    );
})->throws(DuplicateCommandException::class, '[twice]');

it('finds commands by id', function () {
    $registry = (new CommandRegistry)->add($command = Command::make('findable')->url('/x'));

    expect($registry->find('findable'))->toBe($command)
        ->and($registry->find('missing'))->toBeNull();
});

it('filters hidden commands from the visible set', function () {
    $registry = (new CommandRegistry)->add(
        Command::make('shown')->url('/a'),
        Command::make('concealed')->url('/b')->hidden(),
    );

    expect(collect($registry->visible())->map->getName()->all())->toBe(['shown']);
});

it('builds plugin commands lazily with panel and user injection', function () {
    $this->actingAs(makeUser(['name' => 'Erik']));

    SpotlightPlugin::get()->commands(fn (Panel $panel): array => [
        Command::make('panel-echo')->label($panel->getId())->url('/x'),
    ]);

    $registry = SpotlightPlugin::get()->buildStaticRegistry(Filament::getPanel('admin'));

    expect($registry->find('panel-echo')?->getLabel())->toBe('admin');
});

it('collects commands from pages implementing HasSpotlightCommands', function () {
    $registry = SpotlightPlugin::get()->buildStaticRegistry(Filament::getPanel('admin'));

    expect($registry->find('settings:clear-cache'))->not->toBeNull();
});

it('includes navigation commands by default', function () {
    $this->actingAs(makeUser());

    $registry = SpotlightPlugin::get()->buildStaticRegistry(Filament::getPanel('admin'));

    $navigationCommands = collect($registry->all())
        ->filter(fn (Command $command): bool => str_starts_with($command->getName(), 'navigation:'));

    expect($navigationCommands)->not->toBeEmpty()
        ->and($navigationCommands->map->getLabel()->values()->all())->toContain('Dashboard');
});

it('excludes navigation commands when disabled', function () {
    $this->actingAs(makeUser());

    SpotlightPlugin::get()->navigation(false);

    $registry = SpotlightPlugin::get()->buildStaticRegistry(Filament::getPanel('admin'));

    $navigationCommands = collect($registry->all())
        ->filter(fn (Command $command): bool => str_starts_with($command->getName(), 'navigation:'));

    expect($navigationCommands)->toBeEmpty();
});
