<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TrackingEvent;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_prioritize_co_purchase_products_for_slug(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $targetProduct = Product::query()->where('slug', 'neon-track-x')->firstOrFail();
        $bestMatchProduct = Product::query()->where('slug', 'vault-signature')->firstOrFail();
        $secondaryMatchProduct = Product::query()->where('slug', 'cloud-step-v2')->firstOrFail();

        $orderA = Order::query()->create([
            'order_number' => 'T-100001',
            'session_id' => 'session-order-a',
            'status' => 'paid',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 20000,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 20000,
            'customer_name' => 'Test User',
            'customer_email' => 'buyer-a@example.com',
            'customer_phone' => '+79000000001',
            'placed_at' => now()->subDays(2),
        ]);

        $orderB = Order::query()->create([
            'order_number' => 'T-100002',
            'session_id' => 'session-order-b',
            'status' => 'completed',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 22000,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 22000,
            'customer_name' => 'Test User',
            'customer_email' => 'buyer-b@example.com',
            'customer_phone' => '+79000000002',
            'placed_at' => now()->subDays(1),
        ]);

        foreach ([$orderA, $orderB] as $order) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $targetProduct->id,
                'product_name' => $targetProduct->name,
                'product_slug' => $targetProduct->slug,
                'qty' => 1,
                'unit_price' => $targetProduct->price,
                'total_price' => $targetProduct->price,
            ]);
        }

        OrderItem::query()->create([
            'order_id' => $orderA->id,
            'product_id' => $bestMatchProduct->id,
            'product_name' => $bestMatchProduct->name,
            'product_slug' => $bestMatchProduct->slug,
            'qty' => 1,
            'unit_price' => $bestMatchProduct->price,
            'total_price' => $bestMatchProduct->price,
        ]);

        OrderItem::query()->create([
            'order_id' => $orderB->id,
            'product_id' => $bestMatchProduct->id,
            'product_name' => $bestMatchProduct->name,
            'product_slug' => $bestMatchProduct->slug,
            'qty' => 1,
            'unit_price' => $bestMatchProduct->price,
            'total_price' => $bestMatchProduct->price,
        ]);

        OrderItem::query()->create([
            'order_id' => $orderA->id,
            'product_id' => $secondaryMatchProduct->id,
            'product_name' => $secondaryMatchProduct->name,
            'product_slug' => $secondaryMatchProduct->slug,
            'qty' => 1,
            'unit_price' => $secondaryMatchProduct->price,
            'total_price' => $secondaryMatchProduct->price,
        ]);

        $response = $this->getJson('/api/products/neon-track-x/recommendations');

        $response->assertOk();
        $response->assertJsonPath('source', 'co_purchase');
        $response->assertJsonPath('data.0.slug', 'vault-signature');
        $response->assertJsonFragment(['slug' => 'cloud-step-v2']);
    }

    public function test_recommendations_return_co_view_products_for_slug(): void
    {
        $this->seed(ShopDemoSeeder::class);

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-a',
            'payload' => ['slug' => 'neon-track-x'],
            'occurred_at' => now()->subMinutes(10),
        ]);

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-a',
            'payload' => ['slug' => 'vault-signature'],
            'occurred_at' => now()->subMinutes(9),
        ]);

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-a',
            'payload' => ['slug' => 'cloud-step-v2'],
            'occurred_at' => now()->subMinutes(8),
        ]);

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-b',
            'payload' => ['slug' => 'neon-track-x'],
            'occurred_at' => now()->subMinutes(7),
        ]);

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => 'session-b',
            'payload' => ['slug' => 'vault-signature'],
            'occurred_at' => now()->subMinutes(6),
        ]);

        $response = $this->getJson('/api/products/neon-track-x/recommendations');

        $response->assertOk();
        $response->assertJsonPath('source', 'co_view');
        $response->assertJsonPath('data.0.slug', 'vault-signature');
        $response->assertJsonFragment(['slug' => 'cloud-step-v2']);
    }

    public function test_recommendations_fallback_to_featured_when_no_view_events(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/products/neon-track-x/recommendations');

        $response->assertOk();
        $response->assertJsonPath('source', 'featured_fallback');
        $response->assertJsonMissing(['slug' => 'neon-track-x']);
    }
}
