<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { toProductRoute } from '@/lib/product-route'
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
  }>(),
  {
    source: 'catalog',
  },
)

const cartStore = useCartStore()
const router = useRouter()
const { items: cartItems } = storeToRefs(cartStore)
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const isCartBusy = ref(false)
const isImageBroken = ref(false)
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
  },
)
</script>

<template>
  <article class="unified-product-card">
    <div class="product-card__media">
      <RouterLink class="product-link" :to="toProductRoute(product)" @click="onProductClick">
        <img :src="productImageUrl" :alt="product.name" loading="lazy" @error="onProductImageError" />
        <span class="product-card__quickview">Быстрый просмотр</span>
      </RouterLink>
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
          {{ isOutOfStock ? 'Нет в наличии' : 'В наличии' }}
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
        <span class="cart-stepper__label">В корзине</span>
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
  border-radius: 16px;
  background: linear-gradient(180deg, color-mix(in srgb, var(--card) 88%, #fff) 0%, var(--card) 100%);
  border: 1px solid var(--border);
  box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
  padding: 10px;
  height: 100%;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.unified-product-card:hover {
  border-color: color-mix(in srgb, var(--ring) 35%, var(--border));
  box-shadow:
    0 14px 28px rgb(15 23 42 / 12%),
    0 2px 5px rgb(15 23 42 / 6%);
  transform: translateY(-1px);
}

.product-link {
  display: block;
  color: inherit;
  text-decoration: none;
  border-radius: 12px;
  overflow: hidden;
  background: transparent;
  position: relative;
  width: 100%;
  height: 100%;
}

.product-card__media {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  background: transparent;
  border: 1px solid color-mix(in srgb, var(--border) 70%, white);
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

.product-card__quickview {
  position: absolute;
  left: 50%;
  bottom: 16px;
  transform: translate(-50%, 12px);
  opacity: 0;
  transition: 0.2s ease;
  background: rgb(15 23 42 / 62%);
  color: var(--primary-foreground);
  border-radius: 8px;
  font-size: 10px;
  font-weight: 600;
  padding: 5px 9px;
  letter-spacing: 0.01em;
}

.product-card__tags {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  max-width: 72%;
}

.tag-badge {
  padding: 4px 7px;
  border-radius: 8px;
  font-size: 11px;
  line-height: 1;
  font-weight: 600;
  color: #fff;
}

.tag-badge--hit {
  background: #ff7a59;
}

.tag-badge--new {
  background: #2fbf4b;
}

.tag-badge--choice {
  background: #6f5cff;
}

.product-card__rail {
  position: absolute;
  right: 10px;
  top: 10px;
  z-index: 2;
  display: grid;
  gap: 5px;
}

.rail-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: rgb(255 255 255 / 92%);
  color: var(--muted-foreground);
  cursor: pointer;
  display: grid;
  place-items: center;
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
  color: var(--foreground);
  border-color: color-mix(in srgb, var(--ring) 35%, var(--border));
  background: #fff;
}

.product-card__content {
  display: grid;
  gap: 6px;
  padding: 10px 2px 6px;
  flex: 1 1 auto;
}

.product-card__actions {
  min-height: 48px;
  padding: 0 2px 2px;
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: flex-start;
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
  font-size: clamp(19px, 1.18vw, 22px);
  line-height: 1.18;
  letter-spacing: -0.015em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-card__reward {
  margin: 0;
  font-size: 11px;
  color: #1db74e;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.product-card__meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 11px;
  color: var(--muted-foreground);
  line-height: 1.2;
}

.product-card__meta-item--ok {
  color: #1db74e;
  font-weight: 600;
}

.product-card__old-price {
  margin: 0;
  color: var(--muted-foreground);
  font-size: 12px;
  font-weight: 500;
  text-decoration: line-through;
  line-height: 1;
}

.price-row {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 6px;
  min-height: 20px;
}

.price-row strong {
  font-size: clamp(20px, 1.28vw, 24px);
  line-height: 1;
  letter-spacing: -0.015em;
}

.product-card__discount-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 2px 8px;
  background: color-mix(in srgb, #ff7a59 22%, white);
  color: #e14b2b;
  border: 1px solid color-mix(in srgb, #ff7a59 35%, white);
  font-size: 11px;
  line-height: 1;
  font-weight: 700;
}

.action {
  display: block;
  text-align: center;
  width: auto;
  min-width: 148px;
  padding: 7px 14px;
  border-radius: 9px;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  line-height: 1.2;
  transition:
    background-color 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease;
}

.action--cart {
  background: #15a9e8;
  color: var(--primary-foreground);
  border: 1px solid transparent;
}

.action--cart:hover {
  background: #0f9fdb;
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
  position: relative;
  display: flex;
  align-items: center;
  min-height: 42px;
  width: 100%;
  max-width: 220px;
}

.cart-stepper__label {
  position: absolute;
  top: -13px;
  left: 0;
  color: var(--muted-foreground);
  font-size: 10px;
  font-weight: 600;
}

.cart-stepper__controls {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--background);
  padding: 4px 5px;
}

.cart-stepper__controls strong {
  flex: 1 1 auto;
  text-align: center;
  font-size: 17px;
  line-height: 1.1;
  letter-spacing: -0.01em;
}

.cart-stepper__controls button {
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
    border-radius: 14px;
    padding: 8px;
  }

  .unified-product-card img {
    height: 100%;
  }

  .product-card__title-link h3 {
    font-size: 17px;
  }

  .price-row strong {
    font-size: 18px;
  }

  .action {
    font-size: 12px;
    padding: 7px 12px;
    min-width: 132px;
  }

  .product-card__brand-link,
  .product-card__reward,
  .product-card__meta-row {
    font-size: 10px;
  }
}
</style>
