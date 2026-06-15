<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCertificate;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCertificateAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // ── Активные / использованные сертификаты ─────────────────────────────
        $query = GiftCertificate::query()
            ->where('owner_user_id', $user->id);

        if (! $request->boolean('include_used')) {
            $query->usableForCustomer();
        }

        $items = $query
            ->orderByDesc('id')
            ->get()
            ->map(fn (GiftCertificate $certificate): array => [
                'id'                => $certificate->id,
                'code'              => $certificate->code,
                'balance_remaining' => (float) $certificate->balance_remaining,
                'initial_amount'    => (float) $certificate->initial_amount,
                'currency'          => $certificate->currency,
                'status'            => $certificate->status,
                'expires_at'        => $certificate->expires_at,
                'created_at'        => $certificate->created_at,
            ]);

        // ── Заказы на покупку сертификата, ожидающие оплаты ───────────────────
        $pendingOrders = Order::query()
            ->where('user_id', $user->id)
            ->where('checkout_kind', Order::CHECKOUT_KIND_GIFT_CERTIFICATE)
            ->where('payment_status', 'pending')   // только онлайн-оплата в ожидании
            ->orderByDesc('placed_at')
            ->get()
            ->map(fn (Order $order): array => [
                'order_number'   => $order->order_number,
                'total'          => (float) $order->total,
                'currency'       => $order->currency,
                'payment_status' => $order->payment_status,
                'placed_at'      => optional($order->placed_at)?->toIso8601String(),
            ]);

        return response()->json([
            'data'          => $items,
            'pending_orders' => $pendingOrders,
        ]);
    }
}
