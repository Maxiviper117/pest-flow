<?php

declare(strict_types=1);

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

it('requires a scenario before declaring a step', function (): void {
    expect(fn () => given('an orphan step', fn () => null))
        ->toThrow(LogicException::class, 'Given steps must be declared inside scenario().');
});
