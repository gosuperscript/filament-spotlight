<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight;

use Superscript\FilamentSpotlight\Commands\Command;
use Superscript\FilamentSpotlight\Exceptions\DuplicateCommandException;

class CommandRegistry
{
    /**
     * @var array<string, Command>
     */
    protected array $commands = [];

    public function add(Command ...$commands): static
    {
        foreach ($commands as $command) {
            $name = $command->getName();

            if (isset($this->commands[$name])) {
                throw DuplicateCommandException::make($name);
            }

            $this->commands[$name] = $command;
        }

        return $this;
    }

    /**
     * @return array<string, Command>
     */
    public function all(): array
    {
        return $this->commands;
    }

    public function find(string $id): ?Command
    {
        return $this->commands[$id] ?? null;
    }

    /**
     * Commands the current user is allowed to see and run.
     *
     * @return array<Command>
     */
    public function visible(): array
    {
        return array_values(array_filter(
            $this->commands,
            fn (Command $command): bool => $command->isVisible() && $command->isAuthorized(),
        ));
    }
}
