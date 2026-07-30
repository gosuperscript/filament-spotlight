<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight;

use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

readonly class SearchContext
{
    public function __construct(
        public string $query,
        public Panel $panel,
        public ?Authenticatable $user,
    ) {}
}
