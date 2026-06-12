<?php

namespace App\Observers;

use App\Models\LoyaltyProgramSetting;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Jobs\Onec\SendOnecWebhookJob;
use App\Services\Stock\StockService;
use App\Support\Loyalty\LoyaltyProgramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private LoyaltyProgramService $loyalty,
        private StockService $stock,
    ) {}

    public function created(Order $order): void
    {
        $this->reserveStock($order);

        if (config('services.onec.webhook_url')) {
            SendOnecWebhookJob::dispatch('orders.created', $this->orderWebhookPayload($order));
        }
    }

    /**
     * Срабатывает при каждом обновлении заказа.
     * Начисляем баллы только когда заказ доходит до финального статуса
     * (выполнен / товар доставлен) — чтобы нельзя было накрутить баллы
     * оформлением заказов без реальной оплаты и получения товара.
     */
    public function updated(Order $order): void
    {
        $reachedCompleted = $order->wasChanged('order_status') && $order->order_status === 'completed';
        $reachedDelivered = $order->wasChanged('fulfillment_status') && $order->fulfillment_status === 'delivered';

        if ($reachedCompleted || $reachedDelivered) {
            $this->maybeAccrueLoyaltyPoints($order);
        }

        // Управление остатками по статусам
        if ($order->wasChanged('payment_status')) {
            $paid = in_array($order->payment_status, ['paid', 'authorized'], true);
            $wasPaid = in_array($order->getOriginal('payment_status'), ['paid', 'authorized'], true);

            if ($paid && ! $wasPaid) {
                // Оплачен — конвертируем резерв в реальное списание
                $this->deductStock($order);
            }
        }

        if ($order->wasChanged('order_status') || $order->wasChanged('payment_status')) {
            if (config('services.onec.webhook_url')) {
                $event = $order->order_status === 'cancelled' ? 'orders.cancelled' : 'orders.status_changed';
                SendOnecWebhookJob::dispatch($event, $this->orderWebhookPayload($order));
            }
        }

        if ($order->wasChanged('order_status') && $order->order_status === 'cancelled') {
            $wasPaid = in_array($order->getOriginal('payment_status'), ['paid', 'authorized'], true);
            // Если был оплачен — остаток уже списан, не возвращаем; иначе снимаем резерв
            if (! $wasPaid) {
                $this->releaseStock($order);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function orderWebhookPayload(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'onec_uuid' => $order->onec_uuid,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'qty' => $item->qty,
                'price' => (float) $item->price,
            ])->all(),
        ];
    }

    private function reserveStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            try {
                $this->stock->reserve($item->product_id, $item->product_variant_id, $item->qty, $order);
            } catch (\Throwable $e) {
                Log::warning('OrderObserver: не удалось зарезервировать остаток', [
                    'order' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->product_variant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function deductStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            try {
                $this->stock->deduct($item->product_id, $item->product_variant_id, $item->qty, $order);
            } catch (\Throwable $e) {
                Log::error('OrderObserver: не удалось списать остаток', [
                    'order' => $order->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function releaseStock(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            try {
                $this->stock->release($item->product_id, $item->product_variant_id, $item->qty, $order);
            } catch (\Throwable $e) {
                Log::warning('OrderObserver: не удалось освободить резерв', [
                    'order' => $order->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function maybeAccrueLoyaltyPoints(Order $order): void
    {
        // Нет пользователя или нечего начислять
        if (! $order->user_id || (int) $order->loyalty_points_earned <= 0) {
            return;
        }

        // Идемпотентность: не начислять дважды
        $alreadyAccrued = LoyaltyTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', 'accrual')
            ->exists();

        if ($alreadyAccrued) {
            return;
        }

        $user = User::query()->find($order->user_id);

        if (! $user) {
            return;
        }

        $setting = LoyaltyProgramSetting::query()->first();

        // Извлекаем процент из сохранённой мета-информации транзакции (если есть),
        // иначе пересчитываем по текущим настройкам программы
        // Подгружаем транзакции, если ещё не загружены
        if (! $order->relationLoaded('paymentTransactions')) {
            $order->load('paymentTransactions');
        }

        $accrualPercent = $this->resolveAccrualPercent($order, $user, $setting);
        $accrualBase    = $this->resolveAccrualBase($order);
        $pointsEarned   = (int) $order->loyalty_points_earned;

        DB::transaction(function () use ($user, $order, $pointsEarned, $accrualBase, $accrualPercent): void {
            $freshUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $freshUser) {
                return;
            }

            $this->loyalty->applyAccrual(
                $freshUser,
                $order,
                $pointsEarned,
                $accrualBase,
                $accrualPercent,
            );

            Log::info('OrderObserver: баллы начислены', [
                'order'   => $order->order_number,
                'user_id' => $user->id,
                'points'  => $pointsEarned,
            ]);
        });
    }

    /**
     * Процент начисления: берём из мета транзакции (зафиксирован при оформлении),
     * если не сохранён — пересчитываем по текущему уровню пользователя.
     */
    private function resolveAccrualPercent(Order $order, User $user, ?LoyaltyProgramSetting $setting): float
    {
        $tx = $order->paymentTransactions
            ->where('type', 'payment')
            ->first();

        $savedPercent = data_get($tx?->meta, 'accrual_percent');

        if ($savedPercent !== null) {
            return (float) $savedPercent;
        }

        return $this->loyalty->resolveEffectiveAccrualPercent($user, $setting);
    }

    /**
     * База начисления = subtotal − скидки (без учёта доставки).
     * Для заказов, где поле не заполнено — используем total.
     */
    private function resolveAccrualBase(Order $order): float
    {
        $base = (float) $order->subtotal
            - (float) $order->discount_total
            - (float) $order->loyalty_discount_total
            - (float) $order->gift_certificate_discount_total;

        return max(0.0, round($base, 2)) ?: (float) $order->total;
    }
}
