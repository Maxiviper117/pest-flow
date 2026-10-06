<?php

declare(strict_types=1);

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
        ->and($html)->toContain('href="#scenario-checkout-risk/payments-refunds/charges-a-card"')
        ->and($html)->toContain('billing')
        ->and($html)->toContain('critical')
        ->and($html)->toContain('cards')
        ->and(substr_count($html, '<li>billing</li>'))->toBe(1)
        ->and(substr_count($html, '<li>critical</li>'))->toBe(1)
        ->and(substr_count($html, '<li>cards</li>'))->toBe(1)
        ->and($html)->toContain('tests/Checkout.php:4')
        ->and($html)->toContain('tests/Checkout.php:8')
        ->and($html)->toContain('tests/Checkout.php:10')
        ->and($html)->toContain('tests/Checkout.php:12')
        ->and($html)->toContain('<dt>Features</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Rules</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Scenarios</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Recorded steps</dt><dd>1</dd>')
        ->and($html)->toContain('<span class="status-label">Passed</span>')
        ->and($html)->toContain('class="steps flow"')
        ->and($html)->toContain('data-copy="tests/Checkout.php:12"')
        ->and($html)->toContain('id="filter-search"')
        ->and($html)->toContain('id="filter-status"')
        ->and($html)->toContain('id="filter-tag"')
        ->and($html)->toContain('id="filter-feature"')
        ->and($html)->toContain('id="filter-rule"')
        ->and($html)->toContain('id="filter-source"')
        ->and($html)->toContain('<span class="step-type">Given</span>');
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
        ->and($html)->toContain('<section class="standalone-group"')
        ->and($html)->not->toContain('class="feature"')
        ->and($html)->not->toContain('class="rule"');
});

it('counts every scenario status and shows pending scenarios without recorded steps', function (): void {
    $passed = new ScenarioNode('passed', new SourceLocation('tests/Status.php', 1));
    $passedStep = new StepNode(StepType::Given, 'the passing setup', new SourceLocation('tests/Status.php', 11));
    $passed->addStep($passedStep);
    $passed->startExecution();
    $passedStep->startExecution();
    $passedStep->markPassed();
    $passed->markPassed();

    $failed = new ScenarioNode('failed', new SourceLocation('tests/Status.php', 2));
    $failedStep = new StepNode(StepType::When, 'the failing action', new SourceLocation('tests/Status.php', 12));
    $failed->addStep($failedStep);
    $failed->startExecution();
    $failedStep->startExecution();
    $failedStep->markFailed(new RuntimeException('Expected failure'));
    $failed->markFailed(new RuntimeException('Expected failure'));

    $skipped = new ScenarioNode('skipped', new SourceLocation('tests/Status.php', 3));
    $skippedStep = new StepNode(StepType::Then, 'the skipped outcome', new SourceLocation('tests/Status.php', 13));
    $skipped->addStep($skippedStep);
    $skipped->startExecution();
    $skippedStep->startExecution();
    $skippedStep->markSkipped();
    $skipped->markSkipped();

    $running = new ScenarioNode('running', new SourceLocation('tests/Status.php', 4));
    $runningStep = new StepNode(StepType::Given, 'the active setup', new SourceLocation('tests/Status.php', 14));
    $running->addStep($runningStep);
    $running->startExecution();
    $runningStep->startExecution();

    $pending = new ScenarioNode('pending', new SourceLocation('tests/Status.php', 5));

    $html = (new DocumentationReporter)->render([], [$passed, $failed, $skipped, $running, $pending]);
    $scenarioHtml = static function (ScenarioNode $scenario) use ($html): string {
        $pattern = '~<article\\b[^>]*\\bid="'.preg_quote('standalone-scenario-'.$scenario->id, '~').'"[^>]*>.*?</article>~s';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return '';
        }

        return $matches[0];
    };

    expect($html)->toContain('<dt>Passed</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Failed</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Skipped</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Running</dt><dd>1</dd>')
        ->and($html)->toContain('<dt>Pending</dt><dd>1</dd>')
        ->and($html)->toContain('No steps were recorded in this run.')
        ->and($scenarioHtml($passed))->toContain('<span class="status-label">Passed</span>')
        ->and($scenarioHtml($failed))->toContain('<span class="status-label">Failed</span>')
        ->and($scenarioHtml($skipped))->toContain('<span class="status-label">Skipped</span>')
        ->and($scenarioHtml($running))->toContain('<span class="status-label">Running</span>');
});

