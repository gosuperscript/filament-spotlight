<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands\Concerns;

use Closure;

trait HasKeybinding
{
    protected string|Closure|null $keybinding = null;

    /**
     * A keyboard shortcut that runs the command directly, e.g. 'a' or
     * 'mod+shift+m' ('mod' is Cmd on macOS and Ctrl elsewhere). Shortcuts
     * without a modifier only fire while the user is not typing in a field.
     */
    public function keybinding(string|Closure|null $keybinding): static
    {
        $this->keybinding = $keybinding;

        return $this;
    }

    public function getKeybinding(): ?string
    {
        return $this->evaluate($this->keybinding);
    }
}
