<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartMail;
use App\Mail\ReviewReminderMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\GiftCertificate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketingAndGiftCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_gift_certificate_discount_and_updates_balance(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => $product->slug,
            'qty' => 1,
        ])->assertOk();

        $cert = GiftCertificate::query()->create([
            'code' => 'TEST-GC-AAAA-BBBB-CCCC',
            'initial_amount' => 3000,
            'balance_remaining' => 3000,
            'currency' => 'RUB',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => null,
            'recipient_email' => null,
            'admin_note' => null,
        ]);

        $response = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Покупатель',
            'customer_email' => 'buyer-gc@example.com',
            'customer_phone' => '+79991112233',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'gift_certificate_code' => $cert->code,
        ]);

        $response->assertCreated();
        $expectedPrice = (float) $product->price;
        $response->assertJsonPath('gift_certificate_code', $cert->code);

        $applied = min(3000.0, $expectedPrice);
        $this->assertEquals($applied, (float) $response->json('gift_certificate_discount_total'));

        $cert->refresh();
        $this->assertEquals(3000 - $applied, (float) $cert->balance_remaining);
        $this->assertSame(
            ((float) $cert->balance_remaining) <= 0 ? GiftCertificate::STATUS_DEPLETED : GiftCertificate::STATUS_ACTIVE,
            $cert->status,
        );

        $this->assertDatabaseHas('gift_certificate_redemptions', [
            'gift_certificate_id' => $cert->id,
            'amount' => $applied,
        ]);
    }

    public function test_abandoned_cart_command_sends_mail_to_user_with_open_cart(): void
    {
        Mail::fake();

        $this->seed(ShopDemoSeeder::class);

        $product = Product::query()->firstOrFail();

        $user = User::factory()->create([
            'email' => 'cart-user@example.com',
        ]);

        $cart = Cart::query()->create([
            'user_id' => $user->id,
            'session_id' => 'user:'.$user->id,
            'status' => 'open',
            'currency' => 'RUB',
            'subtotal' => 100,
            'total' => 100,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'variant_label' => null,
            'image_url' => null,
            'qty' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $cart->forceFill(['updated_at' => now()->subDays(2)])->save();

        $this->artisan('marketing:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertSent(AbandonedCartMail::class, function (AbandonedCartMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $cart->refresh();
        $this->assertNotNull($cart->abandoned_cart_reminded_at);
    }

    public function test_review_reminder_command_sends_when_order_delivered(): void
    {
        Mail::fake();

        $this->seed(ShopDemoSeeder::class);
        $product = Product::query()->firstOrFail();

        $user = User::factory()->create([
            'email' => 'review-user@example.com',
        ]);

        $order = Order::query()->create([
            'order_number' => 'SHTESTREVW1',
            'user_id' => $user->id,
            'session_id' => 's1',
            'status' => 'completed',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'delivered',
            'refund_status' => 'none',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 100,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 100,
            'customer_name' => 'T',
            'customer_email' => $user->email,
            'customer_phone' => '+79990001122',
            'placed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
        ]);

        $order->forceFill(['updated_at' => now()->subWeek()])->save();

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'variant_label' => null,
            'image_url' => null,
            'qty' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $this->artisan('marketing:send-review-reminders')->assertSuccessful();

        Mail::assertSent(ReviewReminderMail::class, function (ReviewReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $order->refresh();
        $this->assertNotNull($order->marketing_review_reminder_sent_at);
    }

    public function test_review_reminder_skips_when_review_exists_for_order_item(): void
    {
        Mail::fake();

        $this->seed(ShopDemoSeeder::class);
        $product = Product::query()->firstOrFail();

        $user = User::factory()->create([
            'email' => 'review-done@example.com',
        ]);

        $order = Order::query()->create([
            'order_number' => 'SHTESTREVW2',
            'user_id' => $user->id,
            'session_id' => 's2',
            'status' => 'completed',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'delivered',
            'refund_status' => 'none',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'currency' => 'RUB',
            'subtotal' => 100,
            'discount_total' => 0,
            'delivery_total' => 0,
            'total' => 100,
            'customer_name' => 'T',
            'customer_email' => $user->email,
            'customer_phone' => '+79990001122',
            'placed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
        ]);

        $order->forceFill(['updated_at' => now()->subWeek()])->save();

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'variant_label' => null,
            'image_url' => null,
            'qty' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_item_id' => $item->id,
            'rating' => 5,
            'review_text' => 'ok',
            'is_active' => true,
            'is_verified_purchase' => true,
        ]);

        $this->artisan('marketing:send-review-reminders')->assertSuccessful();

        Mail::assertNothingSent();

        $order->refresh();
        $this->assertNotNull($order->marketing_review_reminder_sent_at);
    }

    public function test_checkout_applies_owned_gift_certificate_by_id(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $product = Product::query()->where('slug', 'city-frame-one')->firstOrFail();

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'owned-gc@example.com',
            'phone' => '+79990005577',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_slug' => $product->slug,
            'qty' => 1,
        ])->assertOk();

        $cert = GiftCertificate::query()->create([
            'owner_user_id' => $user->id,
            'code' => 'OWNED-GC-AAA-BBB-CCC',
            'initial_amount' => 3000,
            'balance_remaining' => 3000,
            'currency' => 'RUB',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => null,
            'recipient_email' => null,
            'admin_note' => null,
        ]);

        $response = $this->postJson('/api/checkout', [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => (string) $user->phone,
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'gift_certificate_id' => $cert->id,
        ]);

        $response->assertCreated();
        $expectedPrice = (float) $product->price;

        $applied = min(3000.0, $expectedPrice);
        $this->assertEquals($applied, (float) $response->json('gift_certificate_discount_total'));

        $cert->refresh();
        $this->assertEquals(3000 - $applied, (float) $cert->balance_remaining);
    }

    public function test_me_gift_certificates_lists_only_usable_unless_include_used(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        GiftCertificate::query()->create([
            'owner_user_id' => $user->id,
            'code' => 'USABLE-GC-AAA-BBB-CCC',
            'initial_amount' => 500,
            'balance_remaining' => 500,
            'currency' => 'RUB',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => null,
            'recipient_email' => null,
            'admin_note' => null,
        ]);

        GiftCertificate::query()->create([
            'owner_user_id' => $user->id,
            'code' => 'USED-GC-AAA-BBB-CCC',
            'initial_amount' => 500,
            'balance_remaining' => 0,
            'currency' => 'RUB',
            'status' => GiftCertificate::STATUS_DEPLETED,
            'expires_at' => null,
            'recipient_email' => null,
            'admin_note' => null,
        ]);

        $this->getJson('/api/me/gift-certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USABLE-GC-AAA-BBB-CCC');

        $this->getJson('/api/me/gift-certificates?include_used=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_gift_certificate_purchase_issues_certificate_after_payment_webhook(): void
    {
        $this->seed(ShopDemoSeeder::class);

        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'buy-gc@example.com',
            'phone' => '+79990006688',
        ]);

        Sanctum::actingAs($user);

        $checkoutResponse = $this->postJson('/api/checkout/gift-certificate', [
            'amount' => 500,
            'payment_method' => 'tbank_card',
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('checkout_kind', Order::CHECKOUT_KIND_GIFT_CERTIFICATE);

        $orderNumber = $checkoutResponse->json('order_number');

        $this->postJson('/api/payments/webhooks/tbank_card', [
            'event' => 'paid',
            'order_number' => $orderNumber,
            'payment_id' => 'gc-purchase-xyz',
            'event_id' => 'evt-gc-purchase-1',
        ])->assertOk();

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $order->refresh();

        $this->assertNotNull($order->gift_certificate_issued_at);
        $this->assertSame(Order::CHECKOUT_KIND_GIFT_CERTIFICATE, $order->checkout_kind);

        $this->assertDatabaseHas('gift_certificates', [
            'owner_user_id' => $user->id,
            'initial_amount' => 500,
            'balance_remaining' => 500,
        ]);

        $detail = $this->getJson("/api/orders/{$orderNumber}")
            ->assertOk()
            ->assertJsonPath('checkout_kind', Order::CHECKOUT_KIND_GIFT_CERTIFICATE);

        $this->assertNotEmpty($detail->json('purchased_gift_certificate.code'));
        $this->assertEquals(500.0, (float) $detail->json('purchased_gift_certificate.initial_amount'));
        $this->assertEquals(500.0, (float) $detail->json('purchased_gift_certificate.balance_remaining'));
    }
}