it('counts definitions across features and standalone scenarios and counts only recorded steps', function (): void {
    $recordStep = static function (ScenarioNode $scenario, StepType $type, int $line): void {
        $step = new StepNode($type, 'recorded step', new SourceLocation('tests/Counts.php', $line));
        $scenario->addStep($step);
        $scenario->startExecution();
        $step->startExecution();
        $step->markPassed();
        $scenario->markPassed();
    };

    $firstFeature = new FeatureNode('Checkout', new SourceLocation('tests/Counts.php', 1));
    $firstRule = new RuleNode('Card payments', new SourceLocation('tests/Counts.php', 2), $firstFeature);
    $secondRule = new RuleNode('Refunds', new SourceLocation('tests/Counts.php', 3), $firstFeature);
    $firstFeature->addRule($firstRule);
    $firstFeature->addRule($secondRule);

    $firstScenario = new ScenarioNode('accepts a card', new SourceLocation('tests/Counts.php', 4), $firstRule);
    $firstRule->addScenario($firstScenario);
    $recordStep($firstScenario, StepType::Given, 5);

    $secondScenario = new ScenarioNode('declines an expired card', new SourceLocation('tests/Counts.php', 6), $firstRule);
    $firstRule->addScenario($secondScenario);

    $thirdScenario = new ScenarioNode('refunds a payment', new SourceLocation('tests/Counts.php', 8), $secondRule);
    $secondRule->addScenario($thirdScenario);
    $recordStep($thirdScenario, StepType::Then, 9);

    $secondFeature = new FeatureNode('Accounts', new SourceLocation('tests/Counts.php', 10));
    $thirdRule = new RuleNode('Account access', new SourceLocation('tests/Counts.php', 11), $secondFeature);
    $secondFeature->addRule($thirdRule);
    $fourthScenario = new ScenarioNode('locks an account', new SourceLocation('tests/Counts.php', 12), $thirdRule);
    $thirdRule->addScenario($fourthScenario);
    $recordStep($fourthScenario, StepType::When, 14);

    $standalone = new ScenarioNode('reports a health check', new SourceLocation('tests/Counts.php', 13));

    $html = (new DocumentationReporter)->render([$firstFeature, $secondFeature], [$standalone]);

    expect($html)->toContain('<dt>Features</dt><dd>2</dd>')
        ->and($html)->toContain('<dt>Rules</dt><dd>3</dd>')
        ->and($html)->toContain('<dt>Scenarios</dt><dd>5</dd>')
        ->and($html)->toContain('<dt>Recorded steps</dt><dd>3</dd>')
        ->and($html)->toContain('<dt>Passed</dt><dd>3</dd>')
        ->and($html)->toContain('<dt>Pending</dt><dd>2</dd>');
});

it('embeds the versioned JSON document safely for a local interactive viewer', function (): void {
    $feature = new FeatureNode('Checkout', new SourceLocation('tests/Checkout.php', 1));
    $rule = new RuleNode('Payments', new SourceLocation('tests/Checkout.php', 3), $feature);
    $scenario = new ScenarioNode('Reject unsafe card text', new SourceLocation('tests/Checkout.php', 5), $rule);
    $step = new StepNode(StepType::When, '</script><script>alert(1)</script>', new SourceLocation('tests/Checkout.php', 7));

    $feature->addTags('billing');
    $rule->addTags('critical');
    $feature->addRule($rule);
    $rule->addScenario($scenario);
    $scenario->addStep($step);

    $html = (new DocumentationReporter)->render([$feature], []);
    preg_match('~<script id="flow-document" type="application/json">(.*?)</script>~s', $html, $matches);
    $document = json_decode($matches[1] ?? '', true, 512, JSON_THROW_ON_ERROR);

    expect($document['schema_version'])->toBe(1)
        ->and($document['features'][0]['tags'])->toBe(['billing'])
        ->and($document['features'][0]['rules'][0]['tags'])->toBe(['critical'])
        ->and($document['features'][0]['rules'][0]['scenarios'][0]['steps'][0]['text'])
        ->toBe('</script><script>alert(1)</script>')
        ->and($html)->not->toContain('</script><script>alert(1)</script>')
        ->and($html)->toContain('Scenario status: 1 pending, 0 running, 0 passed, 0 failed, 0 skipped')
        ->and($html)->toContain('JSON.parse(documentElement.textContent)')
        ->and($html)->toContain('metadata.tags.includes(filters.tag)')
        ->and($html)->toContain('metadata.statuses.includes(filters.status)')
        ->and($html)->toContain('metadata.sourceFiles.includes(filters.source)')
        ->and($html)->toContain("document.querySelector('nav').addEventListener('click'")
        ->and($html)->toContain('Copy location')
        ->and($html)->toContain('Unsupported schema version.');
});
