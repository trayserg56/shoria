<?php

namespace App\Support\Delivery\Gateways;

use App\Models\DeliveryMethod;
use App\Models\DeliveryProvider;
use App\Support\Delivery\Contracts\DeliveryGateway;

class ManualDeliveryGateway implements DeliveryGateway
{
    public function driver(): string
    {
        return 'manual';
    }

    public function resolveFee(DeliveryProvider $provider, DeliveryMethod $method, float $subtotal): float
    {
        return (float) $method->fee;
    }
}
