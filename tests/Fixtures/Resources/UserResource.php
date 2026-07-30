<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\HasContextualSpotlightCommands;
use Superscript\FilamentSpotlight\PageContext;
use Superscript\FilamentSpotlight\Tests\Fixtures\User;

class UserResource extends Resource implements HasContextualSpotlightCommands
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

    public static function getContextualSpotlightCommands(PageContext $context): array
    {
        if ($record = $context->record) {
            return [
                Command::make("users:{$record->getKey()}:greet")
                    ->label("Greet {$record->getAttribute('name')}")
                    ->action(fn () => Cache::put('greeted', $record->getKey())),
                Command::make("users:{$record->getKey()}:concealed")
                    ->label('Concealed')
                    ->visible(false)
                    ->action(fn () => null),
            ];
        }

        return [
            Command::make('users:export')
                ->label('Export users')
                ->action(fn () => Cache::put('exported', true)),
        ];
    }
}
