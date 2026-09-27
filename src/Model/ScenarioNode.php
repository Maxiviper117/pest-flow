<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final class ScenarioNode
{
    /**
     * @var list<StepNode>
     */
    private array $steps = [];

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
    ) {}

    public function addStep(StepNode $step): void
    {
        $this->steps[] = $step;
    }

    public function clearSteps(): void
    {
        $this->steps = [];
    }

    /**
     * @return list<StepNode>
     */
    public function steps(): array
    {
        return $this->steps;
    }
}
