<?php

declare(strict_types=1);

namespace Pest\Flow\Model;

/**
 * @internal
 */
final class RuleNode
{
    /**
     * @var list<ScenarioNode>
     */
    private array $scenarios = [];

    public function __construct(
        public readonly string $name,
        public readonly SourceLocation $source,
    ) {}

    public function addScenario(ScenarioNode $scenario): void
    {
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
