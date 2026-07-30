<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * Where the user currently is in the panel, resolved server-side from the
 * URL the client reports. Everything derived from it is re-checked for
 * visibility and authorization, so a spoofed URL can only change which
 * commands are offered — never what the user is allowed to run.
 */
readonly class PageContext
{
    /**
     * @param  class-string<Page>|null  $page
     * @param  class-string<\Filament\Resources\Resource>|null  $resource
     */
    public function __construct(
        public ?string $page = null,
        public ?string $resource = null,
        public ?Model $record = null,
    ) {}
}
