<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscription;
use App\Models\Order;
use App\Models\TrackingEvent;
use App\Support\Analytics\MarketingDashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_dashboard_aggregates_funnel_and_revenue_metrics(): void
    {
        CarbonImmutable::setTestNow('2026-04-23 12:00:00');

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-1',
            'first_touch_source' => 'telegram',
            'occurred_at' => now()->subDay(),
        ]);
        TrackingEvent::query()->create([
            'event_name' => 'add_to_cart',
            'session_id' => 'session-1',
            'first_touch_source' => 'telegram',
            'occurred_at' => now()->subDay(),
        ]);
        TrackingEvent::query()->create([
            'event_name' => 'begin_checkout',
            'session_id' => 'session-1',
            'first_touch_source' => 'telegram',
            'occurred_at' => now()->subDay(),
        ]);
        TrackingEvent::query()->create([
            'event_name' => 'purchase',
            'session_id' => 'session-1',
            'first_touch_source' => 'telegram',
            'occurred_at' => now()->subDay(),
        ]);
        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-2',
            'first_touch_source' => 'google',
            'occurred_at' => now()->subDay(),
        ]);

        NewsletterSubscription::query()->create([
            'email' => 'dash@example.com',
            'status' => 'subscribed',
            'source' => 'home',
            'first_touch_source' => 'telegram',
            'subscribed_at' => now()->subDay(),
        ]);

        Order::query()->create([
            'order_number' => 'SH2604231200001',
            'session_id' => 'session-1',
            'status' => 'paid',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'delivered',
            'refund_status' => 'none',
            'currency' => 'RUB',
            'subtotal' => 9990,
            'discount_total' => 0,
            'delivery_total' => 490,
            'total' => 10480,
            'customer_name' => 'Dash Buyer',
            'customer_email' => 'dash-buyer@example.com',
            'customer_phone' => '+79990000001',
            'first_touch_source' => 'telegram',
            'placed_at' => now()->subDay(),
        ]);

        $metrics = app(MarketingDashboardData::class)->forLastDays(30);

        $this->assertSame(2, $metrics['overview']['sessions']);
        $this->assertSame(1, $metrics['overview']['orders']);
        $this->assertSame(10480.0, $metrics['overview']['revenue']);
        $this->assertSame(10480.0, $metrics['overview']['average_order_value']);
        $this->assertSame(1, $metrics['overview']['newsletter_subscriptions']);

        $this->assertSame(2, $metrics['funnel']['view_product_sessions']);
        $this->assertSame(1, $metrics['funnel']['add_to_cart_sessions']);
        $this->assertSame(1, $metrics['funnel']['begin_checkout_sessions']);
        $this->assertSame(1, $metrics['funnel']['purchase_sessions']);
        $this->assertSame(50.0, $metrics['funnel']['view_to_cart_rate']);
        $this->assertSame(100.0, $metrics['funnel']['cart_to_checkout_rate']);
        $this->assertSame(100.0, $metrics['funnel']['checkout_to_purchase_rate']);
        $this->assertSame(50.0, $metrics['funnel']['view_to_purchase_rate']);
        $this->assertSame(['telegram', 'google'], $metrics['attribution']['labels']);
        $this->assertSame([1, 1], $metrics['attribution']['sessions']);
        $this->assertSame([1, 0], $metrics['attribution']['orders']);
        $this->assertSame([10480.0, 0.0], $metrics['attribution']['revenue']);

        $this->assertContains('22.04', $metrics['daily']['labels']);
        $index = array_search('22.04', $metrics['daily']['labels'], true);

        $this->assertNotFalse($index);
        $this->assertSame(2, $metrics['daily']['views'][$index]);
        $this->assertSame(1, $metrics['daily']['add_to_cart'][$index]);
        $this->assertSame(1, $metrics['daily']['begin_checkout'][$index]);
        $this->assertSame(1, $metrics['daily']['purchase'][$index]);
        $this->assertSame(1, $metrics['daily']['newsletter_subscriptions'][$index]);
        $this->assertSame(10480.0, $metrics['daily']['revenue'][$index]);

        CarbonImmutable::setTestNow();
    }
}
