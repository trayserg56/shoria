<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryMethod;
use App\Models\GiftCertificate;
use App\Models\GiftCertificateRedemption;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Analytics\AttributionData;
use App\Support\Delivery\DeliveryGatewayRegistry;
use App\Support\Delivery\Gateways\CdekDeliveryGateway;
use App\Support\Loyalty\LoyaltyProgramService;
use App\Support\Payments\PaymentGatewayRegistry;
use App\Support\Store\StoreFeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
    ) {}

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
                'method_type' => $method->method_type,
                'requires_pickup_point' => $method->provider_code === 'cdek' && $method->method_type === 'pickup',
                'requires_address' => $method->method_type === 'courier',
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

        $site = SiteSetting::current();
        $loyaltyInfo = $this->loyaltyProgram->infoPayload($loyaltySetting);
        if (! $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
            $loyaltyInfo['is_enabled'] = false;
        }

        return response()->json([
            'delivery_methods' => $deliveryMethods,
            'payment_methods' => $paymentMethods,
            'promo_codes' => $promoCodes,
            'loyalty' => [
                ...$loyaltyInfo,
                'account' => $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)
                    ? $this->loyaltyProgram->userSnapshot($user, $loyaltySetting)
                    : null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_method' => ['required', 'string', 'max:32'],
            'payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethodCodes())],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'gift_certificate_code' => ['nullable', 'string', 'max:64'],
            'gift_certificate_id' => ['nullable', 'integer', 'exists:gift_certificates,id'],
            'loyalty_points_to_spend' => ['nullable', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'attribution' => ['nullable', 'array'],
            'delivery_city' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_pickup_point_code' => ['nullable', 'string', 'max:60'],
            'delivery_pickup_point_address' => ['nullable', 'string', 'max:255'],
        ]);

        if (
            isset($validated['gift_certificate_code'], $validated['gift_certificate_id'])
            && trim((string) $validated['gift_certificate_code']) !== ''
        ) {
            throw ValidationException::withMessages([
                'gift_certificate_code' => 'Укажите либо код сертификата, либо выберите сертификат из кабинета.',
            ]);
        }

        $site = SiteSetting::current();

        if (! $site->isFeatureEnabled(StoreFeatureFlags::GIFT_CERTIFICATES)) {
            $giftCodeTry = trim((string) ($validated['gift_certificate_code'] ?? ''));
            $giftIdTry = isset($validated['gift_certificate_id']) ? (int) $validated['gift_certificate_id'] : null;
            if ($giftCodeTry !== '' || $giftIdTry) {
                throw ValidationException::withMessages([
                    'gift_certificate_code' => 'Подарочные сертификаты сейчас недоступны.',
                ]);
            }
        }

        if (! $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
            $validated['loyalty_points_to_spend'] = 0;
        }

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
        $subtotalAfterPromo = max(0.0, $subtotal - $discountTotal);

        $giftCertPreview = $this->resolveGiftCertificateForCheckout(
            $validated['gift_certificate_code'] ?? null,
            isset($validated['gift_certificate_id']) ? (int) $validated['gift_certificate_id'] : null,
            $cart,
            $subtotalAfterPromo,
            (string) $validated['customer_email'],
            $user,
            false,
        );
        $giftDiscountTotal = $giftCertPreview
            ? $this->calculateGiftCertificateDiscount($giftCertPreview, $subtotalAfterPromo)
            : 0.0;
        $subtotalAfterGift = max(0.0, $subtotalAfterPromo - $giftDiscountTotal);

        $loyaltySetting = $this->loyaltyProgram->getSetting();
        $requestedLoyaltyPoints = max(0, (int) ($validated['loyalty_points_to_spend'] ?? 0));
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

        if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting) && $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
            $maxLoyaltyPoints = $this->loyaltyProgram->resolveMaxRedeemPoints($user, $subtotalAfterGift, $loyaltySetting);
            $loyaltyPointsSpent = min($requestedLoyaltyPoints, $maxLoyaltyPoints);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);
            $loyaltyDiscountTotal = min($loyaltyDiscountTotal, $subtotalAfterGift);

            $pointValue = max(0.01, (float) $loyaltySetting->point_value);
            $loyaltyPointsSpent = (int) floor($loyaltyDiscountTotal / $pointValue);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);

            $loyaltyAccrualBase = max(0.0, $subtotalAfterGift - $loyaltyDiscountTotal);
            $loyaltyAccrualPercent = $this->loyaltyProgram->resolveEffectiveAccrualPercent($user, $loyaltySetting);
            $loyaltyPointsEarned = $this->loyaltyProgram->resolveAccrualPoints($user, $loyaltyAccrualBase, $loyaltySetting);
        }

        $deliveryCityCode = $this->resolveCityCode($deliveryMethod, $validated['delivery_city'] ?? null);
        $deliveryQuote = $this->resolveDeliveryQuote($deliveryMethod, $subtotal, $deliveryCityCode);
        $deliveryTotal = $deliveryQuote['fee'];
        if ($promoCode?->free_delivery) {
            $deliveryTotal = 0.0;
        }
        $orderTotal = max(0, $subtotal - $discountTotal - $giftDiscountTotal - $loyaltyDiscountTotal + $deliveryTotal);
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
            $giftDiscountTotal,
            $subtotalAfterPromo,
            $loyaltyPointsSpent,
            $loyaltyDiscountTotal,
            $loyaltyPointsEarned,
            $loyaltyAccrualBase,
            $loyaltyAccrualPercent,
            $loyaltySetting,
            $deliveryTotal,
            $deliveryCityCode,
            $deliveryQuote,
            $orderTotal,
            $attribution,
        ): Order {
            $giftCert = $this->resolveGiftCertificateForCheckout(
                $validated['gift_certificate_code'] ?? null,
                isset($validated['gift_certificate_id']) ? (int) $validated['gift_certificate_id'] : null,
                $cart,
                $subtotalAfterPromo,
                (string) $validated['customer_email'],
                $user,
                true,
            );
            $giftDiscountLocked = $giftCert
                ? $this->calculateGiftCertificateDiscount($giftCert, $subtotalAfterPromo)
                : 0.0;
            if (round($giftDiscountLocked, 2) !== round($giftDiscountTotal, 2)) {
                throw ValidationException::withMessages([
                    'gift_certificate_code' => 'Не удалось применить сертификат. Обновите страницу и попробуйте снова.',
                ]);
            }

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user?->id,
                'session_id' => $sessionId ?? $cart->session_id,
                'checkout_kind' => Order::CHECKOUT_KIND_CART,
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
                'gift_certificate_id' => $giftCert?->id,
                'gift_certificate_discount_total' => $giftDiscountLocked,
                'loyalty_points_spent' => $loyaltyPointsSpent,
                'loyalty_discount_total' => $loyaltyDiscountTotal,
                'loyalty_points_earned' => $loyaltyPointsEarned,
                'delivery_total' => $deliveryTotal,
                'delivery_city' => $validated['delivery_city'] ?? null,
                'delivery_city_code' => $deliveryCityCode,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_pickup_point_code' => $validated['delivery_pickup_point_code'] ?? null,
                'delivery_pickup_point_address' => $validated['delivery_pickup_point_address'] ?? null,
                'delivery_period_min' => $deliveryQuote['period_min'],
                'delivery_period_max' => $deliveryQuote['period_max'],
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
                // Повторная блокировка внутри транзакции — защита от race condition
                // когда два запроса одновременно прошли первичную проверку вне транзакции
                $lockedPromo = PromoCode::query()
                    ->whereKey($promoCode->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $lockedPromo ||
                    ! $lockedPromo->is_active ||
                    ($lockedPromo->usage_limit !== null && $lockedPromo->used_count >= $lockedPromo->usage_limit)
                ) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'Промокод уже исчерпал лимит использований.',
                    ]);
                }

                $lockedPromo->increment('used_count');

                PromoCodeUsage::query()->create([
                    'promo_code_id' => $promoCode->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'session_id' => $sessionId ?? $cart->session_id,
                    'customer_email' => strtolower(trim($validated['customer_email'])),
                    'used_at' => now(),
                ]);
            }

            if ($giftCert && $giftDiscountLocked > 0) {
                $newBalance = round((float) $giftCert->balance_remaining - $giftDiscountLocked, 2);
                $giftCert->balance_remaining = max(0, $newBalance);
                if ((float) $giftCert->balance_remaining <= 0) {
                    $giftCert->balance_remaining = 0;
                    $giftCert->status = GiftCertificate::STATUS_DEPLETED;
                }
                $giftCert->save();

                GiftCertificateRedemption::query()->create([
                    'gift_certificate_id' => $giftCert->id,
                    'order_id' => $order->id,
                    'amount' => $giftDiscountLocked,
                ]);
            }

            if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting) && SiteSetting::current()->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
                $freshUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

                if ($freshUser && $loyaltyPointsSpent > 0) {
                    $this->loyaltyProgram->applyRedeem(
                        $freshUser,
                        $order,
                        $loyaltyPointsSpent,
                        $loyaltyDiscountTotal,
                    );
                }

                // Баллы начисляются после оплаты — см. OrderObserver
            }

            return $order->fresh(['items', 'paymentTransactions', 'giftCertificate']);
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
            'delivery_city' => $order->delivery_city,
            'delivery_address' => $order->delivery_address,
            'delivery_pickup_point_code' => $order->delivery_pickup_point_code,
            'delivery_pickup_point_address' => $order->delivery_pickup_point_address,
            'delivery_period_min' => $order->delivery_period_min !== null ? (int) $order->delivery_period_min : null,
            'delivery_period_max' => $order->delivery_period_max !== null ? (int) $order->delivery_period_max : null,
            'payment_method' => $order->payment_method,
            'payment_transaction_status' => $order->paymentTransactions->first()?->status,
            'promo_code' => $order->promo_code,
            'gift_certificate_code' => $order->giftCertificate?->code,
            'gift_certificate_discount_total' => (float) $order->gift_certificate_discount_total,
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

    public function oneClick(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $input = $request->validate([
            'product_slug' => ['required', 'string', 'max:255'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'attribution' => ['nullable', 'array'],
            'delivery_method' => ['nullable', 'string', 'max:32'],
            'payment_method' => ['nullable', 'string', 'max:64'],
        ]);

        $qty = (int) ($input['qty'] ?? 1);

        $defaults = $this->resolveOneClickCheckoutDefaults($user);

        if (
            isset($input['delivery_method'])
            && trim((string) $input['delivery_method']) !== ''
        ) {
            $deliveryCode = $this->resolveDeliveryMethod(trim((string) $input['delivery_method']))->code;
        } else {
            $deliveryCode = $defaults['delivery_method'];
        }

        if (
            isset($input['payment_method'])
            && trim((string) $input['payment_method']) !== ''
        ) {
            $paymentMethod = trim((string) $input['payment_method']);
        } else {
            $paymentMethod = $defaults['payment_method'];
        }

        Validator::make(
            ['payment_method' => $paymentMethod],
            ['payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethodCodes())]],
        )->validate();

        $this->resolvePaymentProvider($paymentMethod);

        $cart = Cart::query()
            ->with('items')
            ->where('status', 'open')
            ->where('user_id', $user->id)
            ->first();

        if (! $cart) {
            $cart = Cart::query()->create([
                'user_id' => $user->id,
                'session_id' => 'user:'.$user->id,
                'status' => 'open',
                'currency' => 'RUB',
                'subtotal' => 0,
                'total' => 0,
            ]);
        }

        $snapshot = $cart->items
            ->map(fn (CartItem $item): array => $item->only([
                'product_id',
                'product_variant_id',
                'product_name',
                'product_slug',
                'variant_label',
                'image_url',
                'qty',
                'unit_price',
                'total_price',
            ]))
            ->all();

        $cart->items()->delete();
        $cart->forceFill([
            'subtotal' => 0,
            'total' => 0,
        ])->save();

        $addBody = json_encode([
            'product_slug' => $input['product_slug'],
            'product_variant_id' => $input['product_variant_id'] ?? null,
            'qty' => $qty,
        ], JSON_THROW_ON_ERROR);

        $cartRequest = Request::create('/api/cart/items', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $addBody);

        $authHeader = $request->header('Authorization');

        if ($authHeader !== null && $authHeader !== '') {
            $cartRequest->headers->set('Authorization', $authHeader);
        }

        $cartRequest->setUserResolver(static function ($guard = null) use ($user): User {
            return $user;
        });

        /** @var JsonResponse $cartResponse */
        $cartResponse = app(CartController::class)->addItem($cartRequest);

        if ($cartResponse->getStatusCode() !== 200) {
            $this->replaceCartItemsPayload($cart, $snapshot);

            return $cartResponse;
        }

        $checkoutPayload = [
            'customer_name' => $defaults['customer_name'],
            'customer_email' => $defaults['customer_email'],
            'customer_phone' => $defaults['customer_phone'],
            'delivery_method' => $deliveryCode,
            'payment_method' => $paymentMethod,
            'promo_code' => null,
            'loyalty_points_to_spend' => 0,
            'comment' => null,
            'attribution' => $input['attribution'] ?? null,
        ];

        $checkoutBody = json_encode($checkoutPayload, JSON_THROW_ON_ERROR);
        $checkoutRequest = Request::create('/api/checkout', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $checkoutBody);

        if ($authHeader !== null && $authHeader !== '') {
            $checkoutRequest->headers->set('Authorization', $authHeader);
        }

        $checkoutRequest->setUserResolver(static function ($guard = null) use ($user): User {
            return $user;
        });

        try {
            $response = $this->store($checkoutRequest);

            if (! $response instanceof JsonResponse) {
                throw new \RuntimeException('checkout store returned unexpected response');
            }

            if ($response->getStatusCode() !== 201) {
                $cart->refresh();
                $this->replaceCartItemsPayload($cart, $snapshot);

                return $response;
            }

            if ($snapshot !== []) {
                $this->createFreshOpenCartFromSnapshot($user, $snapshot);
            }

            return $response;
        } catch (\Throwable $e) {
            $cart->refresh();
            $this->replaceCartItemsPayload($cart, $snapshot);

            throw $e;
        }
    }

    public function oneClickSuggestions(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $lastOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('order_status', '!=', 'cancelled')
            ->orderByDesc('placed_at')
            ->first();

        return response()->json($this->resolveOneClickDeliveryAndPaymentSuggestions($lastOrder));
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'delivery_method' => ['required', 'string', 'max:32'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'gift_certificate_code' => ['nullable', 'string', 'max:64'],
            'gift_certificate_id' => ['nullable', 'integer', 'exists:gift_certificates,id'],
            'loyalty_points_to_spend' => ['nullable', 'integer', 'min:0'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'delivery_city' => ['nullable', 'string', 'max:120'],
        ]);

        if (
            isset($validated['gift_certificate_code'], $validated['gift_certificate_id'])
            && trim((string) $validated['gift_certificate_code']) !== ''
        ) {
            throw ValidationException::withMessages([
                'gift_certificate_code' => 'Укажите либо код сертификата, либо выберите сертификат из кабинета.',
            ]);
        }

        $site = SiteSetting::current();

        if (! $site->isFeatureEnabled(StoreFeatureFlags::GIFT_CERTIFICATES)) {
            $giftCodeTry = trim((string) ($validated['gift_certificate_code'] ?? ''));
            $giftIdTry = isset($validated['gift_certificate_id']) ? (int) $validated['gift_certificate_id'] : null;
            if ($giftCodeTry !== '' || $giftIdTry) {
                throw ValidationException::withMessages([
                    'gift_certificate_code' => 'Подарочные сертификаты сейчас недоступны.',
                ]);
            }
        }

        if (! $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
            $validated['loyalty_points_to_spend'] = 0;
        }

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
        $subtotalAfterPromo = max(0.0, $subtotal - $discountTotal);

        $requestedGiftCodeRaw = isset($validated['gift_certificate_code'])
            ? strtoupper(preg_replace('/\s+/', '', trim((string) $validated['gift_certificate_code'])))
            : '';

        $giftCertPreview = null;
        $giftMessage = null;
        $giftApplied = false;

        try {
            $emailForGift = ($validated['customer_email'] ?? null) ? trim((string) $validated['customer_email']) : '';
            if ($emailForGift === '' && $user) {
                $emailForGift = strtolower(trim((string) $user->email));
            }

            $giftCertPreview = $this->resolveGiftCertificateForCheckout(
                $validated['gift_certificate_code'] ?? null,
                isset($validated['gift_certificate_id']) ? (int) $validated['gift_certificate_id'] : null,
                $cart,
                $subtotalAfterPromo,
                $emailForGift,
                $user,
                false,
            );
            if ($giftCertPreview) {
                $giftApplied = true;
                $giftMessage = 'Подарочный сертификат применён.';
            }
        } catch (ValidationException $exception) {
            $giftMessage = (string) collect($exception->errors())->flatten()->first();
        }

        $giftDiscountTotal = $giftCertPreview && $cart
            ? $this->calculateGiftCertificateDiscount($giftCertPreview, $subtotalAfterPromo)
            : 0.0;
        $subtotalAfterGift = max(0.0, $subtotalAfterPromo - $giftDiscountTotal);

        $loyaltySetting = $this->loyaltyProgram->getSetting();
        $requestedLoyaltyPoints = max(0, (int) ($validated['loyalty_points_to_spend'] ?? 0));
        $maxLoyaltyPoints = 0;
        $loyaltyPointsSpent = 0;
        $loyaltyDiscountTotal = 0.0;
        $loyaltyPointsEarned = 0;
        $loyaltyAccrualPercent = 0.0;

        if ($user && $this->loyaltyProgram->isEnabled($loyaltySetting) && $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
            $maxLoyaltyPoints = $this->loyaltyProgram->resolveMaxRedeemPoints($user, $subtotalAfterGift, $loyaltySetting);
            $loyaltyPointsSpent = min($requestedLoyaltyPoints, $maxLoyaltyPoints);
            $loyaltyDiscountTotal = min(
                $subtotalAfterGift,
                $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting),
            );
            $pointValue = max(0.01, (float) $loyaltySetting->point_value);
            $loyaltyPointsSpent = (int) floor($loyaltyDiscountTotal / $pointValue);
            $loyaltyDiscountTotal = $this->loyaltyProgram->resolveRedeemDiscountByPoints($loyaltyPointsSpent, $loyaltySetting);
            $accrualBase = max(0.0, $subtotalAfterGift - $loyaltyDiscountTotal);
            $loyaltyAccrualPercent = $this->loyaltyProgram->resolveEffectiveAccrualPercent($user, $loyaltySetting);
            $loyaltyPointsEarned = $this->loyaltyProgram->resolveAccrualPoints($user, $accrualBase, $loyaltySetting);
        }

        $deliveryCityCode = $this->resolveCityCode($deliveryMethod, $validated['delivery_city'] ?? null);
        $deliveryQuote = $this->resolveDeliveryQuote($deliveryMethod, $subtotal, $deliveryCityCode);
        $deliveryTotal = $deliveryQuote['fee'];
        if ($promoCode?->free_delivery) {
            $deliveryTotal = 0.0;
        }
        $total = max(0, $subtotal - $discountTotal - $giftDiscountTotal - $loyaltyDiscountTotal + $deliveryTotal);

        return response()->json([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'gift_certificate_discount_total' => $giftDiscountTotal,
            'loyalty_discount_total' => $loyaltyDiscountTotal,
            'delivery_total' => $deliveryTotal,
            'delivery_period_min' => $deliveryQuote['period_min'],
            'delivery_period_max' => $deliveryQuote['period_max'],
            'total' => $total,
            'currency' => $cart?->currency ?? 'RUB',
            'promo' => [
                'code' => $promoCode?->code ?? $requestedPromoCode,
                'is_applied' => $promoApplied,
                'message' => $promoMessage,
            ],
            'gift_certificate' => [
                'id' => $giftCertPreview?->id,
                'code' => $giftCertPreview?->code ?? ($requestedGiftCodeRaw !== '' ? $requestedGiftCodeRaw : null),
                'is_applied' => $giftApplied,
                'message' => $giftMessage,
            ],
            'loyalty' => [
                'is_enabled' => $this->loyaltyProgram->isEnabled($loyaltySetting) && $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY),
                'requested_points' => $requestedLoyaltyPoints,
                'applied_points' => $loyaltyPointsSpent,
                'max_points_to_spend' => $maxLoyaltyPoints,
                'points_balance' => $user ? (int) $user->loyalty_points_balance : 0,
                'points_to_earn' => $loyaltyPointsEarned,
                'accrual_percent' => $loyaltyAccrualPercent,
                'account' => $site->isFeatureEnabled(StoreFeatureFlags::LOYALTY)
                    ? $this->loyaltyProgram->userSnapshot($user, $loyaltySetting)
                    : null,
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
        return $this->resolveDeliveryQuote($method, $subtotal)['fee'];
    }

    /**
     * @return array{fee: float, period_min: ?int, period_max: ?int}
     */
    private function resolveDeliveryQuote(DeliveryMethod $method, float $subtotal, ?int $cityCode = null): array
    {
        if (! $method->provider_code || ! $method->provider) {
            return ['fee' => (float) $method->fee, 'period_min' => null, 'period_max' => null];
        }

        $gateway = $this->deliveryGateways->for($method->provider);

        if ($cityCode && $gateway instanceof CdekDeliveryGateway) {
            $tariff = $gateway->calculateTariff($method->provider, $method, $cityCode);
            if ($tariff) {
                return [
                    'fee' => $tariff['fee'],
                    'period_min' => $tariff['period_min'],
                    'period_max' => $tariff['period_max'],
                ];
            }
        }

        return [
            'fee' => $gateway->resolveFee($method->provider, $method, $subtotal),
            'period_min' => null,
            'period_max' => null,
        ];
    }

    private function resolveCityCode(DeliveryMethod $method, ?string $cityName): ?int
    {
        $cityName = trim((string) $cityName);

        if ($cityName === '' || ! $method->provider_code || ! $method->provider) {
            return null;
        }

        $gateway = $this->deliveryGateways->for($method->provider);

        if (! $gateway instanceof CdekDeliveryGateway) {
            return null;
        }

        return $gateway->findCityCode($method->provider, $cityName);
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
    ): ?PromoCode {
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

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    private function replaceCartItemsPayload(Cart $cart, array $snapshot): void
    {
        $cart->items()->delete();

        foreach ($snapshot as $row) {
            CartItem::query()->create([
                ...$row,
                'cart_id' => $cart->id,
            ]);
        }

        $cart->refresh();
        $subtotal = (float) $cart->items->sum(fn (CartItem $item) => (float) $item->total_price);
        $cart->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ])->save();
    }

    /**
     * Восстановить предыдущую корзину в новой open-сессии после успешного 1‑клик заказа.
     *
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    private function createFreshOpenCartFromSnapshot(User $user, array $snapshot): void
    {
        if ($snapshot === []) {
            return;
        }

        $newCart = Cart::query()->create([
            'user_id' => $user->id,
            'session_id' => 'user:'.$user->id,
            'status' => 'open',
            'currency' => 'RUB',
            'subtotal' => 0,
            'total' => 0,
        ]);

        foreach ($snapshot as $row) {
            CartItem::query()->create([
                ...$row,
                'cart_id' => $newCart->id,
            ]);
        }

        $newCart->refresh();
        $subtotal = (float) $newCart->items->sum(fn (CartItem $item) => (float) $item->total_price);
        $newCart->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ])->save();
    }

    /**
     * @return array{delivery_method: string, payment_method: string}
     */
    private function resolveOneClickDeliveryAndPaymentSuggestions(?Order $lastOrder): array
    {
        $deliveryCode = null;

        if ($lastOrder instanceof Order) {
            $method = DeliveryMethod::query()
                ->with('provider')
                ->where('is_active', true)
                ->where('code', $lastOrder->delivery_method)
                ->first();

            if ($method && $this->isDeliveryMethodAvailable($method)) {
                $deliveryCode = $method->code;
            }
        }

        if ($deliveryCode === null) {
            $first = DeliveryMethod::query()
                ->with('provider')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->first(fn (DeliveryMethod $method) => $this->isDeliveryMethodAvailable($method));

            if (! $first instanceof DeliveryMethod) {
                throw ValidationException::withMessages([
                    'delivery_method' => 'Нет доступных способов доставки.',
                ]);
            }

            $deliveryCode = $first->code;
        }

        $paymentMethod = 'card';

        if (
            $lastOrder instanceof Order
            && in_array($lastOrder->payment_method, ['card', 'cash'], true)
        ) {
            try {
                $this->resolvePaymentProvider($lastOrder->payment_method);
                $paymentMethod = $lastOrder->payment_method;
            } catch (ValidationException) {
                $paymentMethod = 'card';
            }
        }

        return [
            'delivery_method' => $deliveryCode,
            'payment_method' => $paymentMethod,
        ];
    }

    /**
     * @return array{customer_name: string, customer_email: string, customer_phone: string, delivery_method: string, payment_method: string}
     */
    private function resolveOneClickCheckoutDefaults(User $user): array
    {
        /** @var Order|null $lastOrder */
        $lastOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('order_status', '!=', 'cancelled')
            ->orderByDesc('placed_at')
            ->first();

        $dp = $this->resolveOneClickDeliveryAndPaymentSuggestions($lastOrder);

        $name = trim((string) (($lastOrder?->customer_name) ?? $user->name));
        $email = trim(strtolower((string) $user->email));
        $phone = trim((string) (($lastOrder?->customer_phone) ?? ($user->phone ?? '')));

        if ($name === '') {
            throw ValidationException::withMessages([
                'customer_name' => 'Заполните имя в профиле или оформите заказ через корзину один раз.',
            ]);
        }

        if ($email === '') {
            throw ValidationException::withMessages([
                'customer_email' => 'Не удалось получить email из профиля.',
            ]);
        }

        if ($phone === '') {
            throw ValidationException::withMessages([
                'customer_phone' => 'Укажите телефон в кабинете или оформите заказ через корзину.',
            ]);
        }

        return [
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => mb_substr($phone, 0, 30),
            'delivery_method' => $dp['delivery_method'],
            'payment_method' => $dp['payment_method'],
        ];
    }

    public function purchaseGiftCertificate(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethodCodes())],
            'loyalty_points_to_spend' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! SiteSetting::current()->isFeatureEnabled(StoreFeatureFlags::GIFT_CERTIFICATES)) {
            throw ValidationException::withMessages([
                'amount' => 'Покупка подарочных сертификатов сейчас недоступна.',
            ]);
        }

        $amount = round((float) $validated['amount'], 2);
        $presets = [500.0, 1000.0, 5000.0];
        $amountAllowed = in_array($amount, $presets, true) || ($amount >= 100 && $amount <= 500_000);

        if (! $amountAllowed) {
            throw ValidationException::withMessages([
                'amount' => 'Доступны номиналы 500, 1000 и 5000 ₽ либо своя сумма от 100 до 500 000 ₽.',
            ]);
        }

        $defaults = $this->resolveOneClickCheckoutDefaults($user);
        $deliveryMethod = $this->resolveDeliveryMethod('pickup');
        $paymentProvider = $this->resolvePaymentProvider($validated['payment_method']);
        $paymentGateway = $this->paymentGateways->for($paymentProvider);

        $subtotal = $amount;
        $discountTotal = 0.0;
        $giftDiscountTotal = 0.0;
        $subtotalAfterPromo = $subtotal;

        $loyaltySetting = $this->loyaltyProgram->getSetting();
        $requestedLoyaltyPoints = max(0, (int) ($validated['loyalty_points_to_spend'] ?? 0));
        $loyaltyPointsSpent = 0;
        $loyaltyDiscountTotal = 0.0;
        $loyaltyPointsEarned = 0;
        $loyaltyAccrualBase = 0.0;
        $loyaltyAccrualPercent = 0.0;

        if ($requestedLoyaltyPoints > 0 && $this->loyaltyProgram->isEnabled($loyaltySetting) && SiteSetting::current()->isFeatureEnabled(StoreFeatureFlags::LOYALTY)) {
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
        $orderTotal = max(0, $subtotal - $loyaltyDiscountTotal + $deliveryTotal);
        $attribution = AttributionData::normalize(null);

        $order = DB::transaction(function () use (
            $user,
            $defaults,
            $deliveryMethod,
            $paymentProvider,
            $paymentGateway,
            $subtotal,
            $discountTotal,
            $giftDiscountTotal,
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
                'user_id' => $user->id,
                'session_id' => 'user:'.$user->id,
                'checkout_kind' => Order::CHECKOUT_KIND_GIFT_CERTIFICATE,
                'status' => 'new',
                'order_status' => 'placed',
                'payment_status' => $paymentGateway->initialPaymentStatus($paymentProvider),
                'fulfillment_status' => 'pending',
                'refund_status' => 'none',
                'delivery_method' => $deliveryMethod->code,
                'payment_method' => $this->resolvePaymentMethodKind($paymentProvider),
                'currency' => 'RUB',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'gift_certificate_id' => null,
                'gift_certificate_discount_total' => $giftDiscountTotal,
                'loyalty_points_spent' => $loyaltyPointsSpent,
                'loyalty_discount_total' => $loyaltyDiscountTotal,
                'loyalty_points_earned' => $loyaltyPointsEarned,
                'delivery_total' => $deliveryTotal,
                'total' => $orderTotal,
                'customer_name' => $defaults['customer_name'],
                'customer_email' => $defaults['customer_email'],
                'customer_phone' => $defaults['customer_phone'],
                ...$attribution,
                'comment' => null,
                'promo_code' => null,
                'placed_at' => now(),
            ]);

            $order->items()->create([
                'product_id' => null,
                'product_variant_id' => null,
                'product_name' => 'Подарочный сертификат',
                'product_slug' => 'gift-certificate',
                'variant_label' => null,
                'image_url' => null,
                'qty' => 1,
                'unit_price' => $subtotal,
                'total_price' => $subtotal,
            ]);

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
                        'customer_email' => strtolower(trim($defaults['customer_email'])),
                        'delivery_method' => $deliveryMethod->code,
                        'checkout_kind' => Order::CHECKOUT_KIND_GIFT_CERTIFICATE,
                    ],
                ),
            ]);

            if ($this->loyaltyProgram->isEnabled($loyaltySetting)) {
                $freshUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

                if ($freshUser && $loyaltyPointsSpent > 0) {
                    $this->loyaltyProgram->applyRedeem(
                        $freshUser,
                        $order,
                        $loyaltyPointsSpent,
                        $loyaltyDiscountTotal,
                    );
                }

                // Баллы начисляются после оплаты — см. OrderObserver
            }

            return $order->fresh(['items', 'paymentTransactions', 'giftCertificate']);
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
            'delivery_city' => $order->delivery_city,
            'delivery_address' => $order->delivery_address,
            'delivery_pickup_point_code' => $order->delivery_pickup_point_code,
            'delivery_pickup_point_address' => $order->delivery_pickup_point_address,
            'delivery_period_min' => $order->delivery_period_min !== null ? (int) $order->delivery_period_min : null,
            'delivery_period_max' => $order->delivery_period_max !== null ? (int) $order->delivery_period_max : null,
            'payment_method' => $order->payment_method,
            'payment_transaction_status' => $order->paymentTransactions->first()?->status,
            'promo_code' => $order->promo_code,
            'gift_certificate_code' => $order->giftCertificate?->code,
            'gift_certificate_discount_total' => (float) $order->gift_certificate_discount_total,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'loyalty_points_spent' => (int) $order->loyalty_points_spent,
            'loyalty_discount_total' => (float) $order->loyalty_discount_total,
            'loyalty_points_earned' => (int) $order->loyalty_points_earned,
            'delivery_total' => (float) $order->delivery_total,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'items_count' => $order->items->sum('qty'),
            'loyalty_account' => $this->loyaltyProgram->userSnapshot($user->fresh(), $loyaltySetting),
            'checkout_kind' => $order->checkout_kind,
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        return 'SH'.now()->format('ymdHis').random_int(100, 999);
    }

    private function normalizeGiftCertificateCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    private function resolveGiftCertificateForCheckout(
        ?string $code,
        ?int $giftCertificateId,
        ?Cart $cart,
        float $subtotalAfterPromo,
        string $customerEmail,
        ?User $user,
        bool $lockForUpdate,
    ): ?GiftCertificate {
        if ($giftCertificateId !== null) {
            if (! $user) {
                throw ValidationException::withMessages([
                    'gift_certificate_id' => 'Войдите в аккаунт, чтобы применить сертификат из личного кабинета.',
                ]);
            }

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'gift_certificate_id' => 'Сертификат можно применить только при непустой корзине.',
                ]);
            }

            $query = GiftCertificate::query()->whereKey($giftCertificateId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $cert = $query->first();

            if (! $cert) {
                throw ValidationException::withMessages([
                    'gift_certificate_id' => 'Подарочный сертификат не найден.',
                ]);
            }

            if ((int) $cert->owner_user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'gift_certificate_id' => 'Этот сертификат не привязан к вашему аккаунту.',
                ]);
            }

            $this->assertGiftCertificateConstraints($cert, $customerEmail, $user, 'gift_certificate_id');

            return $cert;
        }

        $normalized = $this->normalizeGiftCertificateCode($code);

        if ($normalized === null) {
            return null;
        }

        if (! $cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'gift_certificate_code' => 'Сертификат можно применить только при непустой корзине.',
            ]);
        }

        $query = GiftCertificate::query()->where('code', $normalized);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $cert = $query->first();

        if (! $cert) {
            throw ValidationException::withMessages([
                'gift_certificate_code' => 'Подарочный сертификат не найден.',
            ]);
        }

        $this->assertGiftCertificateConstraints($cert, $customerEmail, $user, 'gift_certificate_code');

        return $cert;
    }

    private function assertGiftCertificateConstraints(
        GiftCertificate $cert,
        string $customerEmail,
        ?User $user,
        string $errorKey,
    ): void {
        if ($cert->status === GiftCertificate::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                $errorKey => 'Сертификат аннулирован.',
            ]);
        }

        if ($cert->expires_at && $cert->expires_at->isPast()) {
            throw ValidationException::withMessages([
                $errorKey => 'Срок действия сертификата истёк.',
            ]);
        }

        if ((float) $cert->balance_remaining <= 0) {
            throw ValidationException::withMessages([
                $errorKey => 'На сертификате не осталось средств.',
            ]);
        }

        $email = strtolower(trim($customerEmail));
        $isOwner = $cert->owner_user_id && $user && (int) $cert->owner_user_id === (int) $user->id;

        if ($cert->recipient_email && ! $isOwner) {
            $expected = strtolower(trim((string) $cert->recipient_email));

            if ($email === '') {
                throw ValidationException::withMessages([
                    $errorKey => 'Укажите email — сертификат привязан к адресу получателя.',
                ]);
            }

            if ($expected !== $email) {
                throw ValidationException::withMessages([
                    $errorKey => 'Сертификат привязан к другому email.',
                ]);
            }
        }
    }

    private function calculateGiftCertificateDiscount(GiftCertificate $cert, float $subtotalAfterPromo): float
    {
        if ($subtotalAfterPromo <= 0) {
            return 0.0;
        }

        return round(min((float) $cert->balance_remaining, $subtotalAfterPromo), 2);
    }
}
