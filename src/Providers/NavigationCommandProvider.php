<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Providers;

use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Contracts\Support\Arrayable;
use Superscript\FilamentSpotlight\Commands\Command;

class NavigationCommandProvider
{
    /**
     * Turn the panel's navigation into URL commands. Filament has already
     * resolved visibility, authorization, and tenancy when building the
     * navigation, so every item here is safe to show.
     *
     * @return array<Command>
     */
    public function commands(Panel $panel): array
    {
        $commands = [];

        foreach ($panel->getNavigation() as $group) {
            $groupLabel = $group->getLabel();

            foreach ($this->normalizeItems($group->getItems()) as $item) {
                $commands[] = $this->makeCommand($item, $groupLabel);

                foreach ($this->normalizeItems($item->getChildItems()) as $childItem) {
                    $commands[] = $this->makeCommand($childItem, $groupLabel, parentLabel: $item->getLabel());
                }
            }
        }

        return array_values(array_filter($commands));
    }

    /**
     * @param  array<NavigationItem> | Arrayable<int, NavigationItem>  $items
     * @return array<NavigationItem>
     */
    protected function normalizeItems(array|Arrayable $items): array
    {
        $items = $items instanceof Arrayable ? $items->toArray() : $items;

        return array_values(array_filter(
            $items,
            fn (mixed $item): bool => $item instanceof NavigationItem,
        ));
    }

    protected function makeCommand(NavigationItem $item, ?string $groupLabel, ?string $parentLabel = null): ?Command
    {
        $url = $item->getUrl();

        if (blank($url)) {
            return null;
        }

        $label = $parentLabel ? "{$parentLabel} — {$item->getLabel()}" : $item->getLabel();

        // The URL hash keeps the ID deterministic while disambiguating items
        // that share a label across navigation groups.
        $id = 'navigation:'.str($label)->slug().':'.substr(md5($url), 0, 8);

        return Command::make($id)
            ->label($label)
            ->icon($item->getIcon())
            ->group($groupLabel ?? __('filament-spotlight::spotlight.groups.navigation'))
            ->url($url, $item->shouldOpenUrlInNewTab());
    }
}
