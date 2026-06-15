<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:new,paid,processing,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);

        $query = Order::query()->orderByDesc('placed_at');

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $orders = $query
            ->paginate($perPage)
            ->through(fn (Order $order) => [
                'order_number' => $order->order_number,
                'checkout_kind' => $order->checkout_kind ?? Order::CHECKOUT_KIND_CART,
                'status' => $order->status,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'fulfillment_status' => $order->fulfillment_status,
                'refund_status' => $order->refund_status,
                'payment_transaction_status' => $order->paymentTransactions()->value('status'),
                'delivery_method' => $order->delivery_method,
                'payment_method' => $order->payment_method,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at,
            ]);

        return response()->json($orders);
    }

    /**
     * Возвращает ссылку на страницу оплаты для заказа со статусом pending.
     * Когда будет подключён реальный платёжный шлюз — здесь появится URL редиректа.
     */
    public function paymentUrl(Request $request, string $orderNumber): JsonResponse
    {
        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);

        $query = Order::query()
            ->with(['paymentTransactions'])
            ->where('order_number', $orderNumber);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        }

        $order = $query->firstOrFail();

        // Проверяем, что оплата действительно ожидает
        if ($order->payment_status !== 'pending') {
            return response()->json([
                'payment_url' => null,
                'reason'      => 'not_pending',
            ]);
        }

        // Ищем URL в мета-данных транзакции (заполняется реальным gateway при создании)
        $transaction = $order->paymentTransactions
            ->where('status', 'pending')
            ->first();

        $paymentUrl = data_get($transaction?->meta, 'payment_url');

        return response()->json([
            'payment_url'  => $paymentUrl,
            'order_number' => $order->order_number,
            'amount'       => (float) $order->total,
            'currency'     => $order->currency,
        ]);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);

        $query = Order::query()
            ->with(['items', 'paymentTransactions', 'giftCertificate', 'purchasedGiftCertificate'])
            ->where('order_number', $orderNumber);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')->where('session_id', $sessionId);
        }

        $order = $query->firstOrFail();

        $purchasedGiftPayload = null;

        if (($order->checkout_kind ?? Order::CHECKOUT_KIND_CART) === Order::CHECKOUT_KIND_GIFT_CERTIFICATE) {
            $issued = $order->purchasedGiftCertificate;

            if ($issued) {
                $purchasedGiftPayload = [
                    'code' => $issued->code,
                    'initial_amount' => (float) $issued->initial_amount,
                    'balance_remaining' => (float) $issued->balance_remaining,
                    'status' => $issued->status,
                    'currency' => $issued->currency,
                ];
            }
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'checkout_kind' => $order->checkout_kind ?? Order::CHECKOUT_KIND_CART,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'refund_status' => $order->refund_status,
            'payment_transaction_status' => $order->paymentTransactions->first()?->status,
            'delivery_method' => $order->delivery_method,
            'delivery_city' => $order->delivery_city,
            'delivery_address' => $order->delivery_address,
            'delivery_pickup_point_code' => $order->delivery_pickup_point_code,
            'delivery_pickup_point_address' => $order->delivery_pickup_point_address,
            'delivery_period_min' => $order->delivery_period_min !== null ? (int) $order->delivery_period_min : null,
            'delivery_period_max' => $order->delivery_period_max !== null ? (int) $order->delivery_period_max : null,
            'payment_method' => $order->payment_method,
            'promo_code' => $order->promo_code,
            'gift_certificate_code' => $order->giftCertificate?->code,
            'purchased_gift_certificate' => $purchasedGiftPayload,
            'gift_certificate_discount_total' => (float) $order->gift_certificate_discount_total,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'loyalty_discount_total' => (float) $order->loyalty_discount_total,
            'loyalty_points_spent' => (int) $order->loyalty_points_spent,
            'loyalty_points_earned' => (int) $order->loyalty_points_earned,
            'delivery_total' => (float) $order->delivery_total,
            'currency' => $order->currency,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'comment' => $order->comment,
            'placed_at' => $order->placed_at,
            'confirmed_at' => $order->confirmed_at,
            'cancelled_at' => $order->cancelled_at,
            'completed_at' => $order->completed_at,
            'payment_transactions' => $order->paymentTransactions->map(fn ($transaction) => [
                'provider' => $transaction->provider,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'provider_payment_id' => $transaction->provider_payment_id,
                'confirmed_at' => $transaction->confirmed_at,
                'failed_at' => $transaction->failed_at,
                'cancelled_at' => $transaction->cancelled_at,
            ])->values(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product_name' => $item->product_name,
                'product_slug' => $item->product_slug,
                'variant_label' => $item->variant_label,
                'image_url' => $item->image_url,
                'qty' => $item->qty,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
            ])->values(),
        ]);
    }

    private function resolveIdentity(Request $request): array
    {
        $user = $this->resolveAuthenticatedUser($request);
        $sessionId = (string) $request->query('session_id', '');

        if (! $user && $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => 'session_id обязателен.',
            ]);
        }

        return [
            'user' => $user,
            'session_id' => $sessionId !== '' ? $sessionId : null,
        ];
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            return $user;
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
