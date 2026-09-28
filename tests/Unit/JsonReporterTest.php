<?php

declare(strict_types=1);

use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use Pest\Flow\Reporting\JsonReporter;

it('exports the versioned behaviour hierarchy with metadata', function (): void {
    $feature = new FeatureNode('Contractor activation', new SourceLocation('feature.php', 1));
    $rule = new RuleNode('Only compliant contractors may activate', new SourceLocation('feature.php', 3), $feature);
    $scenario = new ScenarioNode('Activate a compliant contractor', new SourceLocation('feature.php', 5), $rule);
    $step = new StepNode(StepType::Given, 'a compliant contractor', new SourceLocation('feature.php', 7));

    $feature->addRule($rule);
    $rule->addScenario($scenario);
    $scenario->addStep($step);

    $scenario->startExecution();
    $step->startExecution();
    $step->markPassed();
    $scenario->markPassed();

    $document = json_decode(
        (new JsonReporter)->render([$feature], []),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $serializedFeature = $document['features'][0];
    $serializedRule = $serializedFeature['rules'][0];
    $serializedScenario = $serializedRule['scenarios'][0];
    $serializedStep = $serializedScenario['steps'][0];

    expect($document['schema_version'])->toBe(1)
        ->and($document['standalone_scenarios'])->toBe([])
        ->and($serializedFeature['name'])->toBe('Contractor activation')
        ->and($serializedFeature['source'])->toBe(['file' => 'feature.php', 'line' => 1])
        ->and($serializedFeature['tags'])->toBe([])
        ->and($serializedRule['name'])->toBe('Only compliant contractors may activate')
        ->and($serializedRule['source'])->toBe(['file' => 'feature.php', 'line' => 3])
        ->and($serializedRule['tags'])->toBe([])
        ->and($serializedScenario['name'])->toBe('Activate a compliant contractor')
        ->and($serializedScenario['status'])->toBe(ExecutionStatus::Passed->value)
        ->and($serializedScenario['duration'])->toBeFloat()
        ->and($serializedScenario['source'])->toBe(['file' => 'feature.php', 'line' => 5])
        ->and($serializedScenario['tags'])->toBe([])
        ->and($serializedStep['type'])->toBe(StepType::Given->value)
        ->and($serializedStep['text'])->toBe('a compliant contractor')
        ->and($serializedStep['status'])->toBe(ExecutionStatus::Passed->value)
        ->and($serializedStep['duration'])->toBeFloat()
        ->and($serializedStep['source'])->toBe(['file' => 'feature.php', 'line' => 7]);
});

it('exports standalone scenarios separately and preserves pending metadata', function (): void {
    $scenario = new ScenarioNode('a standalone scenario', new SourceLocation('standalone.php', 4));

    $document = json_decode(
        (new JsonReporter)->render([], [$scenario]),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($document['features'])->toBe([])
        ->and($document['standalone_scenarios'])->toHaveCount(1)
        ->and($document['standalone_scenarios'][0]['name'])->toBe('a standalone scenario')
        ->and($document['standalone_scenarios'][0]['status'])->toBe(ExecutionStatus::Pending->value)
        ->and($document['standalone_scenarios'][0]['duration'])->toBeNull()
        ->and($document['standalone_scenarios'][0]['source'])->toBe(['file' => 'standalone.php', 'line' => 4]);
});
