<?php

declare(strict_types=1);

use Pest\Flow\FlowRegistry;
use Pest\Flow\Model\ExecutionStatus;
use Pest\Flow\Model\ScenarioNode;
use Pest\Flow\Model\StepNode;

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Inventory reservation', function (): void {
    rule('Only available stock can be reserved', function (): void {
        scenario('reserves available stock', function (): void {
            given('four units are available', function (): void {
                $this->available = 4;
                $this->requested = 3;
            });

            when('the order is reserved', function (): void {
                $this->reserved = min($this->available, $this->requested);
                $this->available -= $this->reserved;
            });

            then('the requested units are reserved', function (): void {
                expect($this->reserved)->toBe(3)
                    ->and($this->available)->toBe(1);
            });
        });
    });

    afterEach(function (): void {
        $scenario = null;

        foreach (FlowRegistry::scenarios() as $registeredScenario) {
            if ($registeredScenario->name === 'reserves available stock') {
                $scenario = $registeredScenario;
                break;
            }
        }

        if (! $scenario instanceof ScenarioNode) {
            throw new LogicException('The inventory reservation scenario was not registered.');
        }

        expect($scenario->status)->toBe(ExecutionStatus::Passed);
        expect($scenario->duration)->not->toBeNull();
        expect($scenario->exception)->toBeNull();
        expect($scenario->steps())->toHaveCount(3);

        foreach ($scenario->steps() as $step) {
            expect($step->status)->toBe(ExecutionStatus::Passed);
            expect($step->duration)->not->toBeNull();
            expect($step->exception)->toBeNull();
            expect($step->source->file)->toBe(__FILE__);
        }

        $report = [
            'id' => $scenario->id,
            'status' => $scenario->status->value,
            'durationSeconds' => $scenario->duration,
            'steps' => array_map(
                static fn (StepNode $step): array => [
                    'type' => $step->type->value,
                    'description' => $step->description,
                    'status' => $step->status->value,
                    'durationSeconds' => $step->duration,
                    'source' => [
                        'file' => basename($step->source->file),
                        'line' => $step->source->line,
                    ],
                ],
                $scenario->steps(),
            ),
        ];

        expect($report)->toMatchArray([
            'id' => $scenario->id,
            'status' => 'passed',
        ]);
        expect($report['steps'])->toHaveCount(3);
    });
});
