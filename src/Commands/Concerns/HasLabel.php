<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasLabel
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $description = null;

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->evaluate($this->label) ?? (string) str($this->getName())->headline();
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }
}
