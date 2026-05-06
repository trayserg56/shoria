<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { toProductRoute } from '@/lib/product-route'
import { openProductQuickView } from '@/lib/product-quick-view'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore, type WishlistItem } from '@/stores/wishlist'
import { useCompareStore, type CompareItem } from '@/stores/compare'

type ProductCardData = {
  id: number
  name: string
  brand?: string | null
  slug: string
  price: number
  old_price: number | null
  stock: number
  currency: string
  image_url: string | null
  category: {
    name: string
    slug: string
  } | null
  tags?: Array<{
    code: string
    label: string
  }>
  reviews_summary?: {
    count: number
    average: number | null
  }
}

const props = withDefaults(
  defineProps<{
    product: ProductCardData
    source?: string
    showImageSkeleton?: boolean
  }>(),
  {
    source: 'catalog',
    showImageSkeleton: true,
  },
)

const cartStore = useCartStore()
const router = useRouter()
const { items: cartItems } = storeToRefs(cartStore)
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const isCartBusy = ref(false)
const isImageBroken = ref(false)
const isImageLoading = ref(true)
const productImageFallback = '/images/product-fallback.svg'

const isWishlisted = computed(() => wishlistStore.has(props.product.id))
const isCompared = computed(() => compareStore.has(props.product.id))
const isOutOfStock = computed(() => props.product.stock <= 0)
const loyaltyReward = computed(() => Math.max(1, Math.round(props.product.price * 0.1)))
const discountPercent = computed(() => {
  const oldPrice = props.product.old_price

  if (!oldPrice || oldPrice <= props.product.price || oldPrice <= 0) {
    return null
  }

  return Math.round(((oldPrice - props.product.price) / oldPrice) * 100)
})
const productImageUrl = computed(() => {
  if (isImageBroken.value) {
    return productImageFallback
  }

  const rawUrl = props.product.image_url?.trim()

  if (!rawUrl) {
    return productImageFallback
  }

  return rawUrl
})
const currentCartQty = computed(() =>
  cartItems.value
    .filter((item) => item.product_id === props.product.id)
    .reduce((total, item) => total + item.qty, 0),
)

function formatPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function formatPricePerUnit(value: number, currency: string) {
  const amount = new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: 0,
  }).format(value)

  if (currency === 'RUB') {
    return `${amount} ₽/шт`
  }

  return `${amount} ${currency}/шт`
}

