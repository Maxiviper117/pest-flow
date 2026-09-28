<?php

declare(strict_types=1);

use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\FeatureNode;
use Pest\Flow\Model\ScenarioNode;

use function Pest\Flow\feature;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;

feature('Tagged payment behaviours', function (): void {
    rule('Tagged billing rule', function (): void {
        scenario('inherits feature and rule tags', function (): void {
            then('the inherited tags do not change the test result', function (): void {
                expect(true)->toBeTrue();
            });
        });

        scenario('adds a scenario tag', function (): void {
            then('the scenario tag does not change the test result', function (): void {
                expect(true)->toBeTrue();
            });
        })->tags('scenario-only');
    })->tags('rule-shared');
})->tags('feature-shared');

scenario('standalone tagged behaviour', function (): void {
    then('the standalone tag does not change the test result', function (): void {
        expect(true)->toBeTrue();
    });
})->tags('standalone-only');

it('records tags on features, rules, and scenarios', function (): void {
    $feature = array_values(array_filter(
        FlowRegistry::features(),
        static fn (FeatureNode $feature): bool => $feature->name === 'Tagged payment behaviours',
    ))[0];
    $rule = $feature->rules()[0];
    $scenarios = $rule->scenarios();
    $standaloneScenarios = array_values(array_filter(
        FlowRegistry::scenarios(),
        static fn (ScenarioNode $scenario): bool => $scenario->name === 'standalone tagged behaviour',
    ));

    expect($feature->tags())->toBe(['feature-shared'])
        ->and($rule->tags())->toBe(['rule-shared'])
        ->and($scenarios[0]->tags())->toBe([])
        ->and($scenarios[1]->tags())->toBe(['scenario-only'])
        ->and($standaloneScenarios)->toHaveCount(1)
        ->and($standaloneScenarios[0]->tags())->toBe(['standalone-only']);
});
