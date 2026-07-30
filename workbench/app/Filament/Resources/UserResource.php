<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\HasContextualSpotlightCommands;
use Superscript\FilamentSpotlight\PageContext;
use Workbench\App\Models\User;

class UserResource extends Resource implements HasContextualSpotlightCommands
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            TextInput::make('email')->email(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('email'),
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
        $notify = fn (string $title) => fn () => Notification::make()->title($title)->success()->send();

        if ($record = $context->record) {
            return [
                Command::make("users:{$record->getKey()}:impersonate")
                    ->label('Impersonate')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->action($notify("Now impersonating {$record->getAttribute('name')}")),
                Command::make("users:{$record->getKey()}:reset-password")
                    ->label('Send password reset')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->action($notify('Password reset sent')),
                Command::make("users:{$record->getKey()}:deactivate")
                    ->label('Deactivate')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->action($notify("{$record->getAttribute('name')} deactivated")),
            ];
        }

        return [
            Command::make('users:export')
                ->label('Export users')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action($notify('Export started')),
        ];
    }
}
