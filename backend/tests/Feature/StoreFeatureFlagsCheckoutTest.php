<?php

namespace Tests\Feature;

use App\Models\GiftCertificate;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreFeatureFlagsCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_gift_certificate_when_feature_flag_disabled(): void
    {
        $this->seed(ShopDemoSeeder::class);

        SiteSetting::query()->whereKey(1)->update([
            'feature_flags' => [
                'loyalty' => true,
                'gift_certificates' => false,
                'wishlist' => true,
                'product_compare' => true,
            ],
        ]);

        $cert = GiftCertificate::query()->create([
            'code' => 'FLAG-OFF-AAA-BBB-CCC',
            'initial_amount' => 1000,
            'balance_remaining' => 1000,
            'currency' => 'RUB',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => null,
            'recipient_email' => null,
            'admin_note' => null,
        ]);

        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => $product->slug,
            'qty' => 1,
        ])->assertOk();

        $response = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Тест',
            'customer_email' => 'flag-gc@example.com',
            'customer_phone' => '+79991112233',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'gift_certificate_code' => $cert->code,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('сертификат', mb_strtolower((string) collect($response->json('errors.gift_certificate_code'))->first()));
    }

    public function test_checkout_options_disables_loyalty_payload_when_feature_flag_off(): void
    {
        $this->seed(ShopDemoSeeder::class);

        SiteSetting::query()->whereKey(1)->update([
            'feature_flags' => [
                'loyalty' => false,
                'gift_certificates' => true,
                'wishlist' => true,
                'product_compare' => true,
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/checkout/options');

        $response->assertOk()
            ->assertJsonPath('loyalty.is_enabled', false)
            ->assertJsonPath('loyalty.account', null);
    }
}
