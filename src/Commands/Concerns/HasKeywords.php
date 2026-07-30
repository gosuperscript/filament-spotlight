<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasKeywords
{
    /**
     * @var array<string> | Closure
     */
    protected array|Closure $keywords = [];

    /**
     * @param  array<string> | Closure  $keywords
     */
    public function keywords(array|Closure $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getKeywords(): array
    {
        return $this->evaluate($this->keywords);
    }
}
