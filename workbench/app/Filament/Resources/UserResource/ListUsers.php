<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\UserResource;

use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
