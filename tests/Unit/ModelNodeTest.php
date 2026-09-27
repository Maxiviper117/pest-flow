<?php

declare(strict_types=1);

use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use Pest\Flow\Runtime\ScenarioRunner;

use function Pest\Flow\given;
use function Pest\Flow\then;
use function Pest\Flow\when;

it('preserves child order in the behaviour hierarchy', function (): void {
    $feature = new FeatureNode('Contractor activation', new SourceLocation('feature.php', 10));
    $rule = new RuleNode('Only compliant contractors may activate', new SourceLocation('feature.php', 12), $feature);
    $otherRule = new RuleNode('Only authorised contractors may activate', new SourceLocation('feature.php', 20), $feature);
    $scenario = new ScenarioNode('Activate a compliant contractor', new SourceLocation('feature.php', 14), $rule);
    $otherScenario = new ScenarioNode('Reject an incomplete contractor', new SourceLocation('feature.php', 18), $rule);
    $step = new StepNode(StepType::Given, 'a compliant contractor', new SourceLocation('feature.php', 16));
    $otherStep = new StepNode(StepType::Then, 'activation is rejected', new SourceLocation('feature.php', 22));

    $feature->addRule($rule);
    $feature->addRule($otherRule);
    $rule->addScenario($scenario);
    $rule->addScenario($otherScenario);
    $scenario->addStep($step);
    $scenario->addStep($otherStep);

    expect($feature->rules())->toBe([$rule, $otherRule])
        ->and($rule->scenarios())->toBe([$scenario, $otherScenario])
        ->and($scenario->steps())->toBe([$step, $otherStep])
        ->and($rule->feature)->toBe($feature)
        ->and($scenario->rule)->toBe($rule)
        ->and($step->type)->toBe(StepType::Given)
        ->and($step->source->file)->toBe('feature.php')
        ->and($step->source->line)->toBe(16);
});

it('rejects a rule owned by a different feature', function (): void {
    $feature = new FeatureNode('Feature A', new SourceLocation('feature.php', 1));
    $otherFeature = new FeatureNode('Feature B', new SourceLocation('feature.php', 2));
    $rule = new RuleNode('Rule B', new SourceLocation('feature.php', 3), $otherFeature);

    expect(fn () => $feature->addRule($rule))
        ->toThrow(LogicException::class, 'A rule can only be added to its owning feature.');

    expect($feature->rules())->toBe([])
        ->and($otherFeature->rules())->toBe([]);
});

it('rejects a scenario owned by a different rule', function (): void {
    $feature = new FeatureNode('Feature', new SourceLocation('feature.php', 1));
    $rule = new RuleNode('Rule A', new SourceLocation('feature.php', 2), $feature);
    $otherRule = new RuleNode('Rule B', new SourceLocation('feature.php', 3), $feature);
    $scenario = new ScenarioNode('Scenario B', new SourceLocation('feature.php', 4), $otherRule);

    expect(fn () => $rule->addScenario($scenario))
        ->toThrow(LogicException::class, 'A scenario can only be added to its owning rule.');

    expect($rule->scenarios())->toBe([])
        ->and($otherRule->scenarios())->toBe([]);
});

it('replaces recorded steps between scenario executions', function (): void {
    $scenario = new ScenarioNode('adds two numbers', new SourceLocation('feature.php', 4));
    $definition = function (): void {
        given('two numbers', fn () => null);
    };

    ScenarioRunner::run($scenario, $this, $definition);
    ScenarioRunner::run($scenario, $this, $definition);

    expect($scenario->steps())->toHaveCount(1)
        ->and($scenario->steps()[0]->description)->toBe('two numbers');
});

it('records each step type, description, and call site in declaration order', function (): void {
    $scenario = new ScenarioNode('adds two numbers', new SourceLocation('feature.php', 4));
    $expectedLines = [];
    $definition = function () use (&$expectedLines): void {
        $expectedLines[] = __LINE__ + 1;
        given('two numbers', fn () => null);

        $expectedLines[] = __LINE__ + 1;
        when('they are added', fn () => null);

        $expectedLines[] = __LINE__ + 1;
        then('the result is five', fn () => null);
    };

    ScenarioRunner::run($scenario, $this, $definition);

    $steps = $scenario->steps();

    expect(array_map(static fn (StepNode $step): StepType => $step->type, $steps))
        ->toBe([StepType::Given, StepType::When, StepType::Then])
        ->and(array_map(static fn (StepNode $step): string => $step->description, $steps))
        ->toBe(['two numbers', 'they are added', 'the result is five'])
        ->and(array_map(static fn (StepNode $step): array => [$step->source->file, $step->source->line], $steps))
        ->toBe(array_map(static fn (int $line): array => [__FILE__, $line], $expectedLines));
});

it('captures the source call site', function (): void {
    $expectedLine = __LINE__ + 1;
    $source = SourceLocation::capture();

    expect($source->file)->toBe(__FILE__)
        ->and($source->line)->toBe($expectedLine);
});

it('propagates a failing step and marks later steps as skipped', function (): void {
    $scenario = new ScenarioNode('fails on a step', new SourceLocation('feature.php', 4));
    $laterStepRan = false;
    $definition = function () use (&$laterStepRan): void {
        given('a failing step', function (): void {
            throw new RuntimeException('step failed');
        });

        given('a step after the failure', function () use (&$laterStepRan): void {
            $laterStepRan = true;
        });
    };

    expect(fn () => ScenarioRunner::run($scenario, $this, $definition))
        ->toThrow(RuntimeException::class, 'step failed');

    expect($laterStepRan)->toBeFalse()
        ->and($scenario->status)->toBe(ExecutionStatus::Failed)
        ->and($scenario->exception)->toBeInstanceOf(RuntimeException::class)
        ->and($scenario->steps())->toHaveCount(2)
        ->and($scenario->steps()[0]->status)->toBe(ExecutionStatus::Failed)
        ->and($scenario->steps()[0]->exception)->toBeInstanceOf(RuntimeException::class)
        ->and($scenario->steps()[1]->status)->toBe(ExecutionStatus::Skipped);

    expect(fn () => given('outside a scenario', fn () => null))
        ->toThrow(LogicException::class, 'Given steps must be declared inside scenario().');
});
