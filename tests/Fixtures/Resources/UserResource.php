<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Superscript\FilamentSpotlight\Tests\Fixtures\User;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\ListUsers::route('/'),
            'edit' => UserResource\EditUser::route('/{record}/edit'),
        ];
    }
}
