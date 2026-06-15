<?php

namespace App\Support\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Support\Payments\Contracts\PaymentGateway;

class RobokassaPaymentGateway implements PaymentGateway
{
    private const BASE_URL = 'https://auth.robokassa.ru/Merchant/Index.aspx';

    public function driver(): string
    {
        return 'robokassa';
    }

    public function toCheckoutOption(PaymentProvider $provider): array
    {
        return [
            'code'        => $provider->code,
            'name'        => $provider->checkout_label ?: $provider->name,
            'driver'      => $provider->driver,
            'mode'        => $provider->mode,
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
        $merchantLogin = data_get($provider->config, 'merchant_login', config('services.robokassa.merchant_login'));
        $password1     = data_get($provider->config, 'password1', config('services.robokassa.password1'));
        $isTest        = $provider->mode === 'sandbox' ? 1 : 0;

        $outSum = number_format((float) $order->total, 2, '.', '');
        $invId  = $order->id;

        $signature = strtoupper(hash('sha256', "{$merchantLogin}:{$outSum}:{$invId}:{$password1}"));

        $params = http_build_query([
            'MerchantLogin'  => $merchantLogin,
            'OutSum'         => $outSum,
            'InvId'          => $invId,
            'Description'    => "Заказ №{$order->order_number}",
            'SignatureValue'  => $signature,
            'IsTest'         => $isTest,
            'Culture'        => 'ru',
            'Encoding'       => 'utf-8',
        ]);

        return [
            'mode'         => $provider->mode,
            'gateway'      => 'Robokassa',
            'merchant'     => $merchantLogin,
            'inv_id'       => $invId,
            'payment_url'  => self::BASE_URL . '?' . $params,
        ];
    }

    public static function verifyWebhookSignature(
        string $outSum,
        int $invId,
        string $signatureValue,
        string $password2,
    ): bool {
        $expected = strtoupper(hash('sha256', "{$outSum}:{$invId}:{$password2}"));

        return hash_equals($expected, strtoupper($signatureValue));
    }
}
