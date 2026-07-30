<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasSort
{
    protected int|Closure $sort = 0;

    public function sort(int|Closure $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->evaluate($this->sort);
    }
}
