<?php

declare(strict_types=1);

namespace Superscript\FilamentSpotlight\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Components\Component;
use LogicException;

class Command extends Component
{
    use Concerns\CanBeAuthorized;
    use Concerns\CanBeHidden;
    use Concerns\HasAction;
    use Concerns\HasGroup;
    use Concerns\HasIcon;
    use Concerns\HasKeybinding;
    use Concerns\HasKeywords;
    use Concerns\HasLabel;
    use Concerns\HasSort;
    use Concerns\HasUrl;

    public const TYPE_ACTION = 'action';

    public const TYPE_URL = 'url';

    public const TYPE_DISPATCH = 'dispatch';

    protected string $evaluationIdentifier = 'command';

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    final public function __construct(
        protected string $name,
    ) {}

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    /**
     * The name doubles as the command's unique, stable ID: it is what the
     * client sends back to execute the command, and what the server uses to
     * look it up again. Provider-generated names must be deterministic.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Arbitrary scalar payload echoed back by the client when the command is
     * executed. Treat it as untrusted input on the server.
     *
     * @param  array<string, mixed>  $context
     */
    public function context(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getType(): string
    {
        return match (true) {
            $this->hasAction() => static::TYPE_ACTION,
            $this->hasUrl() => static::TYPE_URL,
            $this->hasDispatch() => static::TYPE_DISPATCH,
            default => throw new LogicException("Spotlight command [{$this->name}] must define one of action(), url(), or dispatch()."),
        };
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'command' => [$this],
            'context' => [$this->getContext()],
            'panel' => [Filament::getCurrentOrDefaultPanel()],
            'user' => [Filament::auth()->user()],
            default => [],
        };
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByType(string $parameterType): array
    {
        if ($parameterType === Panel::class) {
            return [Filament::getCurrentOrDefaultPanel()];
        }

        $user = Filament::auth()->user();

        if ($user instanceof $parameterType) {
            return [$user];
        }

        return [];
    }
}
