<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
        $cart = $this->findOrCreateOpenCart($user, $sessionId);
        $cart = $this->recalculateCart($cart);

        return response()->json($this->serializeCart($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:64'],
            'product_slug' => ['required', 'string', 'max:255'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity(
            $request,
            $validated['session_id'] ?? null,
        );
        $qtyToAdd = (int) ($validated['qty'] ?? 1);

        $product = Product::query()
            ->with([
                'images:id,product_id,url,is_cover,sort_order',
                'variants:id,product_id,slug,size_label,color_label,price,stock,is_active,sort_order',
                'variants.images:id,product_variant_id,url,is_cover,sort_order',
            ])
            ->where('is_active', true)
            ->where('slug', $validated['product_slug'])
            ->firstOrFail();

        $selectedVariant = $this->resolveVariantForCartItem(
            $product,
            isset($validated['product_variant_id']) ? (int) $validated['product_variant_id'] : null,
        );

        $cart = $this->findOrCreateOpenCart($user, $sessionId);

        $itemQuery = $cart->items()
            ->where('product_id', $product->id);

        if ($selectedVariant) {
            $itemQuery->where('product_variant_id', $selectedVariant->id);
        } else {
            $itemQuery->whereNull('product_variant_id');
        }

        $item = $itemQuery->first();

        $nextQty = $qtyToAdd;

        if ($item) {
            $nextQty += $item->qty;
        }

        $availableStock = $selectedVariant?->stock ?? $product->stock;

        if ($nextQty > $availableStock) {
            return response()->json([
                'message' => 'Недостаточный остаток товара на складе.',
            ], 422);
        }

        $coverImage = $this->resolveCoverImageUrl($product, $selectedVariant);

        $unitPrice = (float) ($selectedVariant?->price ?? $product->price);

        if (! $item) {
            $item = new CartItem();
            $item->cart_id = $cart->id;
            $item->product_id = $product->id;
            $item->product_variant_id = $selectedVariant?->id;
        }

        $item->product_name = $product->name;
        $item->product_slug = $product->slug;
        $item->variant_label = $this->resolveVariantLabel($selectedVariant);
        $item->image_url = $coverImage;
        $item->qty = $nextQty;
        $item->unit_price = $unitPrice;
        $item->total_price = $unitPrice * $item->qty;
        $item->save();

        $cart = $this->recalculateCart($cart);

        return response()->json($this->serializeCart($cart));
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:64'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity(
            $request,
            $validated['session_id'] ?? null,
        );
        $qty = (int) $validated['qty'];

        $cart = $this->findOrCreateOpenCart($user, $sessionId);
        $item = $cart->items()->where('id', $itemId)->firstOrFail();
        $product = Product::query()
            ->with('variants:id,product_id,slug,size_label,color_label,price,stock,is_active,sort_order')
            ->findOrFail($item->product_id);
        $variant = $item->product_variant_id
            ? $product->variants->firstWhere('id', $item->product_variant_id)
            : null;

        $availableStock = $variant?->stock ?? $product->stock;

        if ($qty > $availableStock) {
            return response()->json([
                'message' => 'Недостаточный остаток товара на складе.',
            ], 422);
        }

        $item->unit_price = (float) ($variant?->price ?? $product->price);
        $item->qty = $qty;
        $item->total_price = ((float) $item->unit_price) * $qty;
        $item->save();

        $cart = $this->recalculateCart($cart);

        return response()->json($this->serializeCart($cart));
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        ['user' => $user, 'session_id' => $sessionId] = $this->resolveIdentity($request);
        $cart = $this->findOrCreateOpenCart($user, $sessionId);
        $item = $cart->items()->where('id', $itemId)->firstOrFail();
        $item->delete();

        $cart = $this->recalculateCart($cart);

        return response()->json($this->serializeCart($cart));
    }

    private function resolveIdentity(Request $request, ?string $sessionFromPayload = null): array
    {
        $user = $this->resolveAuthenticatedUser($request);

        $sessionId = (string) (
            $sessionFromPayload
            ?? $request->input('session_id')
            ?? $request->query('session_id')
            ?? $request->header('X-Session-Id')
        );

        if (! $user && $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => 'session_id обязателен для операций с корзиной.',
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

    private function findOrCreateOpenCart(?User $user, ?string $sessionId): Cart
    {
        if ($user) {
            $userCart = Cart::query()
                ->where('status', 'open')
                ->where('user_id', $user->id)
                ->first();

            if ($userCart) {
                return $userCart;
            }

            if ($sessionId) {
                $guestCart = Cart::query()
                    ->where('status', 'open')
                    ->whereNull('user_id')
                    ->where('session_id', $sessionId)
                    ->first();

                if ($guestCart) {
                    $guestCart->user_id = $user->id;
                    $guestCart->save();

                    return $guestCart;
                }
            }

            return Cart::query()->create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'status' => 'open',
                'currency' => 'RUB',
                'subtotal' => 0,
                'total' => 0,
            ]);
        }

        return Cart::query()->firstOrCreate(
            [
                'session_id' => $sessionId,
                'status' => 'open',
                'user_id' => null,
            ],
            [
                'currency' => 'RUB',
                'subtotal' => 0,
                'total' => 0,
            ],
        );
    }

    private function recalculateCart(Cart $cart): Cart
    {
        $cart->load('items');
        $this->syncCartItemSnapshots($cart);
        $cart->load('items');

        $subtotal = $cart->items->sum(fn (CartItem $item) => (float) $item->total_price);

        $cart->subtotal = $subtotal;
        $cart->total = $subtotal;
        $cart->save();

        return $cart->fresh('items');
    }

    private function syncCartItemSnapshots(Cart $cart): void
    {
        if ($cart->items->isEmpty()) {
            return;
        }

        $productIds = $cart->items->pluck('product_id')->filter()->unique()->all();

        $products = Product::query()
            ->with([
                'images:id,product_id,url,is_cover,sort_order',
                'variants:id,product_id,slug,size_label,color_label,price,stock,is_active,sort_order',
                'variants.images:id,product_variant_id,url,is_cover,sort_order',
            ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($cart->items as $item) {
            $product = $products->get($item->product_id);

            if (! $product) {
                continue;
            }

            $variant = $item->product_variant_id
                ? $product->variants->firstWhere('id', $item->product_variant_id)
                : null;

            $coverImage = $this->resolveCoverImageUrl($product, $variant);

            $unitPrice = (float) ($variant?->price ?? $product->price);
            $nextTotal = $unitPrice * $item->qty;

            if (
                $item->product_name === $product->name
                && $item->product_slug === $product->slug
                && $item->variant_label === $this->resolveVariantLabel($variant)
                && $item->image_url === $coverImage
                && (float) $item->unit_price === $unitPrice
                && (float) $item->total_price === $nextTotal
            ) {
                continue;
            }

            $item->forceFill([
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'variant_label' => $this->resolveVariantLabel($variant),
                'image_url' => $coverImage,
                'unit_price' => $unitPrice,
                'total_price' => $nextTotal,
            ])->save();
        }
    }

    private function serializeCart(Cart $cart): array
    {
        $cart->loadMissing('items');

        $productIds = $cart->items->pluck('product_id')->filter()->unique()->all();

        $products = Product::query()
            ->with('variants:id,product_id,slug,size_label,color_label,price,stock,is_active,sort_order')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $items = $cart->items->map(function (CartItem $item) use ($products) {
            $product = $products->get($item->product_id);
            $variant = $item->product_variant_id && $product
                ? $product->variants->firstWhere('id', $item->product_variant_id)
                : null;
            $availability = $this->resolveItemAvailability($product, $variant, (int) $item->qty);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_slug' => $item->product_slug,
                'product_name' => $item->product_name,
                'variant_label' => $item->variant_label,
                'image_url' => $item->image_url,
                'qty' => $item->qty,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'available' => $availability['available'],
                'available_stock' => $availability['available_stock'],
                'availability_message' => $availability['message'],
            ];
        })->values();

        return [
            'id' => $cart->id,
            'session_id' => $cart->session_id,
            'status' => $cart->status,
            'currency' => $cart->currency,
            'subtotal' => (float) $cart->subtotal,
            'total' => (float) $cart->total,
            'total_items' => (int) $items->sum('qty'),
            'items' => $items,
        ];
    }

    private function resolveVariantForCartItem(Product $product, ?int $variantId): ?ProductVariant
    {
        $activeVariants = $product->variants
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();

        if ($activeVariants->isEmpty()) {
            return null;
        }

        if ($variantId !== null) {
            $selected = $activeVariants->firstWhere('id', $variantId);

            if (! $selected) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'Выбранный вариант товара не найден.',
                ]);
            }

            return $selected;
        }

        return $activeVariants->firstWhere('stock', '>', 0) ?? $activeVariants->first();
    }

    private function resolveItemAvailability(?Product $product, ?ProductVariant $variant, int $qty): array
    {
        if (! $product || ! $product->is_active) {
            return [
                'available' => false,
                'available_stock' => 0,
                'message' => 'Товар больше недоступен.',
            ];
        }

        if ($variant && ! $variant->is_active) {
            return [
                'available' => false,
                'available_stock' => 0,
                'message' => 'Выбранный вариант товара больше недоступен.',
            ];
        }

        $availableStock = max(0, (int) ($variant?->stock ?? $product->stock));

        if ($availableStock <= 0) {
            return [
                'available' => false,
                'available_stock' => 0,
                'message' => 'Нет в наличии.',
            ];
        }

        if ($qty > $availableStock) {
            return [
                'available' => false,
                'available_stock' => $availableStock,
                'message' => "В наличии осталось только {$availableStock} шт.",
            ];
        }

        return [
            'available' => true,
            'available_stock' => $availableStock,
            'message' => null,
        ];
    }

    private function resolveVariantLabel(?ProductVariant $variant): ?string
    {
        if (! $variant) {
            return null;
        }

        if ($variant->color_label && trim($variant->color_label) !== '') {
            return "{$variant->color_label} · {$variant->size_label}";
        }

        return $variant->size_label;
    }

    private function resolveCoverImageUrl(Product $product, ?ProductVariant $variant): ?string
    {
        if ($variant && $variant->relationLoaded('images') && $variant->images->isNotEmpty()) {
            return $variant->images
                ->sortBy([
                    ['is_cover', 'desc'],
                    ['sort_order', 'asc'],
                ])
                ->first()?->url;
        }

        return $product->images
            ->sortBy([
                ['is_cover', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->first()?->url;
    }
}
