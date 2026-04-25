<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Category;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Analytics\AttributionData;
use App\Support\Delivery\DeliveryGatewayRegistry;
use App\Support\Loyalty\LoyaltyProgramService;
use App\Support\Payments\PaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class CheckoutController extends Controller
{
    public function __construct(
        private PaymentGatewayRegistry $paymentGateways,
        private DeliveryGatewayRegistry $deliveryGateways,
        private LoyaltyProgramService $loyaltyProgram,
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $loyaltySetting = $this->loyaltyProgram->getSetting();

        $deliveryMethods = DeliveryMethod::query()
            ->with('provider')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (DeliveryMethod $method) => $this->isDeliveryMethodAvailable($method))
            ->map(fn (DeliveryMethod $method) => [
                'code' => $method->code,
                'name' => $method->name,
                'fee' => $this->resolveDeliveryFee($method, 0),
                'provider_code' => $method->provider_code,
                'provider_mode' => $method->provider?->mode,
                'is_test_mode' => $method->provider?->mode === 'sandbox',
            ])
            ->values();

        $paymentMethods = PaymentProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentProvider $provider) => $this->paymentGateways->for($provider)->toCheckoutOption($provider))
            ->values();

        $promoCodes = PromoCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->filter(fn (PromoCode $promo) => $this->isPromoDateValid($promo))
            ->map(fn (PromoCode $promo) => [
                'code' => $promo->code,
                'name' => $promo->name,
                'discount_type' => $promo->discount_type,
                'discount_value' => (float) $promo->discount_value,
                'applies_to' => $promo->applies_to,
                'min_subtotal' => $promo->min_subtotal !== null ? (float) $promo->min_subtotal : null,
                'min_items_count' => $promo->min_items_count,
                'max_discount_amount' => $promo->max_discount_amount !== null ? (float) $promo->max_discount_amount : null,
                'free_delivery' => (bool) $promo->free_delivery,
            ])
            ->values();

        return response()->json([
            'delivery_methods' => $deliveryMethods,
            'payment_methods' => $paymentMethods,
            'promo_codes' => $promoCodes,
            'loyalty' => [
                ...$this->loyaltyProgram->infoPayload($loyaltySetting),
                'account' => $this->loyaltyProgram->userSnapshot($user, $loyaltySetting),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_method' => ['required', 'string', 'max:32'],
            'payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethodCodes())],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'loyalty_points_to_spend' => ['nullable', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'attribution' => ['nullable', 'array'],
        ]);

        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity(
            $request,
            $validated['session_id'] ?? null,
        );

        $cartQuery = Cart::query()
            ->with('items')
            ->where('status', 'open');

        if ($user) {
            $cartQuery->where('user_id', $user->id);
        } else {
            $cartQuery->whereNull('user_id')->where('session_id', $sessionId);
        }

        $cart = $cartQuery->first();

        if (! $cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Корзина пуста или не найдена.',
            ]);
        }

        $unavailableItemMessage = $this->resolveUnavailableCartItemMessage($cart);

        if ($unavailableItemMessage) {
            throw ValidationException::withMessages([
                'cart' => $unavailableItemMessage,
            ]);
        }

        $subtotal = (float) $cart->items->sum(fn ($item) => (float) $item->total_price);

        if ((float) $cart->subtotal !== $subtotal || (float) $cart->total !== $subtotal) {
            $cart->subtotal = $subtotal;
            $cart->total = $subtotal;
            $cart->save();
        }

        $deliveryMethod = $this->resolveDeliveryMethod($validated['delivery_method']);
        $paymentProvider = $this->resolvePaymentProvider($validated['payment_method']);
        $paymentGateway = $this->paymentGateways->for($paymentProvider);
        $promoCode = $this->resolvePromoCode(
            $validated['promo_code'] ?? null,
            $cart,
            $subtotal,
            $validated['customer_email'],
            $user,
        );
        $discountTotal = $promoCode ? $this->calculateDiscount($promoCode, $cart, $subtotal) : 0.0;
        $loyaltySetting = $this->loyaltyProgram->getSetting();
        $requestedLoyaltyPoints = max(0, (int) ($validated['loyalty_points_to_spend'] ?? 0));
        $subtotalAfterPromo = max(0.0, $subtotal - $discountTotal);
        $loyaltyPointsSpent = 0;
        $loyaltyDiscountTotal = 0.0;
        $loyaltyPointsEarned = 0;
        $loyaltyAccrualBase = 0.0;
        $loyaltyAccrualPercent = 0.0;

        if ($requestedLoyaltyPoints > 0 && ! $user) {
            throw ValidationException::withMessages([
                'loyalty_points_to_spend' => 'Чтобы списывать баллы, войдите в аккаунт.',
            ]);
        }

        if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting)) {
            $maxLoyaltyPoints = $this->loyaltyProgram->resolveMaxRedeemPoints($user, $subtotalAfterPromo, $loyaltySetting);
            $loyaltyPointsSpent = min($requestedLoyaltyPoints, $maxLoyaltyPoints);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);
            $loyaltyDiscountTotal = min($loyaltyDiscountTotal, $subtotalAfterPromo);

            $pointValue = max(0.01, (float) $loyaltySetting->point_value);
            $loyaltyPointsSpent = (int) floor($loyaltyDiscountTotal / $pointValue);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);

            $loyaltyAccrualBase = max(0.0, $subtotalAfterPromo - $loyaltyDiscountTotal);
            $loyaltyAccrualPercent = $this->loyaltyProgram->resolveEffectiveAccrualPercent($user, $loyaltySetting);
            $loyaltyPointsEarned = $this->loyaltyProgram->resolveAccrualPoints($user, $loyaltyAccrualBase, $loyaltySetting);
        }

        $deliveryTotal = $this->resolveDeliveryFee($deliveryMethod, $subtotal);
        if ($promoCode?->free_delivery) {
            $deliveryTotal = 0.0;
        }
        $orderTotal = max(0, $subtotal - $discountTotal - $loyaltyDiscountTotal + $deliveryTotal);
        $attribution = AttributionData::normalize($validated['attribution'] ?? null);

        $order = DB::transaction(function () use (
            $cart,
            $validated,
            $user,
            $sessionId,
            $deliveryMethod,
            $paymentProvider,
            $paymentGateway,
            $promoCode,
            $subtotal,
            $discountTotal,
            $loyaltyPointsSpent,
            $loyaltyDiscountTotal,
            $loyaltyPointsEarned,
            $loyaltyAccrualBase,
            $loyaltyAccrualPercent,
            $loyaltySetting,
            $deliveryTotal,
            $orderTotal,
            $attribution,
        ): Order {
            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user?->id,
                'session_id' => $sessionId ?? $cart->session_id,
                'status' => 'new',
                'order_status' => 'placed',
                'payment_status' => $paymentGateway->initialPaymentStatus($paymentProvider),
                'fulfillment_status' => 'pending',
                'refund_status' => 'none',
                'delivery_method' => $deliveryMethod->code,
                'payment_method' => $this->resolvePaymentMethodKind($paymentProvider),
                'currency' => $cart->currency,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'loyalty_points_spent' => $loyaltyPointsSpent,
                'loyalty_discount_total' => $loyaltyDiscountTotal,
                'loyalty_points_earned' => $loyaltyPointsEarned,
                'delivery_total' => $deliveryTotal,
                'total' => $orderTotal,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                ...$attribution,
                'comment' => $validated['comment'] ?? null,
                'promo_code' => $promoCode?->code,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $cartItem) {
                $order->items()->create([
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'product_name' => $cartItem->product_name,
                    'product_slug' => $cartItem->product_slug,
                    'variant_label' => $cartItem->variant_label,
                    'image_url' => $cartItem->image_url,
                    'qty' => $cartItem->qty,
                    'unit_price' => $cartItem->unit_price,
                    'total_price' => $cartItem->total_price,
                ]);
            }

            PaymentTransaction::query()->create([
                'order_id' => $order->id,
                'provider' => $paymentProvider->code,
                'payment_method' => $paymentProvider->driver,
                'type' => 'charge',
                'status' => $paymentGateway->initialTransactionStatus($paymentProvider),
                'currency' => $order->currency,
                'amount' => $order->total,
                'idempotence_key' => (string) Str::uuid(),
                'meta' => array_merge(
                    $paymentGateway->buildTransactionMeta($order, $paymentProvider),
                    [
                        'customer_email' => strtolower(trim($validated['customer_email'])),
                        'delivery_method' => $deliveryMethod->code,
                    ],
                ),
            ]);

            $cart->status = 'checked_out';
            $cart->save();

            if ($promoCode) {
                PromoCode::query()->whereKey($promoCode->id)->increment('used_count');

                PromoCodeUsage::query()->create([
                    'promo_code_id' => $promoCode->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'session_id' => $sessionId ?? $cart->session_id,
                    'customer_email' => strtolower(trim($validated['customer_email'])),
                    'used_at' => now(),
                ]);
            }

            if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting)) {
                $freshUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

                if ($freshUser && $loyaltyPointsSpent > 0) {
                    $this->loyaltyProgram->applyRedeem(
                        $freshUser,
                        $order,
                        $loyaltyPointsSpent,
                        $loyaltyDiscountTotal,
                    );
                }

                if ($freshUser && $loyaltyPointsEarned > 0) {
                    $this->loyaltyProgram->applyAccrual(
                        $freshUser,
                        $order,
                        $loyaltyPointsEarned,
                        $loyaltyAccrualBase,
                        $loyaltyAccrualPercent,
                    );
                }
            }

            return $order->fresh(['items', 'paymentTransactions']);
        });

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'refund_status' => $order->refund_status,
            'delivery_method' => $order->delivery_method,
            'payment_method' => $order->payment_method,
            'payment_transaction_status' => $order->paymentTransactions->first()?->status,
            'promo_code' => $order->promo_code,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'loyalty_points_spent' => (int) $order->loyalty_points_spent,
            'loyalty_discount_total' => (float) $order->loyalty_discount_total,
            'loyalty_points_earned' => (int) $order->loyalty_points_earned,
            'delivery_total' => (float) $order->delivery_total,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'items_count' => $order->items->sum('qty'),
            'loyalty_account' => $user ? $this->loyaltyProgram->userSnapshot($user->fresh(), $loyaltySetting) : null,
        ], 201);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:64'],
            'delivery_method' => ['required', 'string', 'max:32'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'loyalty_points_to_spend' => ['nullable', 'integer', 'min:0'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity(
            $request,
            $validated['session_id'] ?? null,
        );

        $cartQuery = Cart::query()
            ->with('items')
            ->where('status', 'open');

        if ($user) {
            $cartQuery->where('user_id', $user->id);
        } else {
            $cartQuery->whereNull('user_id')->where('session_id', $sessionId);
        }

        $cart = $cartQuery->first();
        $subtotal = $cart
            ? (float) $cart->items->sum(fn ($item) => (float) $item->total_price)
            : 0.0;

        if ($cart && ((float) $cart->subtotal !== $subtotal || (float) $cart->total !== $subtotal)) {
            $cart->subtotal = $subtotal;
            $cart->total = $subtotal;
            $cart->save();
        }

        $deliveryMethod = $this->resolveDeliveryMethod($validated['delivery_method']);
        $requestedPromoCode = isset($validated['promo_code'])
            ? strtoupper(trim((string) $validated['promo_code']))
            : null;

        $promoCode = null;
        $promoMessage = null;
        $promoApplied = false;

        try {
            $promoCode = $this->resolvePromoCode(
                $validated['promo_code'] ?? null,
                $cart,
                $subtotal,
                $validated['customer_email'] ?? null,
                $user,
            );
            if ($promoCode) {
                $promoApplied = true;
                $promoMessage = 'Промокод применен.';
            }
        } catch (ValidationException $exception) {
            $promoMessage = (string) collect($exception->errors())->flatten()->first();
        }

        $discountTotal = $promoCode && $cart ? $this->calculateDiscount($promoCode, $cart, $subtotal) : 0.0;
        $loyaltySetting = $this->loyaltyProgram->getSetting();
        $requestedLoyaltyPoints = max(0, (int) ($validated['loyalty_points_to_spend'] ?? 0));
        $subtotalAfterPromo = max(0.0, $subtotal - $discountTotal);
        $maxLoyaltyPoints = 0;
        $loyaltyPointsSpent = 0;
        $loyaltyDiscountTotal = 0.0;
        $loyaltyPointsEarned = 0;
        $loyaltyAccrualPercent = 0.0;

        if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting)) {
            $maxLoyaltyPoints = $this->loyaltyProgram->resolveMaxRedeemPoints($user, $subtotalAfterPromo, $loyaltySetting);
            $loyaltyPointsSpent = min($requestedLoyaltyPoints, $maxLoyaltyPoints);
            $loyaltyDiscountTotal = min(
                $subtotalAfterPromo,
                $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting),
            );
            $pointValue = max(0.01, (float) $loyaltySetting->point_value);
            $loyaltyPointsSpent = (int) floor($loyaltyDiscountTotal / $pointValue);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);
            $accrualBase = max(0.0, $subtotalAfterPromo - $loyaltyDiscountTotal);
            $loyaltyAccrualPercent = $this->loyaltyProgram->resolveEffectiveAccrualPercent($user, $loyaltySetting);
            $loyaltyPointsEarned = $this->loyaltyProgram->resolveAccrualPoints($user, $accrualBase, $loyaltySetting);
        }

        $deliveryTotal = $this->resolveDeliveryFee($deliveryMethod, $subtotal);
        if ($promoCode?->free_delivery) {
            $deliveryTotal = 0.0;
        }
        $total = max(0, $subtotal - $discountTotal - $loyaltyDiscountTotal + $deliveryTotal);

        return response()->json([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'loyalty_discount_total' => $loyaltyDiscountTotal,
            'delivery_total' => $deliveryTotal,
            'total' => $total,
            'currency' => $cart?->currency ?? 'RUB',
            'promo' => [
                'code' => $promoCode?->code ?? $requestedPromoCode,
                'is_applied' => $promoApplied,
                'message' => $promoMessage,
            ],
            'loyalty' => [
                'is_enabled' => $this->loyaltyProgram->isEnabled($loyaltySetting),
                'requested_points' => $requestedLoyaltyPoints,
                'applied_points' => $loyaltyPointsSpent,
                'max_points_to_spend' => $maxLoyaltyPoints,
                'points_balance' => $user ? (int) $user->loyalty_points_balance : 0,
                'points_to_earn' => $loyaltyPointsEarned,
                'accrual_percent' => $loyaltyAccrualPercent,
                'account' => $this->loyaltyProgram->userSnapshot($user, $loyaltySetting),
            ],
        ]);
    }

    private function resolveDeliveryMethod(string $code): DeliveryMethod
    {
        $deliveryMethod = DeliveryMethod::query()
            ->with('provider')
            ->where('is_active', true)
            ->where('code', $code)
            ->first();

        if (! $deliveryMethod || ! $this->isDeliveryMethodAvailable($deliveryMethod)) {
            throw ValidationException::withMessages([
                'delivery_method' => 'Выбранный способ доставки недоступен.',
            ]);
        }

        return $deliveryMethod;
    }

    private function resolveUnavailableCartItemMessage(Cart $cart): ?string
    {
        $productIds = $cart->items->pluck('product_id')->filter()->unique()->all();

        if ($productIds === []) {
            return 'Корзина содержит недоступные товары. Обновите состав заказа.';
        }

        $products = Product::query()
            ->with('variants:id,product_id,size_label,price,stock,is_active,sort_order')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($cart->items as $item) {
            $product = $products->get($item->product_id);
            $variant = $item->product_variant_id && $product
                ? $product->variants->firstWhere('id', $item->product_variant_id)
                : null;
            $availability = $this->resolveItemAvailability($product, $variant, (int) $item->qty);

            if (! $availability['available']) {
                return $availability['message'] ?? 'Корзина содержит недоступные товары. Обновите состав заказа.';
            }
        }

        return null;
    }

    private function resolveItemAvailability(?Product $product, ?ProductVariant $variant, int $qty): array
    {
        if (! $product || ! $product->is_active) {
            return [
                'available' => false,
                'message' => 'Один из товаров в корзине больше недоступен. Обновите состав заказа.',
            ];
        }

        if ($variant && ! $variant->is_active) {
            return [
                'available' => false,
                'message' => 'Один из выбранных вариантов товара больше недоступен. Обновите корзину.',
            ];
        }

        $availableStock = max(0, (int) ($variant?->stock ?? $product->stock));

        if ($availableStock <= 0) {
            return [
                'available' => false,
                'message' => 'Один из товаров в корзине закончился. Удалите его или перенесите в избранное.',
            ];
        }

        if ($qty > $availableStock) {
            return [
                'available' => false,
                'message' => "Количество одного из товаров в корзине превышает остаток ({$availableStock} шт.).",
            ];
        }

        return [
            'available' => true,
            'message' => null,
        ];
    }

    private function resolvePaymentProvider(string $code): PaymentProvider
    {
        $aliases = [
            'card' => PaymentProvider::query()
                ->where('is_active', true)
                ->where('driver', '!=', 'manual_cash')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->value('code'),
            'cash' => PaymentProvider::query()
                ->where('is_active', true)
                ->where('driver', 'manual_cash')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->value('code'),
        ];

        $resolvedCode = $aliases[$code] ?? $code;

        $provider = PaymentProvider::query()
            ->where('code', $resolvedCode)
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_method' => 'Выбранный способ оплаты недоступен.',
            ]);
        }

        return $provider;
    }

    private function allowedPaymentMethodCodes(): array
    {
        return array_values(array_unique(array_merge(
            ['card', 'cash'],
            PaymentProvider::query()
                ->where('is_active', true)
                ->pluck('code')
                ->all(),
        )));
    }

    private function isDeliveryMethodAvailable(DeliveryMethod $method): bool
    {
        if (! $method->provider_code) {
            return true;
        }

        return $method->provider?->is_active === true;
    }

    private function resolveDeliveryFee(DeliveryMethod $method, float $subtotal): float
    {
        if (! $method->provider_code || ! $method->provider) {
            return (float) $method->fee;
        }

        return $this->deliveryGateways
            ->for($method->provider)
            ->resolveFee($method->provider, $method, $subtotal);
    }

    private function resolvePaymentMethodKind(PaymentProvider $provider): string
    {
        return $provider->driver === 'manual_cash' ? 'cash' : 'card';
    }

    private function resolvePromoCode(
        ?string $code,
        ?Cart $cart,
        float $subtotal,
        ?string $customerEmail = null,
        ?User $user = null,
    ): ?PromoCode
    {
        if (! $code) {
            return null;
        }

        $normalizedCode = strtoupper(trim($code));

        /** @var PromoCode|null $promo */
        $promo = PromoCode::query()
            ->where('is_active', true)
            ->where('code', $normalizedCode)
            ->lockForUpdate()
            ->first();

        if (! $promo) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод не найден или неактивен.',
            ]);
        }

        if (! $this->isPromoDateValid($promo)) {
            throw ValidationException::withMessages([
                'promo_code' => 'Срок действия промокода истек или еще не начался.',
            ]);
        }

        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод уже исчерпал лимит использований.',
            ]);
        }

        if ($customerEmail && $this->hasPromoBeenUsedByCustomer($promo, $customerEmail)) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод уже использован для этого email.',
            ]);
        }

        if ($promo->min_subtotal !== null && $subtotal < (float) $promo->min_subtotal) {
            throw ValidationException::withMessages([
                'promo_code' => 'Сумма заказа недостаточна для этого промокода.',
            ]);
        }

        if ($promo->min_items_count !== null && $cart) {
            $itemsCount = (int) $cart->items->sum('qty');
            if ($itemsCount < (int) $promo->min_items_count) {
                throw ValidationException::withMessages([
                    'promo_code' => 'Недостаточное количество товаров для этого промокода.',
                ]);
            }
        }

        if ($promo->first_order_only && ! $this->isFirstOrder($user, $customerEmail)) {
            throw ValidationException::withMessages([
                'promo_code' => 'Промокод доступен только для первого заказа.',
            ]);
        }

        return $promo;
    }

    private function hasPromoBeenUsedByCustomer(PromoCode $promo, string $customerEmail): bool
    {
        $normalizedEmail = strtolower(trim($customerEmail));

        return PromoCodeUsage::query()
            ->where('promo_code_id', $promo->id)
            ->where('customer_email', $normalizedEmail)
            ->exists();
    }

    private function isPromoDateValid(PromoCode $promo): bool
    {
        $now = now();

        if ($promo->starts_at && $promo->starts_at->gt($now)) {
            return false;
        }

        if ($promo->ends_at && $promo->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    private function calculateDiscount(PromoCode $promo, Cart $cart, float $subtotal): float
    {
        $discountBase = $subtotal;

        if ($promo->applies_to === 'items') {
            $discountBase = $this->calculateEligibleItemsSubtotal($promo, $cart);
        }

        $discount = match ($promo->discount_type) {
            'fixed_percent' => $discountBase * ((float) $promo->discount_value / 100),
            'fixed_amount' => (float) $promo->discount_value,
            default => 0.0,
        };

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return round(min($discountBase, max(0, $discount)), 2);
    }

    private function calculateEligibleItemsSubtotal(PromoCode $promo, Cart $cart): float
    {
        $includedProductIds = $this->normalizeIntArray($promo->included_product_ids);
        $includedCategoryIds = $this->resolveCategoryIdsWithDescendants(
            $this->normalizeIntArray($promo->included_category_ids),
        );
        $includedBrandIds = $this->normalizeIntArray($promo->included_brand_ids);

        $hasProductFilter = $includedProductIds !== [];
        $hasCategoryFilter = $includedCategoryIds !== [];
        $hasBrandFilter = $includedBrandIds !== [];

        if (! $hasProductFilter && ! $hasCategoryFilter && ! $hasBrandFilter) {
            return (float) $cart->items->sum(fn ($item) => (float) $item->total_price);
        }

        $products = Product::query()
            ->with('categories:id')
            ->whereIn('id', $cart->items->pluck('product_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $isAllMode = $promo->items_match_mode === 'all';
        $eligibleSubtotal = 0.0;

        foreach ($cart->items as $item) {
            $product = $products->get($item->product_id);
            if (! $product) {
                continue;
            }

            $productCategoryIds = array_unique([
                (int) $product->category_id,
                ...$product->categories->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ]);

            $matches = [];

            if ($hasProductFilter) {
                $matches[] = in_array((int) $product->id, $includedProductIds, true);
            }

            if ($hasCategoryFilter) {
                $matches[] = count(array_intersect($productCategoryIds, $includedCategoryIds)) > 0;
            }

            if ($hasBrandFilter) {
                $matches[] = in_array((int) $product->brand_id, $includedBrandIds, true);
            }

            if ($matches === []) {
                $matchesItem = true;
            } elseif ($isAllMode) {
                $matchesItem = ! in_array(false, $matches, true);
            } else {
                $matchesItem = in_array(true, $matches, true);
            }

            if ($matchesItem) {
                $eligibleSubtotal += (float) $item->total_price;
            }
        }

        return round($eligibleSubtotal, 2);
    }

    private function resolveCategoryIdsWithDescendants(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $resolved = array_values(array_unique($categoryIds));
        $frontier = $resolved;

        while ($frontier !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $newChildren = array_values(array_diff($children, $resolved));
            if ($newChildren === []) {
                break;
            }

            $resolved = array_values(array_unique([...$resolved, ...$newChildren]));
            $frontier = $newChildren;
        }

        return $resolved;
    }

    private function normalizeIntArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function ($item): ?int {
            if (is_int($item)) {
                return $item;
            }

            if (is_string($item) && is_numeric($item)) {
                return (int) $item;
            }

            return null;
        }, $value))));
    }

    private function isFirstOrder(?User $user, ?string $customerEmail): bool
    {
        $query = Order::query()->where('order_status', '!=', 'cancelled');

        if ($user) {
            return ! (clone $query)->where('user_id', $user->id)->exists();
        }

        if (! $customerEmail) {
            return true;
        }

        $normalizedEmail = strtolower(trim($customerEmail));

        return ! (clone $query)
            ->where('customer_email', $normalizedEmail)
            ->exists();
    }

    private function resolveIdentity(Request $request, ?string $sessionFromPayload = null): array
    {
        $user = $this->resolveAuthenticatedUser($request);

        $sessionId = (string) (
            $sessionFromPayload
            ?? $request->query('session_id')
            ?? $request->header('X-Session-Id')
        );

        if (! $user && $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => 'session_id обязателен для гостевого checkout.',
            ]);
        }

        return [
            'user' => $user,
            'session_id' => $sessionId !== '' ? $sessionId : null,
        ];
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        if ($user) {
            return $user;
        }

        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken || $accessToken->tokenable_type !== User::class) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    private function generateOrderNumber(): string
    {
        return 'SH' . now()->format('ymdHis') . random_int(100, 999);
    }
}
