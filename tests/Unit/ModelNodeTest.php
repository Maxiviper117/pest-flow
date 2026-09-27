<?php

declare(strict_types=1);

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;

it('builds a feature rule scenario and step hierarchy', function (): void {
    $feature = new FeatureNode('Contractor activation', new SourceLocation('feature.php', 10));
    $rule = new RuleNode('Only compliant contractors may activate', new SourceLocation('feature.php', 12));
    $scenario = new ScenarioNode('Activate a compliant contractor', new SourceLocation('feature.php', 14));
    $step = new StepNode(StepType::Given, 'a compliant contractor', new SourceLocation('feature.php', 16));

    $feature->addRule($rule);
    $rule->addScenario($scenario);
    $scenario->addStep($step);

    expect($feature->rules())->toBe([$rule])
        ->and($rule->scenarios())->toBe([$scenario])
        ->and($scenario->steps())->toBe([$step])
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
