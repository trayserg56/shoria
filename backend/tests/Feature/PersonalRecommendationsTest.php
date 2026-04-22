<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TrackingEvent;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_recommendations_use_order_history_when_available(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'session-personal-orders';
        $seedProduct = Product::query()->where('slug', 'neon-track-x')->firstOrFail();

        $order = Order::query()->create([
            'order_number' => 'T-200001',
            'session_id' => $sessionId,
            'status' => 'new',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => $seedProduct->price,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => $seedProduct->price,
            'customer_name' => 'Personal User',
            'customer_email' => 'personal-orders@example.com',
            'customer_phone' => '+79000000010',
            'placed_at' => now()->subDays(2),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $seedProduct->id,
            'product_name' => $seedProduct->name,
            'product_slug' => $seedProduct->slug,
            'qty' => 1,
            'unit_price' => $seedProduct->price,
            'total_price' => $seedProduct->price,
        ]);

        $response = $this->getJson('/api/recommendations/personal?session_id=' . $sessionId);

        $response->assertOk();
        $response->assertJsonPath('source', 'order_history');
        $response->assertJsonMissing(['slug' => 'neon-track-x']);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_personal_recommendations_use_view_history_when_orders_absent(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'session-personal-views';

        TrackingEvent::query()->create([
            'event_name' => 'view_product',
            'session_id' => $sessionId,
            'payload' => ['slug' => 'neon-track-x'],
            'occurred_at' => now()->subMinutes(4),
        ]);

        $response = $this->getJson('/api/recommendations/personal?session_id=' . $sessionId);

        $response->assertOk();
        $response->assertJsonPath('source', 'view_history');
        $response->assertJsonMissing(['slug' => 'neon-track-x']);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_personal_recommendations_fallback_to_featured_without_history(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/recommendations/personal?session_id=session-no-history');

        $response->assertOk();
        $response->assertJsonPath('source', 'featured_fallback');
        $this->assertNotEmpty($response->json('data'));
    }
}
