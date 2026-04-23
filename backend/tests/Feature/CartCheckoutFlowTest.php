<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ShopDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_complete_cart_checkout_and_fetch_order_details(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-flow';

        $cartResponse = $this->getJson("/api/cart?session_id={$sessionId}");
        $cartResponse->assertOk();
        $cartResponse->assertJsonPath('total_items', 0);

        $addItemResponse = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 2,
        ]);
        $addItemResponse->assertOk();
        $addItemResponse->assertJsonPath('total_items', 2);
        $addItemResponse->assertJsonPath('items.0.product_slug', 'city-frame-one');

        $itemId = $addItemResponse->json('items.0.id');

        $updateItemResponse = $this->patchJson("/api/cart/items/{$itemId}", [
            'session_id' => $sessionId,
            'qty' => 3,
        ]);
        $updateItemResponse->assertOk();
        $updateItemResponse->assertJsonPath('total_items', 3);

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+79998887766',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'comment' => 'Call before delivery',
        ]);
        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('status', 'new');
        $checkoutResponse->assertJsonPath('order_status', 'placed');
        $checkoutResponse->assertJsonPath('payment_status', 'pending');
        $checkoutResponse->assertJsonPath('fulfillment_status', 'pending');
        $checkoutResponse->assertJsonPath('refund_status', 'none');
        $checkoutResponse->assertJsonPath('payment_transaction_status', 'pending');

        $orderNumber = $checkoutResponse->json('order_number');

        $this->assertDatabaseHas('orders', [
            'order_number' => $orderNumber,
            'session_id' => $sessionId,
            'customer_name' => 'Test Buyer',
            'status' => 'new',
            'order_status' => 'placed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'refund_status' => 'none',
        ]);

        $this->assertDatabaseHas('carts', [
            'session_id' => $sessionId,
            'status' => 'checked_out',
        ]);

        $ordersResponse = $this->getJson("/api/orders?session_id={$sessionId}");
        $ordersResponse->assertOk();
        $ordersResponse->assertJsonPath('data.0.order_number', $orderNumber);

        $orderDetailsResponse = $this->getJson("/api/orders/{$orderNumber}?session_id={$sessionId}");
        $orderDetailsResponse->assertOk();
        $orderDetailsResponse->assertJsonPath('order_number', $orderNumber);
        $orderDetailsResponse->assertJsonPath('order_status', 'placed');
        $orderDetailsResponse->assertJsonPath('payment_status', 'pending');
        $orderDetailsResponse->assertJsonPath('payment_transaction_status', 'pending');
        $orderDetailsResponse->assertJsonPath('payment_transactions.0.provider', 'tbank_card');
        $orderDetailsResponse->assertJsonPath('payment_transactions.0.status', 'pending');
        $orderDetailsResponse->assertJsonPath('items.0.product_slug', 'city-frame-one');
        $orderDetailsResponse->assertJsonPath('items.0.qty', 3);
    }

    public function test_cannot_add_more_items_than_product_stock(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-stock-limit';

        $response = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'vault-signature',
            'qty' => 6,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Недостаточный остаток товара на складе.');
    }

    public function test_can_remove_item_from_cart_and_totals_are_recalculated(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-remove-item';

        $addItemResponse = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 2,
        ]);
        $addItemResponse->assertOk();
        $addItemResponse->assertJsonPath('total_items', 2);

        $itemId = $addItemResponse->json('items.0.id');

        $removeItemResponse = $this->deleteJson("/api/cart/items/{$itemId}?session_id={$sessionId}");
        $removeItemResponse->assertOk();
        $removeItemResponse->assertJsonPath('total_items', 0);
        $removeItemResponse->assertJsonPath('subtotal', 0);
        $removeItemResponse->assertJsonPath('total', 0);
        $removeItemResponse->assertJsonCount(0, 'items');
    }

    public function test_cart_refreshes_item_price_after_product_price_changes(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-price-refresh';

        $addItemResponse = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ]);
        $addItemResponse->assertOk();
        $addItemResponse->assertJsonPath('items.0.unit_price', 12990);

        $product = Product::query()->where('slug', 'aero-pulse-300')->firstOrFail();
        $product->price = 11990;
        $product->save();

        $cartResponse = $this->getJson("/api/cart?session_id={$sessionId}");
        $cartResponse->assertOk();
        $cartResponse->assertJsonPath('items.0.unit_price', 11990);
        $cartResponse->assertJsonPath('items.0.total_price', 11990);
        $cartResponse->assertJsonPath('subtotal', 11990);
        $cartResponse->assertJsonPath('total', 11990);
    }

    public function test_cart_marks_item_as_unavailable_when_product_goes_out_of_stock(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-out-of-stock';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ])->assertOk();

        $product = Product::query()->where('slug', 'aero-pulse-300')->firstOrFail();
        $product->stock = 0;
        $product->save();

        $cartResponse = $this->getJson("/api/cart?session_id={$sessionId}");
        $cartResponse->assertOk();
        $cartResponse->assertJsonPath('items.0.available', false);
        $cartResponse->assertJsonPath('items.0.available_stock', 0);
        $cartResponse->assertJsonPath('items.0.availability_message', 'Нет в наличии.');
    }

    public function test_checkout_fails_when_cart_contains_unavailable_items(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-unavailable-checkout';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ])->assertOk();

        $product = Product::query()->where('slug', 'aero-pulse-300')->firstOrFail();
        $product->stock = 0;
        $product->save();

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+79998887766',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ]);

        $checkoutResponse->assertStatus(422);
        $checkoutResponse->assertJsonValidationErrors(['cart']);
        $checkoutResponse->assertJsonPath(
            'errors.cart.0',
            'Один из товаров в корзине закончился. Удалите его или перенесите в избранное.',
        );
    }

    public function test_checkout_validates_customer_fields(): void
    {
        $response = $this->postJson('/api/checkout', [
            'session_id' => 'test-session-validation',
            'customer_name' => '',
            'customer_email' => 'not-an-email',
            'customer_phone' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'customer_name',
            'customer_email',
            'customer_phone',
            'delivery_method',
            'payment_method',
        ]);
    }

    public function test_checkout_fails_when_cart_is_empty(): void
    {
        $response = $this->postJson('/api/checkout', [
            'session_id' => 'test-session-empty-cart',
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+79998887766',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cart']);
    }

    public function test_orders_index_supports_status_filter_and_pagination(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-orders-filters';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $firstCheckout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Buyer One',
            'customer_email' => 'buyer1@example.com',
            'customer_phone' => '+79990000001',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ])->assertCreated();

        $firstOrderNumber = $firstCheckout->json('order_number');

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ])->assertOk();

        $secondCheckout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Buyer Two',
            'customer_email' => 'buyer2@example.com',
            'customer_phone' => '+79990000002',
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
        ])->assertCreated();

        $secondOrderNumber = $secondCheckout->json('order_number');

        $this->assertNotSame($firstOrderNumber, $secondOrderNumber);

        $this->assertDatabaseHas('orders', [
            'order_number' => $firstOrderNumber,
        ]);

        $this->assertDatabaseHas('orders', [
            'order_number' => $secondOrderNumber,
        ]);

        $this->assertDatabaseHas('orders', [
            'order_number' => $firstOrderNumber,
            'status' => 'new',
        ]);

        $this->assertDatabaseHas('orders', [
            'order_number' => $secondOrderNumber,
            'status' => 'new',
        ]);

        $allOrdersResponse = $this->getJson("/api/orders?session_id={$sessionId}&per_page=1");
        $allOrdersResponse->assertOk();
        $allOrdersResponse->assertJsonPath('per_page', 1);
        $allOrdersResponse->assertJsonPath('total', 2);
        $allOrdersResponse->assertJsonCount(1, 'data');

        $firstOrder = \App\Models\Order::query()->where('order_number', $firstOrderNumber)->firstOrFail();
        $firstOrder->status = 'paid';
        $firstOrder->save();

        $paidOrdersResponse = $this->getJson("/api/orders?session_id={$sessionId}&status=paid");
        $paidOrdersResponse->assertOk();
        $paidOrdersResponse->assertJsonPath('total', 1);
        $paidOrdersResponse->assertJsonPath('data.0.order_number', $firstOrderNumber);
        $paidOrdersResponse->assertJsonPath('data.0.status', 'paid');
    }

    public function test_can_add_variant_item_and_preserve_size_in_order_details(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-variants';

        $product = Product::query()
            ->with('variants')
            ->where('slug', 'cloud-step-v2')
            ->firstOrFail();

        $variant = $product->variants->sortBy('sort_order')->firstOrFail();
        $expectedVariantLabel = $variant->color_label
            ? "{$variant->color_label} · {$variant->size_label}"
            : $variant->size_label;

        $addItemResponse = $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'cloud-step-v2',
            'product_variant_id' => $variant->id,
            'qty' => 1,
        ]);

        $addItemResponse->assertOk();
        $addItemResponse->assertJsonPath('items.0.product_variant_id', $variant->id);
        $addItemResponse->assertJsonPath('items.0.variant_label', $expectedVariantLabel);

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Variant Buyer',
            'customer_email' => 'variant.buyer@example.com',
            'customer_phone' => '+79990000099',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
        ]);
        $checkoutResponse->assertCreated();

        $orderNumber = $checkoutResponse->json('order_number');

        $orderDetailsResponse = $this->getJson("/api/orders/{$orderNumber}?session_id={$sessionId}");
        $orderDetailsResponse->assertOk();
        $orderDetailsResponse->assertJsonPath('items.0.variant_label', $expectedVariantLabel);
    }

    public function test_checkout_applies_welcome10_discount_and_delivery_total(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-checkout-v2';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Promo Buyer',
            'customer_email' => 'promo@example.com',
            'customer_phone' => '+79990000111',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'promo_code' => 'WELCOME10',
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('subtotal', 9990);
        $checkoutResponse->assertJsonPath('discount_total', 999);
        $checkoutResponse->assertJsonPath('delivery_total', 490);
        $checkoutResponse->assertJsonPath('total', 9481);
        $checkoutResponse->assertJsonPath('delivery_method', 'courier');
        $checkoutResponse->assertJsonPath('payment_method', 'card');
        $checkoutResponse->assertJsonPath('promo_code', 'WELCOME10');
    }

    public function test_checkout_options_are_loaded_from_database(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $response = $this->getJson('/api/checkout/options');
        $response->assertOk();
        $response->assertJsonPath('delivery_methods.0.code', 'courier');
        $response->assertJsonPath('payment_methods.0.code', 'tbank_card');
        $response->assertJsonPath('payment_methods.0.is_test_mode', true);
        $response->assertJsonPath('promo_codes.0.code', 'WELCOME10');
    }

    public function test_checkout_fails_with_unknown_promo_code(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-bad-promo';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkoutResponse = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Bad Promo Buyer',
            'customer_email' => 'badpromo@example.com',
            'customer_phone' => '+79990000122',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'promo_code' => 'UNKNOWN',
        ]);

        $checkoutResponse->assertStatus(422);
        $checkoutResponse->assertJsonValidationErrors(['promo_code']);
    }

    public function test_checkout_preview_returns_promo_status_message(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-preview-promo';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $previewOk = $this->postJson('/api/checkout/preview', [
            'session_id' => $sessionId,
            'delivery_method' => 'courier',
            'promo_code' => 'WELCOME10',
        ]);

        $previewOk->assertOk();
        $previewOk->assertJsonPath('promo.is_applied', true);
        $previewOk->assertJsonPath('promo.message', 'Промокод применен.');

        $previewFail = $this->postJson('/api/checkout/preview', [
            'session_id' => $sessionId,
            'delivery_method' => 'courier',
            'promo_code' => 'INVALID',
        ]);

        $previewFail->assertOk();
        $previewFail->assertJsonPath('promo.is_applied', false);
        $previewFail->assertJsonPath('promo.message', 'Промокод не найден или неактивен.');
    }

    public function test_checkout_preview_without_promo_code_does_not_fail(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-preview-without-promo';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $preview = $this->postJson('/api/checkout/preview', [
            'session_id' => $sessionId,
            'delivery_method' => 'courier',
        ]);

        $preview->assertOk();
        $preview->assertJsonPath('promo.code', null);
        $preview->assertJsonPath('promo.is_applied', false);
    }

    public function test_checkout_stores_first_touch_attribution(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-attribution';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $checkout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Attribution Buyer',
            'customer_email' => 'attr@example.com',
            'customer_phone' => '+79990000199',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'attribution' => [
                'source' => 'google',
                'medium' => 'cpc',
                'campaign' => 'brand-search',
                'content' => 'headline-a',
                'term' => 'shoria sneakers',
                'landing_path' => '/catalog?q=shoria',
                'referrer_host' => 'google.com',
            ],
        ]);

        $checkout->assertCreated();

        $this->assertDatabaseHas('orders', [
            'order_number' => $checkout->json('order_number'),
            'first_touch_source' => 'google',
            'first_touch_medium' => 'cpc',
            'first_touch_campaign' => 'brand-search',
            'first_touch_content' => 'headline-a',
            'first_touch_term' => 'shoria sneakers',
            'first_touch_referrer_host' => 'google.com',
            'first_touch_landing_path' => '/catalog?q=shoria',
        ]);
    }

    public function test_promo_code_can_be_used_only_once_per_customer_email(): void
    {
        $this->seed(ShopDemoSeeder::class);

        $sessionId = 'test-session-promo-once';

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'city-frame-one',
            'qty' => 1,
        ])->assertOk();

        $firstCheckout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Promo Once Buyer',
            'customer_email' => 'promo-once@example.com',
            'customer_phone' => '+79990000133',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'promo_code' => 'WELCOME10',
        ]);
        $firstCheckout->assertCreated();

        $this->postJson('/api/cart/items', [
            'session_id' => $sessionId,
            'product_slug' => 'aero-pulse-300',
            'qty' => 1,
        ])->assertOk();

        $secondCheckout = $this->postJson('/api/checkout', [
            'session_id' => $sessionId,
            'customer_name' => 'Promo Once Buyer',
            'customer_email' => 'promo-once@example.com',
            'customer_phone' => '+79990000133',
            'delivery_method' => 'courier',
            'payment_method' => 'card',
            'promo_code' => 'WELCOME10',
        ]);

        $secondCheckout->assertStatus(422);
        $secondCheckout->assertJsonValidationErrors(['promo_code']);
        $secondCheckout->assertJsonPath('errors.promo_code.0', 'Промокод уже использован для этого email.');
    }
}
