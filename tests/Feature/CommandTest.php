<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Commands\CommandGroup;
use Superscript\FilamentSpotlight\Tests\Fixtures\User;

it('throws when no execution type is defined', function () {
    Command::make('bare')->getType();
})->throws(LogicException::class, 'must define one of');

it('resolves the execution type with action > url > dispatch precedence', function () {
    expect(Command::make('a')->action(fn () => null)->url('/x')->getType())->toBe(Command::TYPE_ACTION)
        ->and(Command::make('b')->url('/x')->dispatch('event')->getType())->toBe(Command::TYPE_URL)
        ->and(Command::make('c')->dispatch('event')->getType())->toBe(Command::TYPE_DISPATCH);
});

it('generates a headline label from the name by default', function () {
    expect(Command::make('clear-cache')->getLabel())->toBe('Clear Cache');
});

it('evaluates closures with the command injected by name and type', function () {
    $command = Command::make('self-aware')->label(fn (Command $command): string => $command->getName());

    expect($command->getLabel())->toBe('self-aware');
});

it('accepts a command group instance for its group', function () {
    $group = CommandGroup::make('maintenance')->label('Maintenance');

    expect(Command::make('x')->group($group)->getGroup())->toBe('maintenance');
});

it('is hidden via hidden() or visible(false)', function () {
    expect(Command::make('a')->url('/x')->isVisible())->toBeTrue()
        ->and(Command::make('b')->url('/x')->hidden()->isVisible())->toBeFalse()
        ->and(Command::make('c')->url('/x')->visible(false)->isVisible())->toBeFalse()
        ->and(Command::make('d')->url('/x')->visible(fn (): bool => false)->isVisible())->toBeFalse();
});

it('checks authorization against the gate for the authenticated user', function () {
    Gate::define('manage-things', fn (User $user): bool => $user->name === 'Admin');

    $command = Command::make('managed')->url('/x')->authorize('manage-things');

    $this->actingAs(makeUser(['name' => 'Someone']));
    expect($command->isAuthorized())->toBeFalse();

    $this->actingAs(makeUser(['name' => 'Admin']));
    expect($command->isAuthorized())->toBeTrue();
});

it('is authorized by default', function () {
    expect(Command::make('open')->url('/x')->isAuthorized())->toBeTrue();
});
