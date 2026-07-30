<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Exceptions;

use LogicException;

final class DuplicateCommandException extends LogicException
{
    public static function make(string $name): self
    {
        return new self("A Spotlight command named [{$name}] is already registered. Command names must be unique, as they are used to look the command up on execution.");
    }
}
