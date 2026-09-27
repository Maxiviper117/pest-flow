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

    public string $id;

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
        public readonly ?RuleNode $rule = null,
    ) {
        $this->id = $rule instanceof RuleNode
            ? NodeIdentifier::child($rule->id, $name)
            : NodeIdentifier::fromName($name);
    }

    public function addStep(StepNode $step): void
    {
        $step->id = NodeIdentifier::child(
            $this->id,
            $step->type->value . '-' . $step->description,
            array_map(static fn (StepNode $existing): string => $existing->id, $this->steps),
        );

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
