<?php

namespace App\Support\Payments;

use App\Models\PaymentProvider;
use App\Support\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

class PaymentGatewayRegistry
{
    /**
     * @param  iterable<PaymentGateway>  $gateways
     */
    public function __construct(private iterable $gateways)
    {
    }

    public function for(PaymentProvider $provider): PaymentGateway
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->driver() === $provider->driver) {
                return $gateway;
            }
        }

        throw new InvalidArgumentException("Unsupported payment gateway [{$provider->driver()}].");
    }
}
