<?php

declare(strict_types=1);

use function Pest\Flow\given;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

scenario('calculates shipping for an existing Pest suite', function (): void {
    given('a cart with two items', function (): void {
        $this->cart = [
            'itemCount' => 2,
            'subtotal' => 45.00,
        ];
    });

    when('shipping is calculated', function (): void {
        $this->shipping = $this->cart['subtotal'] >= 40.00 ? 0.00 : 6.50;
    });

    then('shipping is free', function (): void {
        expect($this->shipping)->toBe(0.00);
    });
});
