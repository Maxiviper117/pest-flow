<?php

declare(strict_types=1);

use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use Pest\Flow\Query\BehaviourQuery;
use Pest\Flow\Reporting\JsonReporter;

it('uses two-space indentation', function (): void {
    $feature = new FeatureNode('example', new SourceLocation('feature.php', 1));
    $json = (new JsonReporter)->render([$feature], []);

    expect($json)->toContain("  \"features\": [\n    {\n      \"id\": \"example\",");
});

it('exports the versioned behaviour hierarchy with metadata', function (): void {
    $feature = new FeatureNode('Contractor activation', new SourceLocation('feature.php', 1));
    $rule = new RuleNode('Only compliant contractors may activate', new SourceLocation('feature.php', 3), $feature);
    $scenario = new ScenarioNode('Activate a compliant contractor', new SourceLocation('feature.php', 5), $rule);
    $step = new StepNode(StepType::Given, 'a compliant contractor', new SourceLocation('feature.php', 7));

    $feature->addTags('payments');
    $rule->addTags('critical');
    $scenario->addTags('urgent');

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
        ->and($serializedFeature['tags'])->toBe(['payments'])
        ->and($serializedRule['name'])->toBe('Only compliant contractors may activate')
        ->and($serializedRule['source'])->toBe(['file' => 'feature.php', 'line' => 3])
        ->and($serializedRule['tags'])->toBe(['critical'])
        ->and($serializedScenario['name'])->toBe('Activate a compliant contractor')
        ->and($serializedScenario['status'])->toBe(ExecutionStatus::Passed->value)
        ->and($serializedScenario['duration'])->toBeFloat()
        ->and($serializedScenario['source'])->toBe(['file' => 'feature.php', 'line' => 5])
        ->and($serializedScenario['tags'])->toBe(['urgent'])
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

it('keeps the versioned JSON schema when exporting a filtered tree', function (): void {
    $feature = new FeatureNode('Checkout', new SourceLocation('checkout.php', 1));
    $rule = new RuleNode('Card payments', new SourceLocation('checkout.php', 3), $feature);
    $matching = new ScenarioNode('accepts a valid card', new SourceLocation('checkout.php', 5), $rule);
    $other = new ScenarioNode('rejects an expired card', new SourceLocation('checkout.php', 9), $rule);
    $matching->addStep(new StepNode(StepType::Then, 'the payment is accepted', new SourceLocation('checkout.php', 7)));
    $other->addStep(new StepNode(StepType::Then, 'the payment is declined', new SourceLocation('checkout.php', 11)));
    $rule->addScenario($matching);
    $rule->addScenario($other);
    $feature->addRule($rule);
    $query = new BehaviourQuery([$feature], [], search: 'accepted', searchIn: ['step']);

    $document = json_decode(
        (new JsonReporter)->render([$feature], [], $query),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($document['schema_version'])->toBe(1)
        ->and($document['features'][0]['name'])->toBe('Checkout')
        ->and($document['features'][0]['rules'][0]['scenarios'])->toHaveCount(1)
        ->and($document['features'][0]['rules'][0]['scenarios'][0]['name'])->toBe('accepts a valid card')
        ->and($document['features'][0]['rules'][0]['scenarios'][0]['steps'][0]['text'])->toBe('the payment is accepted')
        ->and($document['standalone_scenarios'])->toBe([]);
});
