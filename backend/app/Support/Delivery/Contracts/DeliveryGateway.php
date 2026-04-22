<?php

namespace App\Support\Delivery\Contracts;

use App\Models\DeliveryMethod;
use App\Models\DeliveryProvider;

interface DeliveryGateway
{
    public function driver(): string;

    public function resolveFee(DeliveryProvider $provider, DeliveryMethod $method, float $subtotal): float;
}
