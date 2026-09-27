<?php

declare(strict_types=1);

namespace Pest\Flow\Runtime;

use Closure;
use LogicException;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use PHPUnit\Framework\SkippedTest;
use Throwable;

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
        $scenario->startExecution();

        $context = new ScenarioContext($scenario, $testCase);

        try {
            FlowContext::withScenario($context, static function () use ($context, $definition): void {
                $definition->call($context->testCase);
                $context->collectingSteps = false;

                foreach ($context->stepDefinitions as $stepDefinition) {
                    self::executeStep($context, $stepDefinition['step'], $stepDefinition['definition']);
                }
            });

            $scenario->markPassed();
        } catch (Throwable $exception) {
            foreach ($scenario->steps() as $step) {
                if ($step->status === ExecutionStatus::Pending) {
                    $step->markSkipped();
                }
            }

            if ($exception instanceof SkippedTest) {
                $scenario->markSkipped($exception);
            } else {
                $scenario->markFailed($exception);
            }

            throw $exception;
        }
    }

    public static function step(StepType $type, string $description, Closure $definition): void
    {
        $context = FlowContext::currentScenario();

        if (! $context instanceof ScenarioContext) {
            throw new LogicException(sprintf('%s steps must be declared inside scenario().', ucfirst($type->value)));
        }

        $step = new StepNode($type, $description, SourceLocation::capture());
        $context->scenario->addStep($step);

        if ($context->collectingSteps) {
            $context->stepDefinitions[] = [
                'step' => $step,
                'definition' => $definition,
            ];

            return;
        }

        self::executeStep($context, $step, $definition);
    }

    private static function executeStep(ScenarioContext $context, StepNode $step, Closure $definition): void
    {
        $step->startExecution();

        try {
            $definition->call($context->testCase);
            $step->markPassed();
        } catch (Throwable $exception) {
            if ($exception instanceof SkippedTest) {
                $step->markSkipped($exception);
            } else {
                $step->markFailed($exception);
            }

            throw $exception;
        }
    }
}
