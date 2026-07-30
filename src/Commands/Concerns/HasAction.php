<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasAction
{
    protected ?Closure $action = null;

    protected ?string $dispatchEvent = null;

    /**
     * @var array<mixed> | Closure
     */
    protected array|Closure $dispatchArgs = [];

    /**
     * Run a closure on the server when the command is executed. Returning a
     * string URL from the closure redirects the user to it.
     */
    public function action(Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function hasAction(): bool
    {
        return $this->action !== null;
    }

    /**
     * Dispatch a Livewire event in the browser when the command is executed.
     * This runs entirely client-side, without a server roundtrip.
     *
     * @param  array<mixed> | Closure  $args
     */
    public function dispatch(string $event, array|Closure $args = []): static
    {
        $this->dispatchEvent = $event;
        $this->dispatchArgs = $args;

        return $this;
    }

    public function hasDispatch(): bool
    {
        return $this->dispatchEvent !== null;
    }

    public function getDispatchEvent(): ?string
    {
        return $this->dispatchEvent;
    }

    /**
     * @return array<mixed>
     */
    public function getDispatchArgs(): array
    {
        return $this->evaluate($this->dispatchArgs);
    }

    /**
     * @param  array<string, mixed>  $namedInjections
     */
    public function callAction(array $namedInjections = []): mixed
    {
        return $this->evaluate($this->action, $namedInjections);
    }
}
