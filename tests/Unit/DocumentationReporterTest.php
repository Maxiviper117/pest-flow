<?php

declare(strict_types=1);

use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Model\StepNode;
use Pest\Flow\Model\StepType;
use Pest\Flow\Reporting\DocumentationReporter;

it('renders the behaviour hierarchy, metadata, counts, and safe text', function (): void {
    $feature = new FeatureNode('Checkout <Risk>', new SourceLocation('tests/Checkout.php', 4));
    $rule = new RuleNode('Payments & refunds', new SourceLocation('tests/Checkout.php', 8), $feature);
    $scenario = new ScenarioNode('Charges a card', new SourceLocation('tests/Checkout.php', 10), $rule);
    $step = new StepNode(StepType::Given, 'a card named <script>', new SourceLocation('tests/Checkout.php', 12));

    $feature->addTags('billing');
    $rule->addTags('critical');
    $scenario->addTags('cards');
    $feature->addRule($rule);
    $rule->addScenario($scenario);
    $scenario->addStep($step);
    $scenario->startExecution();
    $step->startExecution();
    $step->markPassed();
    $scenario->markPassed();

    $html = (new DocumentationReporter)->render([$feature], []);

    expect($html)->toContain('<!doctype html>')
        ->and($html)->toContain('Checkout &lt;Risk&gt;')
        ->and($html)->toContain('Payments &amp; refunds')
        ->and($html)->toContain('a card named &lt;script&gt;')
        ->and($html)->not->toContain('a card named <script>')
        ->and($html)->toContain('href="#checkout-risk/payments-refunds/charges-a-card"')
        ->and($html)->toContain('billing')
        ->and($html)->toContain('critical')
        ->and($html)->toContain('cards')
        ->and($html)->toContain('tests/Checkout.php:12')
        ->and($html)->toContain('<dt>Features</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Rules</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Scenarios</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Recorded steps</dt><dd>1</dd>')
        ->and($html)->toContain('<strong>Passed</strong>');
});

it('renders standalone scenarios without a synthetic feature or rule', function (): void {
    $scenario = new ScenarioNode('A top-level scenario', new SourceLocation('tests/Standalone.php', 5));
    $scenario->addTags('quick-check');
    $scenario->addStep(new StepNode(StepType::Then, 'it is visible', new SourceLocation('tests/Standalone.php', 7)));

    $html = (new DocumentationReporter)->render([], [$scenario]);

    expect($html)->toContain('Standalone scenarios')
        ->and($html)->toContain('A top-level scenario')
        ->and($html)->toContain('quick-check')
        ->and($html)->toContain('tests/Standalone.php:7')
        ->and($html)->not->toContain('>Feature</p>')
        ->and($html)->not->toContain('>Rule</p>');
});

it('counts every scenario status and shows pending scenarios without recorded steps', function (): void {
    $passed = new ScenarioNode('passed', new SourceLocation('tests/Status.php', 1));
    $passed->startExecution();
    $passed->markPassed();

    $failed = new ScenarioNode('failed', new SourceLocation('tests/Status.php', 2));
    $failed->startExecution();
    $failed->markFailed(new RuntimeException('Expected failure'));

    $skipped = new ScenarioNode('skipped', new SourceLocation('tests/Status.php', 3));
    $skipped->startExecution();
    $skipped->markSkipped();

    $running = new ScenarioNode('running', new SourceLocation('tests/Status.php', 4));
    $running->startExecution();

    $pending = new ScenarioNode('pending', new SourceLocation('tests/Status.php', 5));

    $html = (new DocumentationReporter)->render([], [$passed, $failed, $skipped, $running, $pending]);

    expect($html)->toContain('<dt>Passed</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Failed</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Skipped</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Running</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Pending</dt><dd>1</dd>')
        ->and($html)->toContain('No steps were recorded in this run.')
        ->and($html)->toContain(ExecutionStatus::Pending->value);
});
