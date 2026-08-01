<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands;

use Closure;
use Filament\Support\Components\Component;
use Superscript\FilamentSpotlight\Support\MatchesClosureDependencyTypes;

class CommandGroup extends Component
{
    use MatchesClosureDependencyTypes;

    protected string $evaluationIdentifier = 'group';

    protected string|Closure|null $label = null;

    protected int|Closure $sort = 0;

    final public function __construct(
        protected string $name,
    ) {}

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->evaluate($this->label) ?? (string) str($this->name)->headline();
    }

    public function sort(int|Closure $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->evaluate($this->sort);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'sort' => $this->getSort(),
        ];
    }
}
