<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Livewire\Spotlight;
use Superscript\FilamentSpotlight\PageContext;
use Superscript\FilamentSpotlight\SpotlightPlugin;
use Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;
use Superscript\FilamentSpotlight\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->actingAs(makeUser());
});

function contextualSpotlight(): Spotlight
{
    $component = new Spotlight;
    $component->mount();

    return $component;
}

/**
 * @return array{context: array{badge: string | null, label: string} | null, commands: array<array<string, mixed>>}
 */
function staticResponse(?string $url = null): array
{
    return contextualSpotlight()->getStaticCommands($url);
}

function staticCommands(?string $url = null): Collection
{
    return collect(staticResponse($url)['commands']);
}

function editUrlFor(User $record): string
{
    return UserResource::getUrl('edit', ['record' => $record]);
}

it('offers record commands on a record page, pinned under a record chip', function () {
    $record = makeUser(['name' => 'Jane Cooper']);

    $response = staticResponse(editUrlFor($record));

    $greet = collect($response['commands'])->firstWhere('id', "users:{$record->getKey()}:greet");

    expect($response['context'])->toBe(['badge' => 'User', 'label' => 'Jane Cooper'])
        ->and($greet)->not->toBeNull()
        ->and($greet['label'])->toBe('Greet Jane Cooper')
        ->and($greet['contextual'])->toBeTrue()
        ->and($greet['group'])->toBeNull();
});

it('offers page commands on a resource list page, under the resource chip', function () {
    $response = staticResponse('http://localhost/admin/users');

    $export = collect($response['commands'])->firstWhere('id', 'users:export');

    expect($response['context'])->toBe(['badge' => null, 'label' => 'Users'])
        ->and($export)->not->toBeNull()
        ->and($export['contextual'])->toBeTrue()
        ->and($export['group'])->toBeNull();
});

it('offers page commands on a plain page, under the page chip', function () {
    $response = staticResponse('http://localhost/admin/settings');

    $reset = collect($response['commands'])->firstWhere('id', 'settings:reset');

    expect($response['context'])->toBe(['badge' => null, 'label' => 'Settings'])
        ->and($reset)->not->toBeNull()
        ->and($reset['contextual'])->toBeTrue();
});

it('sends no chip when the page offers no contextual commands', function () {
    $response = staticResponse('http://localhost/admin');

    expect($response['context'])->toBeNull();
});

it('keeps an explicit group on contextual commands while still pinning them', function () {
    $record = makeUser(['name' => 'Jane Cooper']);

    SpotlightPlugin::get()->commands(fn (?User $record): array => $record ? [
        Command::make("users:{$record->getKey()}:grouped")
            ->label('Grouped')
            ->group('maintenance')
            ->contextual()
            ->action(fn () => null),
    ] : []);

    $grouped = staticCommands(editUrlFor($record))->firstWhere('id', "users:{$record->getKey()}:grouped");

    expect($grouped['group'])->toBe('maintenance')
        ->and($grouped['contextual'])->toBeTrue();
});

it('excludes hidden contextual commands from payloads', function () {
    $record = makeUser();

    $ids = staticCommands(editUrlFor($record))->pluck('id');

    expect($ids)->toContain("users:{$record->getKey()}:greet")
        ->and($ids)->not->toContain("users:{$record->getKey()}:concealed");
});

it('only offers contextual commands where they apply', function () {
    $record = makeUser();

    $ids = staticCommands('http://localhost/admin')->pluck('id');

    expect($ids)->not->toContain("users:{$record->getKey()}:greet")
        ->and($ids)->not->toContain('users:export')
        ->and($ids)->not->toContain('settings:reset');
});

it('offers no contextual commands for unresolvable urls', function (?string $url) {
    $record = makeUser();

    $response = staticResponse($url);
    $ids = collect($response['commands'])->pluck('id');

    expect($response['context'])->toBeNull()
        ->and($ids)->not->toContain("users:{$record->getKey()}:greet")
        ->and($ids)->not->toContain('users:export');
})->with([
    'no url' => [null],
    'unroutable url' => ['http://localhost/nope'],
    'not a url' => ['not a url'],
    'route outside the panel' => ['http://localhost/_workbench'],
]);

it('falls back to page commands when the record cannot be resolved', function () {
    $response = staticResponse('http://localhost/admin/users/999999/edit');
    $ids = collect($response['commands'])->pluck('id');

    expect($ids)->not->toContain('users:999999:greet')
        ->and($ids)->toContain('users:export')
        ->and($response['context'])->toBe(['badge' => null, 'label' => 'Users']);
});

it('executes a record command re-materialized from the url', function () {
    $record = makeUser();

    $result = contextualSpotlight()->execute("users:{$record->getKey()}:greet", [
        'url' => editUrlFor($record),
    ]);

    expect($result)->toBeNull()
        ->and(Cache::get('greeted'))->toBe($record->getKey());
});

it('executes a page command re-materialized from the url', function () {
    $result = contextualSpotlight()->execute('users:export', [
        'url' => 'http://localhost/admin/users',
    ]);

    expect($result)->toBeNull()
        ->and(Cache::get('exported'))->toBeTrue();
});

it('aborts with 404 when executing a contextual command without its url', function () {
    $record = makeUser();

    contextualSpotlight()->execute("users:{$record->getKey()}:greet");
})->throws(NotFoundHttpException::class);

it('aborts with 403 when executing a hidden contextual command', function () {
    $record = makeUser();

    try {
        contextualSpotlight()->execute("users:{$record->getKey()}:concealed", [
            'url' => editUrlFor($record),
        ]);

        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('injects the record and page context into plugin command closures', function () {
    $record = makeUser(['name' => 'Wade Warren']);

    SpotlightPlugin::get()->navigation(false)->commands(fn (?User $record, ?PageContext $pageContext): array => $record ? [
        Command::make('whereami')
            ->label("On {$record->name} via ".class_basename((string) $pageContext?->page))
            ->action(fn () => null),
    ] : []);

    $payloads = staticCommands(editUrlFor($record));

    expect($payloads->firstWhere('id', 'whereami')['label'])->toBe('On Wade Warren via EditUser');

    expect(staticCommands()->pluck('id'))->not->toContain('whereami');
});

it('passes the url through Livewire calls end to end', function () {
    $record = makeUser(['name' => 'Esther Howard']);

    Livewire::test(Spotlight::class)
        ->call('getStaticCommands', editUrlFor($record))
        ->assertReturned(fn (array $response): bool => collect($response['commands'])
            ->contains(fn (array $payload): bool => $payload['id'] === "users:{$record->getKey()}:greet"));
});
