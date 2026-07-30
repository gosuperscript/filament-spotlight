<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Contracts;

use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\SearchContext;

interface CommandProvider
{
    /**
     * Return commands matching the given search context. Called server-side
     * on every (debounced) keystroke, and again during execution to
     * re-materialize a provider-generated command by its ID.
     *
     * Command names must be deterministic (e.g. "documents:recent:{id}") so
     * a command found by search can be found again on execute.
     *
     * @return array<Command>
     */
    public function search(SearchContext $context): array;
}
