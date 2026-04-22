<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\PaymentTransaction;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_marks_order_as_paid_and_logs_event(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'webhook-paid-session';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Webhook Buyer',
            'customer_email' => 'webhook@example.com',
            'customer_phone' => '+79990000123',
            'delivery_method' => 'courier',
            'payment_method' => 'tbank_card',
        ])->assertCreated();

        $orderNumber = $checkoutResponse->json('order_number');

        $webhookResponse = $this->postJson('/api/payments/webhooks/tbank_card', [
            'event' => 'paid',
            'order_number' => $orderNumber,
            'payment_id' => 'bank-payment-001',
            'event_id' => 'evt-paid-001',
        ]);

        $webhookResponse->assertOk();
        $webhookResponse->assertJsonPath('status', 'processed');
        $webhookResponse->assertJsonPath('result.payment_status', 'paid');
        $webhookResponse->assertJsonPath('result.order_status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('payment_transactions', [
            'provider' => 'tbank_card',
            'provider_payment_id' => 'bank-payment-001',
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_code' => 'tbank_card',
            'order_number' => $orderNumber,
            'event_type' => 'paid',
            'status' => 'processed',
        ]);
    }

    public function test_unknown_payment_webhook_event_is_logged_as_ignored(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'webhook-ignored-session';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Webhook Buyer',
            'customer_email' => 'ignored@example.com',
            'customer_phone' => '+79990000124',
            'delivery_method' => 'courier',
            'payment_method' => 'tbank_card',
        ])->assertCreated();

        $orderNumber = $checkoutResponse->json('order_number');

        $webhookResponse = $this->postJson('/api/payments/webhooks/tbank_card', [
            'event' => 'ping',
            'order_number' => $orderNumber,
            'event_id' => 'evt-ping-001',
        ]);

        $webhookResponse->assertOk();
        $webhookResponse->assertJsonPath('status', 'ignored');

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $transaction->status);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_code' => 'tbank_card',
            'order_number' => $orderNumber,
            'event_type' => 'ping',
            'status' => 'ignored',
        ]);
    }

    public function test_order_status_logs_relation_is_scoped_to_single_order(): void
    {
        $firstOrder = Order::query()->create([
            'order_number' => 'SH-TEST-001',
            'session_id' => 'status-log-order-1',
            'status' => 'new',
            'order_status' => 'placed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'refund_status' => 'none',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 1000,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 1000,
            'customer_name' => 'First Buyer',
            'customer_email' => 'first@example.com',
            'customer_phone' => '+79990000001',
            'placed_at' => now(),
        ]);

        $secondOrder = Order::query()->create([
            'order_number' => 'SH-TEST-002',
            'session_id' => 'status-log-order-2',
            'status' => 'new',
            'order_status' => 'placed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'refund_status' => 'none',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 2000,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 2000,
            'customer_name' => 'Second Buyer',
            'customer_email' => 'second@example.com',
            'customer_phone' => '+79990000002',
            'placed_at' => now(),
        ]);

        OrderStatusLog::query()->create([
            'order_id' => $firstOrder->id,
            'field' => 'payment_status',
            'old_value' => 'pending',
            'new_value' => 'paid',
            'source' => 'test',
        ]);

        OrderStatusLog::query()->create([
            'order_id' => $secondOrder->id,
            'field' => 'payment_status',
            'old_value' => 'pending',
            'new_value' => 'failed',
            'source' => 'test',
        ]);

        $firstOrderLogs = $firstOrder->fresh()->statusLogs()->pluck('order_id')->unique()->all();
        $secondOrderLogs = $secondOrder->fresh()->statusLogs()->pluck('order_id')->unique()->all();

        $this->assertSame([$firstOrder->id], $firstOrderLogs);
        $this->assertSame([$secondOrder->id], $secondOrderLogs);
    }
}
