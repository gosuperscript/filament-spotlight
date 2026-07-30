<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasUrl
{
    protected string|Closure|null $url = null;

    protected bool|Closure $shouldOpenUrlInNewTab = false;

    public function url(string|Closure|null $url, bool|Closure $shouldOpenInNewTab = false): static
    {
        $this->url = $url;
        $this->shouldOpenUrlInNewTab = $shouldOpenInNewTab;

        return $this;
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    public function getUrl(): ?string
    {
        return $this->evaluate($this->url);
    }

    public function shouldOpenUrlInNewTab(): bool
    {
        return (bool) $this->evaluate($this->shouldOpenUrlInNewTab);
    }
}
