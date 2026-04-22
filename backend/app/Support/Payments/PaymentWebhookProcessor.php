<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PaymentWebhookProcessor
{
    public function process(string $providerCode, array $payload): PaymentWebhookLog
    {
        $provider = PaymentProvider::query()->where('code', $providerCode)->first();

        $eventType = $this->detectEventType($payload);
        $providerPaymentId = $this->extractString($payload, [
            'provider_payment_id',
            'payment_id',
            'paymentId',
            'PaymentId',
            'id',
        ]);
        $orderNumber = $this->extractString($payload, [
            'order_number',
            'orderNumber',
            'OrderNumber',
            'order_id',
            'OrderId',
        ]);
        $externalEventId = $this->extractString($payload, [
            'event_id',
            'eventId',
            'NotificationId',
            'webhook_id',
        ]);

        return DB::transaction(function () use ($provider, $providerCode, $payload, $eventType, $providerPaymentId, $orderNumber, $externalEventId): PaymentWebhookLog {
            $transaction = $this->resolveTransaction($providerCode, $providerPaymentId, $orderNumber);
            $order = $transaction?->order ?? $this->resolveOrderByNumber($orderNumber);

            $log = PaymentWebhookLog::query()->create([
                'order_id' => $order?->id,
                'payment_transaction_id' => $transaction?->id,
                'provider_code' => $providerCode,
                'external_event_id' => $externalEventId,
                'provider_payment_id' => $providerPaymentId,
                'order_number' => $orderNumber,
                'event_type' => $eventType,
                'status' => 'received',
                'payload' => $payload,
            ]);

            if (! $provider || ! $provider->is_active) {
                return $this->finalizeLog($log, 'ignored', [
                    'message' => 'Provider is inactive or missing.',
                ]);
            }

            if (! $transaction || ! $order) {
                return $this->finalizeLog($log, 'ignored', [
                    'message' => 'Transaction or order not found.',
                ]);
            }

            if ($providerPaymentId && ! $transaction->provider_payment_id) {
                $transaction->provider_payment_id = $providerPaymentId;
            }

            [$transactionStatus, $orderPatch] = $this->resolveWebhookOutcome($eventType, $order);

            if (! $transactionStatus) {
                return $this->finalizeLog($log, 'ignored', [
                    'message' => 'Unsupported event type.',
                ]);
            }

            $this->applyTransactionStatus($transaction, $transactionStatus);
            $transaction->save();

            $order->fill($orderPatch);
            $order->save();

            $log->order_id = $order->id;
            $log->payment_transaction_id = $transaction->id;

            return $this->finalizeLog($log, 'processed', [
                'transaction_status' => $transaction->status,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'refund_status' => $order->refund_status,
            ]);
        });
    }

    private function resolveTransaction(?string $providerCode, ?string $providerPaymentId, ?string $orderNumber): ?PaymentTransaction
    {
        if ($providerCode && $providerPaymentId) {
            $transaction = PaymentTransaction::query()
                ->where('provider', $providerCode)
                ->where('provider_payment_id', $providerPaymentId)
                ->latest('id')
                ->first();

            if ($transaction) {
                return $transaction;
            }
        }

        if (! $orderNumber) {
            return null;
        }

        return PaymentTransaction::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', $orderNumber))
            ->when($providerCode, fn ($query) => $query->where('provider', $providerCode))
            ->latest('id')
            ->first();
    }

    private function resolveOrderByNumber(?string $orderNumber): ?Order
    {
        if (! $orderNumber) {
            return null;
        }

        return Order::query()->where('order_number', $orderNumber)->first();
    }

    private function detectEventType(array $payload): ?string
    {
        $raw = $this->extractString($payload, [
            'event',
            'event_type',
            'type',
            'status',
            'Status',
        ]);

        return $raw ? str($raw)->lower()->replace(' ', '_')->value() : null;
    }

    private function extractString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function resolveWebhookOutcome(?string $eventType, Order $order): array
    {
        return match ($eventType) {
            'paid', 'succeeded', 'success', 'confirmed' => [
                'succeeded',
                [
                    'payment_status' => 'paid',
                    'order_status' => $order->order_status === 'placed' ? 'confirmed' : $order->order_status,
                ],
            ],
            'authorized', 'authorised', 'hold' => [
                'authorized',
                [
                    'payment_status' => 'authorized',
                ],
            ],
            'failed', 'rejected', 'error' => [
                'failed',
                [
                    'payment_status' => 'failed',
                ],
            ],
            'cancelled', 'canceled' => [
                'cancelled',
                [
                    'payment_status' => 'cancelled',
                ],
            ],
            'refunded', 'refund' => [
                'refunded',
                [
                    'payment_status' => 'refunded',
                    'refund_status' => 'refunded',
                ],
            ],
            'partially_refunded', 'partial_refund' => [
                'partially_refunded',
                [
                    'payment_status' => 'partially_refunded',
                    'refund_status' => 'partially_refunded',
                ],
            ],
            default => [null, []],
        };
    }

    private function applyTransactionStatus(PaymentTransaction $transaction, string $status): void
    {
        $transaction->status = $status;

        match ($status) {
            'succeeded', 'authorized' => $transaction->confirmed_at ??= now(),
            'failed' => $transaction->failed_at ??= now(),
            'cancelled' => $transaction->cancelled_at ??= now(),
            default => null,
        };
    }

    private function finalizeLog(PaymentWebhookLog $log, string $status, array $result, ?string $errorMessage = null): PaymentWebhookLog
    {
        $log->status = $status;
        $log->result = $result;
        $log->error_message = $errorMessage;
        $log->processed_at = now();
        $log->save();

        return $log;
    }
}
