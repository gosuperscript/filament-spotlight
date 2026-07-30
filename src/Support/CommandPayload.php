<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Support;

use Superscript\FilamentSpotlight\Commands\Command;

final class CommandPayload
{
    /**
     * Serialize a command into the JSON shape the client renders. Closures
     * are evaluated here, server-side — the client only ever receives the
     * command's ID and display data.
     *
     * @return array<string, mixed>
     */
    public static function fromCommand(Command $command): array
    {
        $type = $command->getType();

        return [
            'id' => $command->getName(),
            'type' => $type,
            'label' => $command->getLabel(),
            'description' => $command->getDescription(),
            'icon' => $command->getIconHtml(),
            'group' => $command->getGroup(),
            'keywords' => $command->getKeywords(),
            'keybinding' => $command->getKeybinding(),
            'sort' => $command->getSort(),
            'url' => $type === Command::TYPE_URL ? $command->getUrl() : null,
            'openInNewTab' => $type === Command::TYPE_URL && $command->shouldOpenUrlInNewTab(),
            'event' => $type === Command::TYPE_DISPATCH ? $command->getDispatchEvent() : null,
            'eventArgs' => $type === Command::TYPE_DISPATCH ? $command->getDispatchArgs() : [],
            'context' => $command->getContext(),
            'contextual' => $command->isContextual(),
        ];
    }
}
