<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;

use Filament\Resources\Pages\EditRecord;
use Superscript\FilamentSpotlight\Tests\Fixtures\Resources\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
