<?php

declare(strict_types=1);

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Invoice approval', function (): void {
    rule('Invoices with a purchase order can be approved', function (): void {
        scenario('approves an invoice with a purchase order', function (): void {
            given('a submitted invoice with a purchase order', function (): void {
                $this->invoice = [
                    'status' => 'submitted',
                    'purchaseOrder' => 'PO-1042',
                ];
            });

            when('the invoice is reviewed', function (): void {
                $this->invoice['status'] = 'approved';
            });

            then('the invoice is approved', function (): void {
                expect($this->invoice)->toMatchArray([
                    'status' => 'approved',
                    'purchaseOrder' => 'PO-1042',
                ]);
            });
        });
    });

    rule('Invoices without a purchase order need correction', function (): void {
        scenario('holds an invoice without a purchase order', function (): void {
            given('a submitted invoice without a purchase order', function (): void {
                $this->invoice = [
                    'status' => 'submitted',
                    'purchaseOrder' => null,
                ];
            });

            when('the invoice is reviewed', function (): void {
                $this->invoice['status'] = $this->invoice['purchaseOrder'] === null
                    ? 'needs-information'
                    : 'approved';
            });

            then('the invoice is held for correction', function (): void {
                expect($this->invoice['status'])->toBe('needs-information');
            });
        });
    });
});
