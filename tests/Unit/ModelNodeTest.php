<?php

declare(strict_types=1);

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use Pest\Flow\Runtime\ScenarioRunner;

use function Pest\Flow\given;

it('builds a feature rule scenario and step hierarchy', function (): void {
    $feature = new FeatureNode('Contractor activation', new SourceLocation('feature.php', 10));
    $rule = new RuleNode('Only compliant contractors may activate', new SourceLocation('feature.php', 12), $feature);
    $scenario = new ScenarioNode('Activate a compliant contractor', new SourceLocation('feature.php', 14), $rule);
    $step = new StepNode(StepType::Given, 'a compliant contractor', new SourceLocation('feature.php', 16));

    $feature->addRule($rule);
    $rule->addScenario($scenario);
    $scenario->addStep($step);

    expect($feature->rules())->toBe([$rule])
        ->and($rule->scenarios())->toBe([$scenario])
        ->and($scenario->steps())->toBe([$step])
        ->and($rule->feature)->toBe($feature)
        ->and($scenario->rule)->toBe($rule)
        ->and($step->type)->toBe(StepType::Given)
        ->and($step->source->file)->toBe('feature.php')
        ->and($step->source->line)->toBe(16);
});

it('can clear steps before a scenario execution', function (): void {
    $scenario = new ScenarioNode('adds two numbers', new SourceLocation('feature.php', 4));
    $scenario->addStep(new StepNode(StepType::Given, 'two numbers', new SourceLocation('feature.php', 6)));

    $scenario->clearSteps();

    expect($scenario->steps())->toBe([]);
});

it('captures the source call site', function (): void {
    $expectedLine = __LINE__ + 1;
    $source = SourceLocation::capture();

    expect($source->file)->toBe(__FILE__)
        ->and($source->line)->toBe($expectedLine);
});

it('propagates a failing step and skips later steps', function (): void {
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
        ->and($scenario->steps())->toHaveCount(1);
});
