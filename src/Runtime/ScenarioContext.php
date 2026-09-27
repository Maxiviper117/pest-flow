<?php

declare(strict_types=1);

namespace Pest\Flow\Runtime;

use Closure;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\StepNode;

/** @internal */
final class ScenarioContext
{
    /**
     * @var list<array{step: StepNode, definition: Closure}>
     */
    public array $stepDefinitions = [];

    public bool $collectingSteps = true;

    public function __construct(
        public readonly ScenarioNode $scenario,
        public readonly object $testCase,
    ) {}
}
