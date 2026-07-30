<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\HasContextualSpotlightCommands;
use Superscript\FilamentSpotlight\Contracts\HasSpotlightCommands;
use Superscript\FilamentSpotlight\PageContext;

class Settings extends Page implements HasContextualSpotlightCommands, HasSpotlightCommands
{
    protected string $view = 'filament-spotlight::spotlight';

    public static function getSpotlightCommands(): array
    {
        return [
            Command::make('settings:clear-cache')
                ->label('Clear cache')
                ->keywords(['flush'])
                ->action(fn () => Cache::put('cache-cleared', true)),
        ];
    }

    public static function getContextualSpotlightCommands(PageContext $context): array
    {
        return [
            Command::make('settings:reset')
                ->label('Reset settings')
                ->action(fn () => Cache::put('settings-reset', true)),
        ];
    }
}
