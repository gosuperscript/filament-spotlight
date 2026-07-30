<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

trait CanBeAuthorized
{
    protected string|Closure|null $authorizedAbility = null;

    protected mixed $authorizedAbilityArguments = [];

    public function authorize(string|Closure $ability, mixed $arguments = []): static
    {
        $this->authorizedAbility = $ability;
        $this->authorizedAbilityArguments = $arguments;

        return $this;
    }

    public function isAuthorized(): bool
    {
        if (blank($this->authorizedAbility)) {
            return true;
        }

        return Gate::forUser(Filament::auth()->user())->check(
            $this->evaluate($this->authorizedAbility),
            $this->evaluate($this->authorizedAbilityArguments),
        );
    }
}
