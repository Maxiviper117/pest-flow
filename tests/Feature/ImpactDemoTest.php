<?php

declare(strict_types=1);

use Pest\Flow\Examples\ImpactDemo\ShippingPolicy;

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

require_once __DIR__.'/../../examples/impact-demo/ShippingPolicy.php';

feature('Order shipping', function (): void {
    rule('only paid orders with available stock can ship', function (): void {
        scenario('ships a paid order with available stock', function (): void {
            given('a paid order with one item available', function (): void {
                $this->isPaid = true;
                $this->availableItems = 1;
            });

            when('shipping eligibility is checked', function (): void {
                $this->canShip = (new ShippingPolicy)->canShip($this->isPaid, $this->availableItems);
            });

            then('the order is eligible to ship', function (): void {
                expect($this->canShip)->toBeTrue();
            });
        });

        scenario('holds an unpaid order', function (): void {
            given('an unpaid order with stock', function (): void {
                $this->isPaid = false;
                $this->availableItems = 3;
            });

            when('shipping eligibility is checked', function (): void {
                $this->canShip = (new ShippingPolicy)->canShip($this->isPaid, $this->availableItems);
            });

            then('the order is not eligible to ship', function (): void {
                expect($this->canShip)->toBeFalse();
            });
        });
    });
});
