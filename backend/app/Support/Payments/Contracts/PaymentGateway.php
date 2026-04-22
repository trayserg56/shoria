<?php

namespace App\Support\Payments\Contracts;

use App\Models\Order;
use App\Models\PaymentProvider;

interface PaymentGateway
{
    public function driver(): string;

    public function toCheckoutOption(PaymentProvider $provider): array;

    public function initialPaymentStatus(PaymentProvider $provider): string;

    public function initialTransactionStatus(PaymentProvider $provider): string;

    public function buildTransactionMeta(Order $order, PaymentProvider $provider): array;
}
