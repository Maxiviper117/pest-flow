<?php

declare(strict_types=1);

use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\StepNode;

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Contractor activation', function (): void {
    rule('Only compliant contractors may activate', function (): void {
        scenario('Activates a compliant contractor', function (): void {
            given('a compliant contractor', function (): void {
                $this->contractor = [
                    'compliant' => true,
                    'active' => false,
                ];
            });

            when('they activate', function (): void {
                if (! $this->contractor['compliant']) {
                    throw new RuntimeException('Only compliant contractors may activate.');
                }

                $this->contractor['active'] = true;
            });

            then('activation is recorded', function (): void {
                expect($this->contractor['active'])->toBeTrue();

                $feature = FlowRegistry::features()[0];
                $rule = $feature->rules()[0];
                $scenario = $rule->scenarios()[0];

                expect($feature->id)->toBe('contractor-activation')
                    ->and($rule->id)->toBe('contractor-activation/only-compliant-contractors-may-activate')
                    ->and($scenario->id)->toBe('contractor-activation/only-compliant-contractors-may-activate/activates-a-compliant-contractor')
                    ->and(array_map(
                        static fn (StepNode $step): string => $step->id,
                        $scenario->steps(),
                    ))->toBe([
                        'contractor-activation/only-compliant-contractors-may-activate/activates-a-compliant-contractor/given-a-compliant-contractor',
                        'contractor-activation/only-compliant-contractors-may-activate/activates-a-compliant-contractor/when-they-activate',
                        'contractor-activation/only-compliant-contractors-may-activate/activates-a-compliant-contractor/then-activation-is-recorded',
                    ]);
            });
        });
    });
});
