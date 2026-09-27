<?php

declare(strict_types=1);

namespace Pest\Flow\Runtime;

use Closure;
use LogicException;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;

/**
 * @internal
 */
final class ScenarioRunner
{
    public static function run(
        ScenarioNode $scenario,
        object $testCase,
        Closure $definition,
    ): void {
        $scenario->clearSteps();

        $context = new ScenarioContext($scenario, $testCase);

        FlowContext::withScenario($context, static function () use ($context, $definition): void {
            $definition->call($context->testCase);
        });
    }

    public static function step(StepType $type, string $description, Closure $definition): void
    {
        $context = FlowContext::currentScenario();

        if (! $context instanceof ScenarioContext) {
            throw new LogicException(sprintf('%s steps must be declared inside scenario().', ucfirst($type->value)));
        }

        $context->scenario->addStep(new StepNode($type, $description, SourceLocation::capture()));

        $definition->call($context->testCase);
    }
}
