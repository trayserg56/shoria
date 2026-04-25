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
    <button
      type="button"
      class="wishlist-toggle"
      :class="{ 'wishlist-toggle--active': isWishlisted }"
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
      class="compare-toggle"
      :class="{ 'compare-toggle--active': isCompared }"
      :aria-label="isCompared ? 'Убрать из сравнения' : 'Добавить в сравнение'"
      @click="toggleCompare"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path
          d="M10 3H5a2 2 0 0 0-2 2v5h2V5h5V3zm9 11v5a2 2 0 0 1-2 2h-5v-2h5v-5h2zM3 14v5a2 2 0 0 0 2 2h5v-2H5v-5H3zm16-9h-5V3h5a2 2 0 0 1 2 2v5h-2V5zM8 8h2v8H8V8zm6 0h2v8h-2V8z"
        />
      </svg>
    </button>

    <RouterLink class="product-link" :to="toProductRoute(product)" @click="onProductClick">
      <img :src="productImageUrl" :alt="product.name" loading="lazy" @error="onProductImageError" />
      <div class="product-card__content">
        <p class="product-card__category">{{ product.category?.name ?? 'Sneakers' }}</p>
        <div v-if="product.tags?.length" class="product-card__tags">
          <span v-for="tag in product.tags" :key="`${product.id}-${tag.code}`" class="tag-badge">
            {{ tag.label }}
          </span>
        </div>
        <h3>{{ product.name }}</h3>
        <div class="price-row">
          <strong>{{ formatPrice(product.price, product.currency) }}</strong>
          <s v-if="product.old_price">{{ formatPrice(product.old_price, product.currency) }}</s>
        </div>
        <p v-if="isOutOfStock" class="product-card__stock product-card__stock--empty">Нет в наличии</p>
      </div>
    </RouterLink>
    <button
      v-if="product.brand"
      type="button"
      class="product-card__brand-link"
      @click.stop="openBrandCatalog(product.brand)"
    >
      {{ product.brand }}
    </button>

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
  overflow: hidden;
  border-radius: 18px;
  background: var(--card-bg, #fff);
  border: 1px solid rgb(226 217 203 / 72%);
  box-shadow: 0 6px 18px rgb(16 24 40 / 4%);
}

.product-link {
  display: flex;
  flex-direction: column;
  flex: 1;
  color: inherit;
  text-decoration: none;
}

.unified-product-card img {
  width: 100%;
  height: 186px;
  object-fit: cover;
}

.product-card__content {
  display: grid;
  grid-template-rows: auto auto minmax(54px, auto) auto auto;
  align-content: start;
  gap: 6px;
  padding: 14px 16px 16px;
}

.product-card__actions {
  min-height: 78px;
  padding: 0 16px 16px;
}

.product-card__category {
  font-size: 12px;
  color: #7f8ca8;
}

.product-card__brand {
  margin: 0;
  font-size: 13px;
  color: #4f5a74;
}

.product-card__brand-link {
  margin: 0 16px 8px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #4f5a74;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}

.product-card__brand-link:hover {
  color: var(--color-accent, #bf4b08);
}

.product-card h3 {
  margin: 0;
}

.product-card__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-height: 28px;
}

.tag-badge {
  padding: 3px 8px;
  border-radius: 999px;
  background: #fff2e8;
  color: #c74803;
  font-size: 11px;
  font-weight: 600;
}

.price-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 36px;
}

.price-row strong {
  font-size: 20px;
}

.price-row s {
  color: #8a95ab;
}

.product-card__stock {
  margin: 2px 0 0;
  font-size: 13px;
  color: #7f8ca8;
}

.product-card__stock--empty {
  color: #b2552f;
  font-weight: 600;
}

.action {
  display: block;
  text-align: center;
  width: 100%;
  padding: 10px 14px;
  border: none;
  border-radius: 12px;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.action--cart {
  background: #1f2233;
  color: #fff;
}

.action--wishlist {
  background: #fff4ea;
  color: #b2552f;
  border: 1px solid #f0c7a5;
}

.action--cart:disabled {
  opacity: 0.7;
  cursor: default;
}

.cart-stepper {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 52px;
}

.cart-stepper__label {
  position: absolute;
  top: -18px;
  left: 0;
  color: #5b6b89;
  font-size: 12px;
  font-weight: 600;
}

.cart-stepper__controls {
  width: 100%;
  display: grid;
  grid-template-columns: 40px 1fr 40px;
  align-items: center;
  gap: 8px;
  border: 1px solid #d6d3cc;
  border-radius: 12px;
  background: #fff;
  padding: 6px;
}

.cart-stepper__controls strong {
  text-align: center;
  font-size: 18px;
}

.cart-stepper__controls button {
  height: 36px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #f8f7f4;
  font: inherit;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
}

.cart-stepper__controls button:disabled {
  opacity: 0.6;
  cursor: default;
}

.wishlist-toggle,
.compare-toggle {
  position: absolute;
  display: grid;
  place-items: center;
  z-index: 2;
  right: 10px;
  width: 42px;
  height: 42px;
  padding: 0;
  border: 1px solid rgb(255 255 255 / 70%);
  border-radius: 12px;
  background: rgb(255 255 255 / 92%);
  cursor: pointer;
}

.wishlist-toggle {
  top: 10px;
  color: #1f2233;
}

.compare-toggle {
  top: 58px;
  color: #3a4d73;
}

.wishlist-toggle--active {
  border-color: #f35b04;
  background: #fff2e8;
  color: #c74803;
}

.compare-toggle--active {
  border-color: #4465a8;
  background: #eef2fb;
  color: #2f4b8b;
}

.wishlist-toggle svg,
.compare-toggle svg {
  width: 19px;
  height: 19px;
  fill: currentColor;
}
</style>
