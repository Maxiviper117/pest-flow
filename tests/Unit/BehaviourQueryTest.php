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
use Pest\Flow\Reporting\AgentListReporter;

$createBehaviourTree = static function (): array {
    $payments = new FeatureNode('Payments', new SourceLocation('tests/Feature/CheckoutTest.php', 10));
    $payments->addTags('billing');
    $authorization = new RuleNode('Cards need authorization', new SourceLocation('tests/Feature/CheckoutTest.php', 12), $payments);
    $authorization->addTags('critical');

    $accepted = new ScenarioNode('Accepts valid cards', new SourceLocation('tests/Feature/CheckoutTest.php', 15), $authorization);
    $accepted->addTags('checkout');
    $accepted->addStep(new StepNode(StepType::Given, 'a customer with a valid card', new SourceLocation('tests/Feature/CheckoutTest.php', 17)));
    $accepted->addStep(new StepNode(StepType::Then, 'the card is charged', new SourceLocation('tests/Feature/CheckoutTest.php', 19)));

    $expired = new ScenarioNode('Rejects expired cards', new SourceLocation('tests/Feature/CheckoutTest.php', 23), $authorization);
    $expired->addTags('expired');
    $expired->addStep(new StepNode(StepType::Given, 'an expired card', new SourceLocation('tests/Feature/CheckoutTest.php', 25)));
    $expired->addStep(new StepNode(StepType::Then, 'the expired card is declined', new SourceLocation('tests/Feature/CheckoutTest.php', 27)));
    $expired->startExecution();
    $expired->markFailed(new RuntimeException('Decline was not returned.'));

    $authorization->addScenario($accepted);
    $authorization->addScenario($expired);
    $payments->addRule($authorization);

    $refunds = new FeatureNode('Refunds', new SourceLocation('tests/Feature/RefundTest.php', 5));
    $refundRule = new RuleNode('Customers can request refunds', new SourceLocation('tests/Feature/RefundTest.php', 7), $refunds);
    $refundScenario = new ScenarioNode('Approves eligible refunds', new SourceLocation('tests/Feature/RefundTest.php', 9), $refundRule);
    $refundRule->addScenario($refundScenario);
    $refunds->addRule($refundRule);

    $standalone = new ScenarioNode('Checks service status', new SourceLocation('tests/Feature/HealthTest.php', 3));
    $standalone->addTags('operations');
    $standalone->addStep(new StepNode(StepType::Then, 'the service is healthy', new SourceLocation('tests/Feature/HealthTest.php', 5)));

    return [$payments, $refunds, $standalone];
};

it('searches step metadata while preserving the feature and rule context', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();
    $query = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'EXPIRED CARD IS DECLINED', searchIn: ['step']);

    expect($query->features())->toHaveCount(1)
        ->and($query->features()[0]->name)->toBe('Payments')
        ->and($query->rulesFor($query->features()[0]))->toHaveCount(1)
        ->and($query->scenariosFor($query->rulesFor($query->features()[0])[0]))->toHaveCount(1)
        ->and($query->scenariosFor($query->rulesFor($query->features()[0])[0])[0]->name)->toBe('Rejects expired cards')
        ->and($query->stepsFor($query->scenariosFor($query->rulesFor($query->features()[0])[0])[0]))->toHaveCount(2);
});

it('scopes searches to the requested fields', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();
    $tagOnly = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'billing', searchIn: ['tag']);
    $nameOnly = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'billing', searchIn: ['name']);
    $stepOnly = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'expired card', searchIn: ['step']);

    expect($tagOnly->features())->toHaveCount(1)
        ->and($tagOnly->scenariosFor($tagOnly->rulesFor($tagOnly->features()[0])[0]))->toHaveCount(2)
        ->and($nameOnly->hasResults())->toBeFalse()
        ->and($stepOnly->features())->toHaveCount(1)
        ->and($stepOnly->scenariosFor($stepOnly->rulesFor($stepOnly->features()[0])[0]))->toHaveCount(1);
});

it('matches inherited feature and rule tags as well as direct scenario tags', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();

    $billingQuery = new BehaviourQuery([$payments, $refunds], [$standalone], tag: 'BILLING');
    $scenarioTagQuery = new BehaviourQuery([$payments, $refunds], [$standalone], tag: 'checkout');

    expect($billingQuery->features())->toHaveCount(1)
        ->and($billingQuery->scenariosFor($billingQuery->rulesFor($billingQuery->features()[0])[0]))->toHaveCount(2)
        ->and($scenarioTagQuery->features())->toHaveCount(1)
        ->and($scenarioTagQuery->scenariosFor($scenarioTagQuery->rulesFor($scenarioTagQuery->features()[0])[0]))->toHaveCount(1);
});

it('combines feature, rule, status, and source filters', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();
    $query = new BehaviourQuery(
        [$payments, $refunds],
        [$standalone],
        feature: 'pay',
        rule: 'authorization',
        status: ExecutionStatus::Failed->value,
        source: 'CheckoutTest.php',
    );

    expect($query->features())->toHaveCount(1)
        ->and($query->features()[0]->name)->toBe('Payments')
        ->and($query->scenariosFor($query->rulesFor($query->features()[0])[0]))->toHaveCount(1)
        ->and($query->scenariosFor($query->rulesFor($query->features()[0])[0])[0]->status)->toBe(ExecutionStatus::Failed);
});

it('keeps standalone scenarios and returns a valid empty selection', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();
    $standaloneQuery = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'service status');
    $emptyQuery = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'unknown behaviour');

    expect($standaloneQuery->features())->toBe([])
        ->and($standaloneQuery->standaloneScenarios())->toHaveCount(1)
        ->and($emptyQuery->hasResults())->toBeFalse()
        ->and($emptyQuery->features())->toBe([])
        ->and($emptyQuery->standaloneScenarios())->toBe([]);
});

it('renders compact hierarchy, execution state, and source locations', function () use ($createBehaviourTree): void {
    [$payments, $refunds, $standalone] = $createBehaviourTree();
    $query = new BehaviourQuery([$payments, $refunds], [$standalone], search: 'expired card');
    $output = (new AgentListReporter)->render($query);

    expect($output)->toContain('Feature: Payments')
        ->and($output)->toContain('Feature: Payments [tags: billing]')
        ->and($output)->toContain('  Rule: Cards need authorization [tags: critical]')
        ->and($output)->toContain('Scenario [failed]: Rejects expired cards [tags: expired] (tests/Feature/CheckoutTest.php:23)')
        ->and($output)->toContain('Then [pending]: the expired card is declined (tests/Feature/CheckoutTest.php:27)');
});

it('rejects empty search and unsupported status values', function (): void {
    expect(fn () => new BehaviourQuery([], [], search: '  '))
        ->toThrow(InvalidArgumentException::class, 'Search text cannot be empty.')
        ->and(fn () => new BehaviourQuery([], [], status: 'broken'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported status "broken"')
        ->and(fn () => new BehaviourQuery([], [], search: 'card', searchIn: ['step-description']))
        ->toThrow(InvalidArgumentException::class, 'Unsupported search field "step-description"');
});
