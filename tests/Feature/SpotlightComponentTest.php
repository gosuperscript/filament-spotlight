<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Livewire\Spotlight;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Superscript\FilamentSpotlight\Tests\Fixtures\RecentDocumentsProvider;
use Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;
use Superscript\FilamentSpotlight\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->actingAs(makeUser());
});

function spotlight(): Spotlight
{
    $component = new Spotlight;
    $component->mount();

    return $component;
}

it('mounts and renders through Livewire', function () {
    Livewire::test(Spotlight::class)
        ->assertOk()
        ->assertSet('panelId', 'admin');
});

it('returns static command payloads', function () {
    SpotlightPlugin::get()->navigation(false)->commands([
        Command::make('ping')->label('Ping')->keywords(['pong'])->action(fn () => null),
    ]);

    $response = spotlight()->getStaticCommands();
    $payloads = $response['commands'];

    expect($payloads)->toHaveCount(2) // ping + settings:clear-cache fixture page
        ->and($response['context'])->toBeNull();

    $ping = collect($payloads)->firstWhere('id', 'ping');

    expect($ping)
        ->toMatchArray([
            'type' => 'action',
            'label' => 'Ping',
            'keywords' => ['pong'],
        ]);
});

it('includes the keybinding in command payloads', function () {
    SpotlightPlugin::get()->navigation(false)->commands([
        Command::make('assign')->keybinding('a')->action(fn () => null),
    ]);

    $assign = collect(spotlight()->getStaticCommands()['commands'])->firstWhere('id', 'assign');

    expect($assign['keybinding'])->toBe('a');
});

it('ships only visible and authorized keybound commands with the client config', function () {
    Gate::define('never', fn (User $user): bool => false);

    SpotlightPlugin::get()->commands([
        Command::make('assign')->keybinding('a')->action(fn () => null),
        Command::make('plain')->action(fn () => null),
        Command::make('concealed')->keybinding('c')->hidden()->action(fn () => null),
        Command::make('forbidden')->keybinding('f')->authorize('never')->action(fn () => null),
    ]);

    Livewire::test(Spotlight::class)->assertViewHas(
        'config',
        fn (array $config): bool => collect($config['keybindingItems'])->pluck('id')->all() === ['assign'],
    );
});

it('offers contextual keybound commands for the page the client reports', function () {
    $record = makeUser(['name' => 'Jane Cooper']);

    $payloads = spotlight()->getKeybindingCommands(UserResource::getUrl('edit', ['record' => $record]));

    $greet = collect($payloads)->firstWhere('id', "users:{$record->getKey()}:greet");

    expect($greet)->not->toBeNull()
        ->and($greet['keybinding'])->toBe('g g')
        ->and(collect($payloads)->pluck('id'))->not->toContain("users:{$record->getKey()}:concealed");
});

it('offers no contextual keybound commands without a reported page', function () {
    $record = makeUser(['name' => 'Jane Cooper']);

    expect(collect(spotlight()->getKeybindingCommands(null))->pluck('id'))
        ->not->toContain("users:{$record->getKey()}:greet");
});

it('excludes hidden and unauthorized commands from static payloads', function () {
    Gate::define('never', fn (User $user): bool => false);

    SpotlightPlugin::get()->navigation(false)->commands([
        Command::make('concealed')->url('/x')->hidden(),
        Command::make('forbidden')->url('/x')->authorize('never'),
    ]);

    $ids = collect(spotlight()->getStaticCommands()['commands'])->pluck('id');

    expect($ids)->not->toContain('concealed')
        ->and($ids)->not->toContain('forbidden');
});

it('includes navigation commands with urls in static payloads', function () {
    $payloads = collect(spotlight()->getStaticCommands()['commands']);

    $dashboard = $payloads->first(fn (array $payload): bool => $payload['label'] === 'Dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['type'])->toBe('url')
        ->and($dashboard['url'])->toContain('/admin');
});

it('finds records through global search while typing', function () {
    makeUser(['name' => 'Alice Wonder']);
    makeUser(['name' => 'Bob Builder']);

    $results = spotlight()->search('Alice');

    expect($results)->toHaveCount(1)
        ->and($results[0]['label'])->toBe('Alice Wonder')
        ->and($results[0]['type'])->toBe('url')
        ->and($results[0]['url'])->toContain('/admin/users/');
});

it('searches custom command providers', function () {
    SpotlightPlugin::get()->globalSearch(false)->providers([RecentDocumentsProvider::class]);

    $results = spotlight()->search('report');

    expect($results)->toHaveCount(1)
        ->and($results[0]['id'])->toBe('documents:report')
        ->and($results[0]['group'])->toBe('documents');
});

it('groups global search results under the verbatim category name', function () {
    makeUser(['name' => 'Alice Wonder']);

    $results = spotlight()->search('Alice');

    expect($results)->toHaveCount(1)
        ->and($results[0]['group'])->toBe('users');
});

it('searches an explicit global search provider even when the panel has none', function () {
    makeUser(['name' => 'Alice Wonder']);

    Filament::getCurrentPanel()->globalSearch(false);

    expect(spotlight()->search('Alice'))->toHaveCount(0);

    SpotlightPlugin::get()->globalSearch(DefaultGlobalSearchProvider::class);

    $results = spotlight()->search('Alice');

    expect($results)->toHaveCount(1)
        ->and($results[0]['label'])->toBe('Alice Wonder');
});

it('executes an action command and reports side effects', function () {
    $executed = false;

    SpotlightPlugin::get()->commands([
        Command::make('mark')->action(function () use (&$executed) {
            $executed = true;
        }),
    ]);

    expect(spotlight()->execute('mark'))->toBeNull()
        ->and($executed)->toBeTrue();
});

it('executes a page-contributed command', function () {
    expect(spotlight()->execute('settings:clear-cache'))->toBeNull()
        ->and(Cache::get('cache-cleared'))->toBeTrue();
});

it('redirects when an action returns a url', function () {
    SpotlightPlugin::get()->commands([
        Command::make('go')->action(fn (): string => '/target'),
    ]);

    expect(spotlight()->execute('go'))->toBe(['redirect' => '/target']);
});

it('redirects url commands executed server-side', function () {
    SpotlightPlugin::get()->commands([
        Command::make('link')->url('/somewhere'),
    ]);

    expect(spotlight()->execute('link'))->toBe(['redirect' => '/somewhere']);
});

it('re-materializes provider commands on execute using the query', function () {
    SpotlightPlugin::get()->globalSearch(false)->providers([RecentDocumentsProvider::class]);

    $result = spotlight()->execute('documents:report', ['query' => 'report']);

    expect($result)->toBe(['redirect' => '/documents/report']);
});

it('aborts with 404 for unknown command ids', function () {
    spotlight()->execute('does-not-exist');
})->throws(NotFoundHttpException::class);

it('aborts with 403 when executing a hidden command by id', function () {
    SpotlightPlugin::get()->commands([
        Command::make('secret')->visible(false)->action(fn () => null),
    ]);

    try {
        spotlight()->execute('secret');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('aborts with 403 when the gate denies an otherwise visible command', function () {
    Gate::define('run-secret', fn (User $user): bool => false);

    SpotlightPlugin::get()->commands([
        Command::make('gated')->authorize('run-secret')->action(fn () => null),
    ]);

    try {
        spotlight()->execute('gated');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});