function formatReviewsCount(value: number) {
  const normalized = Math.max(0, Math.trunc(value))
  const mod10 = normalized % 10
  const mod100 = normalized % 100

  if (mod10 === 1 && mod100 !== 11) {
    return `${normalized} отзыв`
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${normalized} отзыва`
  }

  return `${normalized} отзывов`
}

function formatRating(value: number | null | undefined) {
  if (value === null || value === undefined) {
    return '—'
  }

  return value.toFixed(1)
}

function tagCodeClass(code: string) {
  if (code === 'new') {
    return 'tag-badge--new'
  }

  if (code === 'customer_choice') {
    return 'tag-badge--choice'
  }

  return 'tag-badge--hit'
}

function onQuickViewClick() {
  openProductQuickView(props.product.slug, props.product.category?.slug ?? null, props.source)
}

function onProductClick() {
  void trackEvent('select_product', {
    source: props.source,
    slug: props.product.slug,
  })
}

function toWishlistItem(): WishlistItem {
  return {
    id: props.product.id,
    slug: props.product.slug,
    name: props.product.name,
    price: props.product.price,
    old_price: props.product.old_price,
    stock: props.product.stock,
    currency: props.product.currency,
    image_url: props.product.image_url,
    category: props.product.category,
  }
}

function toggleWishlist() {
  const added = wishlistStore.toggle(toWishlistItem())

  void trackEvent('toggle_wishlist', {
    source: props.source,
    slug: props.product.slug,
    action: added ? 'added' : 'removed',
  })
}

function toCompareItem(): CompareItem {
  return {
    id: props.product.id,
    slug: props.product.slug,
    name: props.product.name,
    price: props.product.price,
    old_price: props.product.old_price,
    currency: props.product.currency,
    image_url: props.product.image_url,
    stock: props.product.stock,
    category: props.product.category,
    tags: props.product.tags,
  }
}

function toggleCompare() {
  const result = compareStore.toggle(toCompareItem())

  void trackEvent('toggle_compare', {
    source: props.source,
    slug: props.product.slug,
    action: result.active ? 'added' : 'removed',
  })
}

async function addToCart() {
  if (isCartBusy.value) {
    return
  }

  isCartBusy.value = true

  try {
    await cartStore.addItemBySlug(props.product.slug, 1)

    void trackEvent('add_to_cart', {
      source: props.source,
      slug: props.product.slug,
      price: props.product.price,
      qty: 1,
    })
  } catch (error) {
    console.error(error)
  } finally {
    isCartBusy.value = false
  }
}

async function increaseCartQty() {
  if (isCartBusy.value) {
    return
  }

  const entry = cartItems.value.find((item) => item.product_id === props.product.id)

  isCartBusy.value = true

  try {
    if (!entry) {
      await cartStore.addItemBySlug(props.product.slug, 1)
    } else {
      await cartStore.updateQty(entry.id, entry.qty + 1)
    }
  } catch (error) {
    console.error(error)
  } finally {
    isCartBusy.value = false
  }
}

async function decreaseCartQty() {
  if (isCartBusy.value) {
    return
  }

  const entry = cartItems.value.find((item) => item.product_id === props.product.id)

  if (!entry) {
    return
  }

  isCartBusy.value = true

  try {
    if (entry.qty <= 1) {
      await cartStore.removeItem(entry.id)
    } else {
      await cartStore.updateQty(entry.id, entry.qty - 1)
    }
  } catch (error) {
    console.error(error)
  } finally {
    isCartBusy.value = false
  }
}

function onProductImageError() {
  isImageBroken.value = true
  isImageLoading.value = false
}

function onProductImageLoad() {
  isImageLoading.value = false
}

async function openBrandCatalog(brand: string | null | undefined) {
  const value = brand?.trim()

  if (!value) {
    return
  }

  await router.push({
    path: '/catalog',
    query: {
      brands: value,
    },
  })
}

watch(
  () => props.product.image_url,
  () => {
    isImageBroken.value = false
    isImageLoading.value = true
  },
)
</script>

<template>
  <article class="unified-product-card">
    <div class="product-card__media">
      <RouterLink class="product-link" :to="toProductRoute(product)" @click="onProductClick">
        <AppSkeleton
          v-if="showImageSkeleton && isImageLoading"
          class="product-card__image-skeleton"
          width="100%"
          height="100%"
          radius="12px"
        />
        <img
          :src="productImageUrl"
          :alt="product.name"
          loading="lazy"
          :class="{ 'product-card__image--hidden': showImageSkeleton && isImageLoading }"
          @error="onProductImageError"
          @load="onProductImageLoad"
        />
      </RouterLink>
      <button type="button" class="product-card__quickview" @click.stop="onQuickViewClick">
        Быстрый просмотр
      </button>
      <div v-if="product.tags?.length" class="product-card__tags">
        <span
          v-for="tag in product.tags"
          :key="`${product.id}-${tag.code}`"
          class="tag-badge"
          :class="tagCodeClass(tag.code)"
        >
          {{ tag.label }}
        </span>
      </div>
      <div class="product-card__rail">
        <button
          type="button"
          class="rail-btn"
          :class="{ 'rail-btn--active': isWishlisted }"
          :aria-label="isWishlisted ? 'Убрать из избранного' : 'Добавить в избранное'"
          @click="toggleWishlist"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
              d="M12 20.7l-1.1-1C6 15.2 3 12.5 3 9.2 3 6.5 5.1 4.4 7.8 4.4c1.5 0 3 .7 4 1.9 1-1.2 2.5-1.9 4-1.9 2.7 0 4.8 2.1 4.8 4.8 0 3.3-3 6-7.9 10.5l-1.1 1z"
            />
          </svg>
        </button>
        <button
          type="button"
          class="rail-btn"
          :class="{ 'rail-btn--active': isCompared }"
          :aria-label="isCompared ? 'Убрать из сравнения' : 'Добавить в сравнение'"
          @click="toggleCompare"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
              d="M10 3H5a2 2 0 0 0-2 2v5h2V5h5V3zm9 11v5a2 2 0 0 1-2 2h-5v-2h5v-5h2zM3 14v5a2 2 0 0 0 2 2h5v-2H5v-5H3zm16-9h-5V3h5a2 2 0 0 1 2 2v5h-2V5zM8 8h2v8H8V8zm6 0h2v8h-2V8z"
            />
          </svg>
        </button>
      </div>
    </div>
    <div class="product-card__content">
      <div class="price-row">
        <strong>{{ formatPricePerUnit(product.price, product.currency) }}</strong>
        <span v-if="product.old_price" class="product-card__old-price">
          {{ formatPrice(product.old_price, product.currency) }}
        </span>
        <span v-if="discountPercent" class="product-card__discount-pill">-{{ discountPercent }}%</span>
      </div>
      <p class="product-card__reward">+{{ loyaltyReward }} на счет</p>
      <button
        v-if="product.brand"
        type="button"
        class="product-card__brand-link"
        @click.stop="openBrandCatalog(product.brand)"
      >
        {{ product.brand }}
      </button>
      <RouterLink class="product-card__title-link" :to="toProductRoute(product)" @click="onProductClick">
        <h3>{{ product.name }}</h3>
      </RouterLink>
      <div class="product-card__meta-row">
        <span v-if="(product.reviews_summary?.count ?? 0) > 0" class="product-card__meta-item">
          ★ {{ formatRating(product.reviews_summary?.average) }} · {{ formatReviewsCount(product.reviews_summary?.count ?? 0) }}
        </span>
        <span class="product-card__meta-item" :class="{ 'product-card__meta-item--ok': !isOutOfStock }">
          <template v-if="!isOutOfStock">✓ </template>{{ isOutOfStock ? 'Нет в наличии' : 'В наличии' }}
        </span>
      </div>
    </div>

    <div class="product-card__actions">
      <button
        v-if="currentCartQty === 0 && !isOutOfStock"
        type="button"
        class="action action--cart"
        :disabled="isCartBusy"
        @click="addToCart"
      >
        {{ isCartBusy ? 'Добавляем...' : 'В корзину' }}
      </button>
      <button
        v-else-if="currentCartQty === 0"
        type="button"
        class="action action--wishlist"
        @click="toggleWishlist"
      >
        {{ isWishlisted ? 'В избранном' : 'В избранное' }}
      </button>
      <div v-else class="cart-stepper">
        <div class="cart-stepper__controls">
          <button type="button" :disabled="isCartBusy" @click="decreaseCartQty">−</button>
          <strong>{{ currentCartQty }}</strong>
          <button type="button" :disabled="isCartBusy" @click="increaseCartQty">+</button>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.unified-product-card {
  position: relative;
  display: flex;
  flex-direction: column;
  border-radius: 14px;
  background: #fff;
  border: 1px solid var(--border);
  /* без «серой подложки»: лёгкая тень только по контуру */
  box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
  padding: 0;
  overflow: hidden;
  height: 100%;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.unified-product-card:hover {
  border-color: color-mix(in srgb, var(--border), var(--primary) 22%);
  box-shadow:
    0 10px 32px rgb(15 23 42 / 12%),
    0 2px 8px rgb(15 23 42 / 6%);
  transform: translateY(-2px);
}

.product-link {
  display: block;
  color: inherit;
  text-decoration: none;
  border-radius: 0;
  overflow: hidden;
  background: transparent;
  position: relative;
  width: 100%;
  height: 100%;
}

.product-card__media {
  position: relative;
  overflow: hidden;
  background: #fff;
  border: none;
  border-bottom: 1px solid var(--border);
  aspect-ratio: 4 / 3;
}

.product-card__media:hover .product-card__quickview {
  opacity: 1;
  transform: translate(-50%, 0);
}

.unified-product-card img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  padding: 0;
}

.product-card__image-skeleton {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.product-card__image--hidden {
  opacity: 0;
}

.product-card__quickview {
  position: absolute;
  left: 50%;
  bottom: 14px;
  z-index: 3;
  transform: translate(-50%, 10px);
  opacity: 0;
  transition: 0.22s ease;
  background: rgb(23 23 23 / 72%);
  color: #fff;
  border: none;
  border-radius: 999px;
  font: inherit;
  font-size: 11px;
  font-weight: 600;
  padding: 8px 14px;
  letter-spacing: 0.02em;
  backdrop-filter: blur(4px);
  cursor: pointer;
}

.product-card__quickview:focus-visible {
  opacity: 1;
  transform: translate(-50%, 0);
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

.product-card__tags {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 2;
  display: flex;
  flex-direction: column;
  flex-wrap: nowrap;
  align-items: flex-start;
  gap: 6px;
  max-width: 58%;
}

.tag-badge {
  padding: 5px 9px;
  border-radius: 6px;
  font-size: 11px;
  line-height: 1.15;
  font-weight: 700;
  color: #fff;
  box-shadow: 0 1px 2px rgb(0 0 0 / 12%);
}

.tag-badge--hit {
  background: #ea580c;
}

.tag-badge--new {
  background: #16a34a;
}

.tag-badge--choice {
  background: #7c3aed;
}

.product-card__rail {
  position: absolute;
  right: 8px;
  top: 8px;
  z-index: 2;
  display: grid;
  gap: 6px;
}

.rail-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid color-mix(in srgb, var(--border), transparent 10%);
  background: rgb(255 255 255 / 96%);
  color: #9ca3af;
  cursor: pointer;
  display: grid;
  place-items: center;
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
  transition:
    color 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.rail-btn svg {
  width: 15px;
  height: 15px;
  fill: currentColor;
}

.rail-btn--active {
  color: var(--primary);
  border-color: color-mix(in srgb, var(--primary), transparent 65%);
  background: #fff;
}

.product-card__content {
  display: grid;
  gap: 8px;
  padding: 12px 14px 8px;
  flex: 1 1 auto;
  background: #fff;
}

.product-card__actions {
  min-height: 44px;
  padding: 0 14px 14px;
  margin-top: auto;
  display: flex;
  align-items: stretch;
  justify-content: stretch;
  width: 100%;
}

.product-card__brand-link {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--muted-foreground);
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  text-align: left;
  cursor: pointer;
  line-height: 1.2;
}

.product-card__brand-link:hover {
  color: var(--foreground);
}

.product-card__title-link {
  color: inherit;
  text-decoration: none;
}

.product-card__title-link h3 {
  margin: 0;
  font-size: clamp(15px, 1.05vw, 17px);
  font-weight: 600;
  line-height: 1.35;
  letter-spacing: -0.01em;
  color: var(--foreground);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-card__reward {
  margin: 0;
  font-size: 12px;
  color: #15803d;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.product-card__meta-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 12px;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.25;
}

.product-card__meta-item--ok {
  color: #15803d;
  font-weight: 600;
}

.product-card__old-price {
  margin: 0;
  color: #9ca3af;
  font-size: 13px;
  font-weight: 500;
  text-decoration: line-through;
  line-height: 1;
}

.price-row {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 8px 10px;
  min-height: 22px;
}

.price-row strong {
  font-size: clamp(18px, 1.15vw, 22px);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.02em;
  color: var(--foreground);
}

.product-card__discount-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 6px;
  padding: 3px 7px;
  background: #dc2626;
  color: #fff;
  border: none;
  font-size: 11px;
  line-height: 1;
  font-weight: 700;
}

.action {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-height: 44px;
  text-align: center;
  width: 100%;
  padding: 11px 16px;
  border-radius: 10px;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  line-height: 1.2;
  transition:
    background-color 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.action--cart {
  background: var(--primary);
  color: var(--primary-foreground);
  border: 1px solid transparent;
  box-shadow: 0 1px 2px rgb(37 99 235 / 25%);
}

.action--cart:hover {
  background: color-mix(in srgb, var(--primary), #000 8%);
}

.action--wishlist {
  background: var(--background);
  color: var(--foreground);
  border: 1px solid var(--border);
}

.action--cart:disabled {
  opacity: 0.7;
  cursor: default;
}

.cart-stepper {
  display: flex;
  align-items: center;
  width: 100%;
}

.cart-stepper__controls {
  box-sizing: border-box;
  width: 100%;
  height: 44px;
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--background);
  padding: 0 8px;
}

.cart-stepper__controls strong {
  flex: 1 1 auto;
  text-align: center;
  font-size: 17px;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

.cart-stepper__controls button {
  box-sizing: border-box;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: #fff;
  font: inherit;
  font-size: 22px;
  line-height: 0.9;
  cursor: pointer;
  color: var(--foreground);
}

.cart-stepper__controls button:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 720px) {
  .unified-product-card {
    border-radius: 12px;
  }

  .unified-product-card img {
    height: 100%;
  }

  .product-card__content {
    padding: 10px 12px 6px;
  }

  .product-card__actions {
    padding: 0 12px 12px;
  }

  .product-card__title-link h3 {
    font-size: 15px;
  }

  .price-row strong {
    font-size: 17px;
  }

  .action {
    font-size: 13px;
    padding: 10px 14px;
  }

  .product-card__brand-link {
    font-size: 10px;
  }

  .product-card__reward,
  .product-card__meta-row {
    font-size: 11px;
  }
}
</style>
