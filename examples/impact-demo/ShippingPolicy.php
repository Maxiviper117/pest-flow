<?php

declare(strict_types=1);

namespace Pest\Flow\Examples\ImpactDemo;

final class ShippingPolicy
{
    public function canShip(bool $isPaid, int $availableItems): bool
    {
        return $isPaid && $availableItems > 0;
    }
}
