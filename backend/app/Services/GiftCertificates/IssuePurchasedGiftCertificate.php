<?php

namespace App\Services\GiftCertificates;

use App\Models\GiftCertificate;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class IssuePurchasedGiftCertificate
{
    public static function run(Order $order): void
    {
        if (($order->checkout_kind ?? Order::CHECKOUT_KIND_CART) !== Order::CHECKOUT_KIND_GIFT_CERTIFICATE) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            return;
        }

        if ($order->gift_certificate_issued_at !== null) {
            return;
        }

        if (! $order->user_id) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->gift_certificate_issued_at !== null) {
                return;
            }

            $item = $locked->items()->first();

            if (! $item) {
                return;
            }

            $amount = round((float) $item->total_price, 2);

            if ($amount <= 0) {
                return;
            }

            $cert = GiftCertificate::query()->create([
                'owner_user_id' => $locked->user_id,
                'purchased_order_id' => $locked->id,
                'code' => GiftCertificate::generateUniqueCode(),
                'initial_amount' => $amount,
                'balance_remaining' => $amount,
                'currency' => $locked->currency,
                'status' => GiftCertificate::STATUS_ACTIVE,
                'expires_at' => null,
                'recipient_email' => null,
                'admin_note' => 'Покупка в кабинете, заказ '.$locked->order_number,
            ]);

            $locked->forceFill([
                'gift_certificate_issued_at' => now(),
                'order_status' => 'completed',
                'fulfillment_status' => 'delivered',
            ])->save();
        });
    }
}
