<?php

namespace App\Support\Delivery;

use App\Models\DeliveryProvider;
use App\Support\Delivery\Contracts\DeliveryGateway;
use InvalidArgumentException;

class DeliveryGatewayRegistry
{
    /**
     * @param  iterable<DeliveryGateway>  $gateways
     */
    public function __construct(private iterable $gateways)
    {
    }

    public function for(DeliveryProvider $provider): DeliveryGateway
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->driver() === $provider->driver) {
                return $gateway;
            }
        }

        throw new InvalidArgumentException("Unsupported delivery gateway [{$provider->driver()}].");
    }
}
