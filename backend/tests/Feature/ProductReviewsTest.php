<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_leave_review_without_purchase(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/products/city-frame-one/reviews', [
                'rating' => 5,
                'review_text' => 'Очень удобная модель, но не покупал.',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Оставить отзыв можно только после покупки этого товара.');
    }

    public function test_user_can_create_review_after_purchase(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();

        $this->createPaidOrderForProduct($user, $product);

        $initialCount = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->count();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/products/city-frame-one/reviews', [
                'rating' => 5,
                'review_text' => 'Качество отличное, доставка быстрая, размер подошёл.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('review.rating', 5);
        $response->assertJsonPath('summary.count', $initialCount + 1);

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);
    }

    public function test_review_requires_text_and_rating(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();

        $this->createPaidOrderForProduct($user, $product);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/products/city-frame-one/reviews', [
                'rating' => 4,
                'review_text' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['review_text']);
    }

    public function test_product_show_returns_review_flags_for_authorized_user(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $user = User::factory()->create();
        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();

        $this->createPaidOrderForProduct($user, $product);

        $initialCount = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->count();

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/products/city-frame-one')
            ->assertOk()
            ->assertJsonPath('can_review', true)
            ->assertJsonPath('reviews_summary.count', $initialCount);

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
            'review_text' => 'После недели носки всё отлично.',
            'is_active' => true,
            'is_verified_purchase' => true,
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/products/city-frame-one')
            ->assertOk()
            ->assertJsonPath('reviews_summary.count', $initialCount + 1)
            ->assertJsonPath('my_review.rating', 4);
    }

    private function createPaidOrderForProduct(User $user, Product $product): void
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'RVW-' . strtoupper(substr((string) fake()->uuid(), 0, 8)),
            'session_id' => 'review-test-session-' . $user->id,
            'status' => 'paid',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => $product->price,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => $product->price,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '+79990001122',
            'placed_at' => now()->subDay(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'image_url' => null,
            'qty' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
        ]);
    }
}
