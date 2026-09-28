<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * A rule node in the discovered behaviour registry.
 */
final class RuleNode implements TaggableNode
{
    use HasTags;

    /**
     * @var list<ScenarioNode>
     */
    private array $scenarios = [];

    public string $id;

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
        public readonly FeatureNode $feature,
    ) {
        $this->id = NodeIdentifier::child($feature->id, $name);
    }

    public function addScenario(ScenarioNode $scenario): void
    {
        if ($scenario->rule !== $this) {
            throw new \LogicException('A scenario can only be added to its owning rule.');
        }

        $scenario->id = NodeIdentifier::child(
            $this->id,
            $scenario->name,
            array_map(static fn (ScenarioNode $existing): string => $existing->id, $this->scenarios),
        );

        $this->scenarios[] = $scenario;
    }

    /**
     * @return list<ScenarioNode>
     */
    public function scenarios(): array
    {
        return $this->scenarios;
    }
}
