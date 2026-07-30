<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;
use Superscript\FilamentSpotlight\Commands\CommandGroup;

trait HasGroup
{
    protected string|CommandGroup|Closure|null $group = null;

    public function group(string|CommandGroup|Closure|null $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        $group = $this->evaluate($this->group);

        return $group instanceof CommandGroup ? $group->getName() : $group;
    }
}
