<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\HasSpotlightCommands;

class Settings extends Page implements HasSpotlightCommands
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
}
