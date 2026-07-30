<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Tests\Fixtures;

use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Contracts\CommandProvider;
use Superscript\FilamentSpotlight\SearchContext;

class RecentDocumentsProvider implements CommandProvider
{
    public function search(SearchContext $context): array
    {
        if ($context->query === '') {
            return [];
        }

        return [
            Command::make('documents:'.$context->query)
                ->label('Open document '.$context->query)
                ->group('documents')
                ->context(['slug' => $context->query])
                ->action(fn (array $context): string => '/documents/'.$context['slug']),
        ];
    }
}
