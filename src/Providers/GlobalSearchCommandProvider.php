<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Providers;

use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Illuminate\Contracts\Support\Htmlable;
use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\CommandProvider;
use Superscript\FilamentSpotlight\SearchContext;

class GlobalSearchCommandProvider implements CommandProvider
{
    /**
     * With no explicit provider, the panel's own global search provider is
     * used — which is null when the panel has global search disabled.
     */
    public function __construct(
        protected ?GlobalSearchProvider $provider = null,
    ) {}

    public function search(SearchContext $context): array
    {
        if (blank($context->query)) {
            return [];
        }

        $results = ($this->provider ?? $context->panel->getGlobalSearchProvider())?->getResults($context->query);

        if ($results === null) {
            return [];
        }

        $commands = [];

        foreach ($results->getCategories() as $category => $categoryResults) {
            foreach ($categoryResults as $result) {
                if ($result instanceof GlobalSearchResult) {
                    $commands[] = $this->makeCommand($result, $category);
                }
            }
        }

        return $commands;
    }

    protected function makeCommand(GlobalSearchResult $result, string $category): Command
    {
        $title = $result->title instanceof Htmlable
            ? trim(strip_tags($result->title->toHtml()))
            : $result->title;

        $details = collect($result->details)
            ->map(fn (string $value, string $key): string => "{$key}: {$value}")
            ->implode(' · ');

        return Command::make('global-search:'.substr(md5("{$category}|{$result->url}|{$title}"), 0, 16))
            ->label($title)
            ->description($details !== '' ? $details : null)
            // The verbatim category (a resource's plural label) lets results
            // merge into a registered CommandGroup of the same name; the
            // client headline-cases unregistered names for display.
            ->group($category)
            ->url($result->url);
    }
}
