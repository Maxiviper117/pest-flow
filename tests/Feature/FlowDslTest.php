<?php

declare(strict_types=1);

use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\RuleNode;
use Pest\Flow\Model\SourceLocation;
use Pest\Flow\Runtime\FlowContext;

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Pest Flow scenario execution', function (): void {
    beforeEach(function (): void {
        $this->trace = ['feature'];
    });

    rule('steps share the Pest test context and run in order', function (): void {
        beforeEach(function (): void {
            $this->trace[] = 'rule';
        });

        scenario('adds two numbers', function (): void {
            given('two numbers', function (): void {
                $this->trace[] = 'given';
                $this->left = 2;
                $this->right = 3;
            });

            when('they are added', function (): void {
                $this->trace[] = 'when';
                $this->result = $this->left + $this->right;
            });

            then('the result is five', function (): void {
                $this->trace[] = 'then';

                expect($this->result)->toBe(5)
                    ->and($this->trace)->toBe(['feature', 'rule', 'given', 'when', 'then']);
            });
        });
    });
});

it('requires a feature before declaring a rule', function (): void {
    expect(fn () => rule('an orphan rule', fn () => null))
        ->toThrow(LogicException::class, 'rule() must be declared inside feature().');
});

it('rejects features nested inside other features', function (): void {
    $feature = new FeatureNode('outer feature', new SourceLocation(__FILE__, __LINE__));

    expect(fn () => FlowContext::withFeature($feature, fn () => feature('inner feature', fn () => null)))
        ->toThrow(LogicException::class, 'Features cannot be nested.');

    expect(FlowContext::currentFeature())->toBeNull();
});

it('rejects rules nested inside other rules', function (): void {
    $feature = new FeatureNode('feature', new SourceLocation(__FILE__, __LINE__));
    $rule = new RuleNode('outer rule', new SourceLocation(__FILE__, __LINE__), $feature);

    expect(fn () => FlowContext::withFeature(
        $feature,
        fn () => FlowContext::withRule($rule, fn () => rule('inner rule', fn () => null)),
    ))->toThrow(LogicException::class, 'Rules cannot be nested.');

    expect(FlowContext::currentFeature())->toBeNull()
        ->and(FlowContext::currentRule())->toBeNull();
});

it('requires a rule before declaring a scenario in a feature', function (): void {
    $feature = new FeatureNode('feature', new SourceLocation(__FILE__, __LINE__));

    expect(fn () => FlowContext::withFeature($feature, fn () => scenario('orphan scenario', fn () => null)))
        ->toThrow(LogicException::class, 'scenario() inside a feature() must be declared inside rule().');

    expect(FlowContext::currentFeature())->toBeNull();
});

it('requires a scenario before declaring each step type', function (): void {
    foreach ([
        'Given' => fn () => given('orphan Given', fn () => null),
        'When' => fn () => when('orphan When', fn () => null),
        'Then' => fn () => then('orphan Then', fn () => null),
    ] as $type => $declaration) {
        expect($declaration)
            ->toThrow(LogicException::class, sprintf('%s steps must be declared inside scenario().', $type));
    }
});
