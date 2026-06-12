<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutOneClickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('checkout-one-click');
    }

    public function test_one_click_requires_authentication(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $this->postJson('/api/checkout/one-click', [
            'product_slug' => 'city-frame-one',
        ])->assertUnauthorized();
    }

    public function test_one_click_orders_single_item_and_restores_previous_cart(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'oneclick@example.com',
            'phone' => '+79991112233',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $this->postJson('/api/checkout', [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => '+79991112233',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ])->assertCreated();

        $this->postJson('/api/cart/items', [
            'product_slug' => 'city-frame-one',
            'qty' => 2,
        ])->assertOk();

        $response = $this->postJson('/api/checkout/one-click', [
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('order_status', 'placed');

        $cart = $this->getJson('/api/cart')->assertOk();
        $cart->assertJsonPath('total_items', 2);
        $cart->assertJsonPath('items.0.product_slug', 'city-frame-one');
    }

    public function test_one_click_suggestions_requires_authentication(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $this->getJson('/api/checkout/one-click/suggestions')->assertUnauthorized();
    }

    public function test_one_click_suggestions_returns_delivery_and_payment(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+79001234567',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/checkout/one-click/suggestions');

        $response->assertOk();
        $response->assertJsonStructure(['delivery_method', 'payment_method']);
    }

    public function test_one_click_respects_explicit_delivery_and_payment(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'pickupcash@example.com',
            'phone' => '+79001234567',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout/one-click', [
            'product_slug' => 'city-frame-one',
            'qty' => 1,
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('delivery_method', 'pickup');
        $response->assertJsonPath('payment_method', 'cash');
    }

    public function test_one_click_rejects_unknown_delivery_method(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'phone' => '+79001234567',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout/one-click', [
            'product_slug' => 'city-frame-one',
            'delivery_method' => 'does_not_exist_xyz',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_method']);
    }

    public function test_one_click_requires_phone_when_no_prior_order_phone(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout/one-click', [
            'product_slug' => 'city-frame-one',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_phone']);
    }
}
