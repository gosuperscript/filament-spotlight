<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Contracts;

use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\PageContext;

interface HasContextualSpotlightCommands
{
    /**
     * Commands scoped to where the user currently is, shown pinned at the
     * top of the menu. Implement on a page or resource: it is only consulted
     * while the user is on that page (or one of the resource's pages), and
     * on record pages the context carries the resolved record.
     *
     * Command names must be deterministic and unique per context (e.g.
     * "users:{$record->getKey()}:deactivate"): the registry is rebuilt from
     * the same URL when a command is executed, and visibility/authorization
     * are re-checked then. Closures capture the context directly, so make
     * visible()/authorize() checks record-aware where it matters.
     *
     * @return array<Command>
     */
    public static function getContextualSpotlightCommands(PageContext $context): array;
}
