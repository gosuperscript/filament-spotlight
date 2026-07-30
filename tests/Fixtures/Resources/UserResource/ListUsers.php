<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;

use Filament\Resources\Pages\ListRecords;
use Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
