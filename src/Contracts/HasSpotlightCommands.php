<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Contracts;

use Superscript\FilamentSpotlight\Commands\Command;

interface HasSpotlightCommands
{
    /**
     * Commands this page or resource contributes to the command menu.
     *
     * @return array<Command>
     */
    public static function getSpotlightCommands(): array;
}
