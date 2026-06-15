<?php

namespace Tests\Feature;

use App\Models\LoyaltyProgramSetting;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_preview_checkout_with_loyalty_points(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $user = User::factory()->create([
            'email' => 'loyalty-preview@example.com',
            'password' => 'secret123',
            'loyalty_points_balance' => 1000,
            'loyalty_total_spent' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $preview = $this->postJson('/api/checkout/preview', [
            'session_id' => $sessionId,
            'delivery_method' => 'courier',
            'loyalty_points_to_spend' => 1000,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $preview->assertOk();
        $preview->assertJsonPath('subtotal', 9990);
        $preview->assertJsonPath('loyalty.applied_points', 1000);
        $preview->assertJsonPath('loyalty_discount_total', 1000);
        $preview->assertJsonPath('total', 9480);
        $preview->assertJsonPath('loyalty.points_to_earn', 449);
    }

    public function test_checkout_spends_and_accrues_loyalty_points_for_authenticated_user(): void
    {
        $this->seed(ShopDemoSeeder::class);

        LoyaltyProgramSetting::query()->whereKey(1)->update([
            'is_enabled' => true,
            'base_accrual_percent' => 5,
            'max_redeem_percent' => 25,
            'point_value' => 1,
        ]);

        $user = User::factory()->create([
            'email' => 'loyalty-checkout@example.com',
            'password' => 'secret123',
            'loyalty_points_balance' => 1000,
            'loyalty_total_spent' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $checkout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Loyalty Buyer',
            'customer_email' => 'loyalty-checkout@example.com',
            'customer_phone' => '+79990000099',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'loyalty_points_to_spend' => 1000,
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $checkout->assertCreated();
        $checkout->assertJsonPath('loyalty_points_spent', 1000);
        $checkout->assertJsonPath('loyalty_discount_total', 1000);
        $checkout->assertJsonPath('loyalty_points_earned', 449);
        $checkout->assertJsonPath('total', 9480);

        // Списание баллов применяется сразу при оформлении заказа.
        $user->refresh();
        $this->assertSame(0, (int) $user->loyalty_points_balance);
        $this->assertSame(0.0, (float) $user->loyalty_total_spent);
        $this->assertDatabaseCount('loyalty_transactions', 1);

        // Начисление баллов происходит только при выполнении заказа.
        $order = Order::query()->where('order_number', $checkout->json('order_number'))->firstOrFail();
        $order->update([
            'order_status' => 'completed',
            'fulfillment_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $user->refresh();
        $this->assertSame(449, (int) $user->loyalty_points_balance);
        $this->assertSame(8990.0, (float) $user->loyalty_total_spent);
        $this->assertDatabaseCount('loyalty_transactions', 2);
    }

    public function test_guest_cannot_spend_loyalty_points(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest-loyalty@example.com',
            'customer_phone' => '+79991111111',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'loyalty_points_to_spend' => 300,
        ]);

        $checkout->assertStatus(422);
        $checkout->assertJsonValidationErrors(['loyalty_points_to_spend']);
    }
}
