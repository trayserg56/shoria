<?php

namespace App\Support\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Support\Payments\Contracts\PaymentGateway;

class SberPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'sber';
    }

    public function toCheckoutOption(PaymentProvider $provider): array
    {
        return [
            'code' => $provider->code,
            'name' => $provider->checkout_label ?: $provider->name,
            'driver' => $provider->driver,
            'mode' => $provider->mode,
            'is_test_mode' => $provider->mode === 'sandbox',
        ];
    }

    public function initialPaymentStatus(PaymentProvider $provider): string
    {
        return 'pending';
    }

    public function initialTransactionStatus(PaymentProvider $provider): string
    {
        return 'pending';
    }

    public function buildTransactionMeta(Order $order, PaymentProvider $provider): array
    {
        return [
            'mode' => $provider->mode,
            'gateway' => 'Sber',
            'merchant' => data_get($provider->config, 'merchant_login'),
        ];
    }
}
