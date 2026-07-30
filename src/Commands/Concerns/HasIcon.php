<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use BackedEnum;
use Closure;

use function Filament\Support\generate_icon_html;

use Illuminate\Contracts\Support\Htmlable;

trait HasIcon
{
    protected string|BackedEnum|Htmlable|Closure|null $icon = null;

    public function icon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->icon);
    }

    public function getIconHtml(): ?string
    {
        return generate_icon_html($this->getIcon())?->toHtml();
    }
}
