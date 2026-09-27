<?php

declare(strict_types=1);

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Support ticket assignment', function (): void {
    beforeEach(function (): void {
        $this->audit = ['feature setup'];
    });

    rule('New tickets are assigned to the on-call agent', function (): void {
        beforeEach(function (): void {
            $this->onCallAgent = 'agent-17';
            $this->audit[] = 'rule setup';
        });

        scenario('assigns a new ticket to the on-call agent', function (): void {
            given('a new support ticket', function (): void {
                $this->ticket = [
                    'status' => 'new',
                    'assignee' => null,
                ];
            });

            when('the ticket is triaged', function (): void {
                $this->ticket['assignee'] = $this->onCallAgent;
                $this->ticket['status'] = 'assigned';
            });

            then('the ticket is assigned and the group setup ran', function (): void {
                expect($this->ticket)->toMatchArray([
                    'status' => 'assigned',
                    'assignee' => 'agent-17',
                ])->and($this->audit)->toBe(['feature setup', 'rule setup']);
            });
        });
    });
});
