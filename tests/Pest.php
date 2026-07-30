<?php

declare(strict_types=1);

use Superscript\FilamentSpotlight\Tests\Fixtures\User;
use Superscript\FilamentSpotlight\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function makeUser(array $attributes = []): User
{
    return User::forceCreate([
        'name' => 'Test User',
        'email' => uniqid('user', true).'@example.com',
        'password' => bcrypt('password'),
        ...$attributes,
    ]);
}
