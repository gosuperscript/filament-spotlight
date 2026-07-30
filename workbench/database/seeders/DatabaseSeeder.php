<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        User::query()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => $password,
        ]);

        foreach ([
            'Jane Cooper',
            'Wade Warren',
            'Esther Howard',
            'Cameron Williamson',
            'Brooklyn Simmons',
            'Leslie Alexander',
            'Jenny Wilson',
            'Guy Hawkins',
        ] as $name) {
            User::query()->create([
                'name' => $name,
                'email' => str_replace(' ', '.', strtolower($name)).'@example.com',
                'password' => $password,
            ]);
        }
    }
}
