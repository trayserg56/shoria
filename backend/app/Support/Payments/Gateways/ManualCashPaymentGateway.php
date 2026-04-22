<?php

namespace App\Support\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Support\Payments\Contracts\PaymentGateway;

class ManualCashPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'manual_cash';
    }

    public function toCheckoutOption(PaymentProvider $provider): array
    {
        return [
            'code' => $provider->code,
            'name' => $provider->checkout_label ?: $provider->name,
            'driver' => $provider->driver,
            'mode' => $provider->mode,
            'is_test_mode' => false,
        ];
    }

    public function initialPaymentStatus(PaymentProvider $provider): string
    {
        return 'unpaid';
    }

    public function initialTransactionStatus(PaymentProvider $provider): string
    {
        return 'created';
    }

    public function buildTransactionMeta(Order $order, PaymentProvider $provider): array
    {
        return [
            'mode' => $provider->mode,
            'gateway' => 'manual',
            'instruction' => 'pay_on_delivery',
        ];
    }
}
