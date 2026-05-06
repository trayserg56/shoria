<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { X } from 'lucide-vue-next'
import { closeProductQuickView, productQuickViewState } from '@/lib/product-quick-view'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toProductRoute } from '@/lib/product-route'
import { trackEvent } from '@/lib/analytics'
import { toast } from '@/lib/toast'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore, type WishlistItem } from '@/stores/wishlist'
import { useCompareStore, type CompareItem } from '@/stores/compare'
import { useOneClickCheckoutModalStore } from '@/stores/one-click-checkout-modal'
import Loader from '@/components/ui/loader/Loader.vue'
import ProductImageLightbox from '@/components/ProductImageLightbox.vue'
import { characteristicsCatalogRoute } from '@/lib/catalog-characteristics'

type ProductImage = {
  url: string
  alt: string | null
  is_cover: boolean
}

type ProductVariantPayload = {
  id: number
  slug: string
  size_label: string
  color_label: string | null
  sku: string | null
  price: number | null
  stock: number
  images: ProductImage[]
  has_custom_images: boolean
}

type ProductPayload = {
  id: number
  name: string
  brand: string | null
  slug: string
  sku: string | null
  description: string | null
  price: number
  old_price: number | null
  currency: string
  stock: number
  has_variants: boolean
  category: { name: string; slug: string } | null
  tags: Array<{ code: string; label: string }>
  reviews_summary: { count: number; average: number | null }
  selected_variant_slug: string | null
  variants: ProductVariantPayload[]
  images: ProductImage[]
  characteristics?: Array<{
    group: string | null
    name: string
    values: string[]
  }>
}

const cartStore = useCartStore()
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const oneClickModalStore = useOneClickCheckoutModalStore()
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const { isAuthenticated } = storeToRefs(authStore)

const product = ref<ProductPayload | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const selectedVariantId = ref<number | null>(null)
const activeImageIndex = ref(0)
const isCartBusy = ref(false)
const quickActionError = ref('')
const qvImageLightboxOpen = ref(false)

const isOpen = computed(() => productQuickViewState.value !== null)

const selectedVariant = computed(
  () => product.value?.variants.find((v) => v.id === selectedVariantId.value) ?? null,
)

const effectivePrice = computed(() => selectedVariant.value?.price ?? product.value?.price ?? 0)

const effectiveStock = computed(() => selectedVariant.value?.stock ?? product.value?.stock ?? 0)

const displayImages = computed((): ProductImage[] => {
  if (!product.value) {
    return []
  }

  const variant = selectedVariant.value

  if (variant?.images?.length) {
    return [...variant.images].sort((a, b) => Number(b.is_cover) - Number(a.is_cover))
  }

  return [...product.value.images].sort((a, b) => Number(b.is_cover) - Number(a.is_cover))
})

const activeImage = computed(() => displayImages.value[activeImageIndex.value] ?? null)

const qvLightboxSlides = computed(() => {
  if (!product.value) {
    return []
  }

  const name = product.value.name

  return displayImages.value.map((img) => ({
    url: img.url,
    alt: img.alt || name,
  }))
})

const isWishlisted = computed(() => (product.value ? wishlistStore.has(product.value.id) : false))
const isCompared = computed(() => (product.value ? compareStore.has(product.value.id) : false))

function characteristicRowValues(item: {
  name: string
  values?: unknown
  value?: unknown
}): string[] {
  if (Array.isArray(item.values) && item.values.length > 0) {
    return item.values.map((x) => String(x).trim()).filter(Boolean)
  }

  const v = typeof item.value === 'string' ? item.value.trim() : ''

  return v !== '' ? [v] : []
}

const groupedCharacteristics = computed(() => {
  if (!product.value?.characteristics?.length) {
    return []
  }

  const groups = new Map<string, Array<{ name: string; values: string[] }>>()

  for (const item of product.value.characteristics) {
    const values = characteristicRowValues(item)
    if (values.length === 0) {
      continue
    }

    const groupName = item.group?.trim() || 'Характеристики'

    if (!groups.has(groupName)) {
      groups.set(groupName, [])
    }

    groups.get(groupName)?.push({
      name: item.name,
      values,
    })
  }

  return Array.from(groups.entries()).map(([name, items]) => ({
    name,
    items,
  }))
})

const descriptionPreview = computed(() => {
  const raw = product.value?.description?.trim()

  if (!raw) {
    return ''
  }

  const div = document.createElement('div')
  div.innerHTML = raw
  const text = (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim()

  return text.length > 480 ? `${text.slice(0, 480)}…` : text
})

const productRoute = computed(() => {
  if (!product.value) {
    return { name: 'home' as const }
  }

  return toProductRoute({
    slug: product.value.slug,
    category: product.value.category,
    variant_slug: selectedVariant.value?.slug ?? null,
  })
})

function formatPrice(value: number, currency: string): string {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function resolveInitialVariant(data: ProductPayload): ProductVariantPayload | null {
  if (!data.variants.length) {
    return null
  }

  if (data.selected_variant_slug) {
    const found = data.variants.find((v) => v.slug === data.selected_variant_slug)

    if (found) {
      return found
    }
  }

  return data.variants.find((v) => v.stock > 0) ?? data.variants[0] ?? null
}

async function loadProduct(slug: string) {
  isLoading.value = true
  hasError.value = false
  product.value = null
  selectedVariantId.value = null
  activeImageIndex.value = 0
  qvImageLightboxOpen.value = false

  try {
    const data = await fetchJson<ProductPayload>(`/api/products/${encodeURIComponent(slug)}`)
    product.value = data

    const initial = resolveInitialVariant(data)
    selectedVariantId.value = initial?.id ?? null

    void trackEvent('quick_view_open', {
      slug: data.slug,
      variant: initial?.slug ?? null,
      source: productQuickViewState.value?.source ?? 'unknown',
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

watch(
  productQuickViewState,
  (state) => {
    if (!state) {
      product.value = null
      hasError.value = false
      selectedVariantId.value = null
      activeImageIndex.value = 0
      qvImageLightboxOpen.value = false

      return
    }

    void loadProduct(state.slug)
  },
  { immediate: true },
)

watch(selectedVariantId, () => {
  activeImageIndex.value = 0
  quickActionError.value = ''
  qvImageLightboxOpen.value = false
})

function lockBodyScroll() {
  const html = document.documentElement
  const body = document.body
  const gap = Math.max(0, window.innerWidth - html.clientWidth)

  html.style.overflow = 'hidden'
  body.style.overflow = 'hidden'

  if (gap > 0) {
    body.style.paddingRight = `${gap}px`
  }
}

function unlockBodyScroll() {
  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''
  document.body.style.paddingRight = ''
}

watch(isOpen, (open) => {
  if (open) {
    window.addEventListener('keydown', onEscape)
    lockBodyScroll()
    return
  }

  quickActionError.value = ''
  window.removeEventListener('keydown', onEscape)
  unlockBodyScroll()
})

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    if (qvImageLightboxOpen.value) {
      qvImageLightboxOpen.value = false
      return
    }
    e.preventDefault()
    close()
  }
}

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEscape)
  unlockBodyScroll()
})

function close() {
  closeProductQuickView()
}

async function addToCart() {
  if (!product.value || isCartBusy.value || effectiveStock.value <= 0) {
    return
  }

  isCartBusy.value = true

  try {
    await cartStore.addItemBySlug(
      product.value.slug,
      1,
      selectedVariant.value?.id,
    )

    void trackEvent('add_to_cart', {
      source: 'quick_view',
      slug: product.value.slug,
      price: effectivePrice.value,
      qty: 1,
    })
  } catch (error) {
    console.error(error)
  } finally {
    isCartBusy.value = false
  }
}

function selectVariant(v: ProductVariantPayload) {
  selectedVariantId.value = v.id
}

function toWishlistItem(): WishlistItem {
  const p = product.value
  if (!p) {
    throw new Error('product')
  }

  return {
    id: p.id,
    slug: p.slug,
    name: p.name,
    price: effectivePrice.value,
    old_price: p.old_price,
    stock: effectiveStock.value,
    currency: p.currency,
    image_url: displayImages.value[0]?.url ?? null,
    category: p.category,
  }
}

function toCompareItem(): CompareItem {
  const p = product.value
  if (!p) {
    throw new Error('product')
  }

  return {
    id: p.id,
    slug: p.slug,
    name: p.name,
    price: effectivePrice.value,
    old_price: p.old_price,
    currency: p.currency,
    image_url: displayImages.value[0]?.url ?? null,
    stock: effectiveStock.value,
    category: p.category,
    tags: p.tags,
  }
}

function toggleWishlist() {
  if (!product.value) {
    return
  }

  const added = wishlistStore.toggle(toWishlistItem())

  void trackEvent('toggle_wishlist', {
    source: 'quick_view',
    slug: product.value.slug,
    action: added ? 'added' : 'removed',
  })
}

function toggleCompare() {
  if (!product.value) {
    return
  }

  const result = compareStore.toggle(toCompareItem())

  void trackEvent('toggle_compare', {
    source: 'quick_view',
    slug: product.value.slug,
    action: result.active ? 'added' : 'removed',
  })
}

async function oneClickBuy() {
  quickActionError.value = ''

  if (!product.value || effectiveStock.value <= 0) {
    return
  }

  if (product.value.has_variants && product.value.variants.length > 0 && !selectedVariantId.value) {
    quickActionError.value = 'Выберите вариант перед быстрым заказом.'
    return
  }

  if (!isAuthenticated.value) {
    toast.info('Войдите в аккаунт, чтобы купить в 1 клик.')

    await router.replace({
      query: { ...route.query, auth: '1' },
    })
    return
  }

  oneClickModalStore.open({
    productName: product.value.name,
    productSlug: product.value.slug,
    productVariantId: selectedVariant.value?.id,
    qty: 1,
    productPrice: effectivePrice.value,
    currency: product.value.currency,
    source: 'quick_view',
  })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="qv-backdrop"
      role="dialog"
      aria-modal="true"
      aria-labelledby="qv-title"
      @click.self="close"
    >
      <div class="qv-dialog">
        <button type="button" class="qv-close" aria-label="Закрыть" @click="close">
          <X :size="22" :stroke-width="2" />
        </button>

        <div v-if="isLoading" class="qv-state">
          <Loader :size="40" compact label="" />
          <p>Загружаем товар…</p>
        </div>

        <div v-else-if="hasError" class="qv-state">
          <p>Не удалось загрузить товар.</p>
          <button type="button" class="qv-linkish" @click="close">Закрыть</button>
        </div>

        <template v-else-if="product">
          <div class="qv-grid">
            <div class="qv-media">
              <div class="qv-media-inner">
                <div class="qv-main-image qv-main-image-trigger-wrapper">
                  <button
                    v-if="activeImage"
                    type="button"
                    class="qv-main-image-trigger"
                    :aria-label="`Открыть фото на весь экран (${activeImageIndex + 1} из ${displayImages.length})`"
                    @click.stop="qvImageLightboxOpen = true"
                  >
                    <img
                      :src="resolveImageSrc(activeImage.url)"
                      :alt="activeImage.alt || product.name"
                      loading="eager"
                      decoding="async"
                      @error="applyImageFallback"
                    />
                  </button>
                </div>
                <div class="qv-media-rail">
                  <button
                    type="button"
                    class="qv-rail-btn"
                    :class="{ 'qv-rail-btn--active': isWishlisted }"
                    :aria-label="isWishlisted ? 'Убрать из избранного' : 'Добавить в избранное'"
                    title="Избранное"
                    @click.stop="toggleWishlist"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path
                        d="M12 20.7l-1.1-1C6 15.2 3 12.5 3 9.2 3 6.5 5.1 4.4 7.8 4.4c1.5 0 3 .7 4 1.9 1-1.2 2.5-1.9 4-1.9 2.7 0 4.8 2.1 4.8 4.8 0 3.3-3 6-7.9 10.5l-1.1 1z"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="qv-rail-btn"
                    :class="{ 'qv-rail-btn--active': isCompared }"
                    :aria-label="isCompared ? 'Убрать из сравнения' : 'Добавить в сравнение'"
                    title="Сравнение"
                    @click.stop="toggleCompare"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path
                        d="M10 3H5a2 2 0 0 0-2 2v5h2V5h5V3zm9 11v5a2 2 0 0 1-2 2h-5v-2h5v-5h2zM3 14v5a2 2 0 0 0 2 2h5v-2H5v-5H3zm16-9h-5V3h5a2 2 0 0 1 2 2v5h-2V5zM8 8h2v8H8V8zm6 0h2v8h-2V8z"
                      />
                    </svg>
                  </button>
                  <button
                    v-if="effectiveStock > 0"
                    type="button"
                    class="qv-rail-btn"
                    aria-label="Купить в один клик"
                    title="Купить в один клик"
                    @click.stop="oneClickBuy"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M13 3L5 17h8l-1.5 4L19 11h-7.8L13 3z" />
                    </svg>
                  </button>
                </div>
              </div>
              <div v-if="displayImages.length > 1" class="qv-thumbs" role="tablist" aria-label="Фото товара">
                <button
                  v-for="(img, index) in displayImages"
                  :key="`${img.url}-${index}`"
                  type="button"
                  class="qv-thumb"
                  :class="{ 'qv-thumb--active': index === activeImageIndex }"
                  :aria-selected="index === activeImageIndex"
                  @click="activeImageIndex = index"
                >
                  <img :src="resolveImageSrc(img.url)" :alt="img.alt || ''" loading="lazy" @error="applyImageFallback" />
                </button>
              </div>
            </div>

            <div class="qv-body">
              <p v-if="product.brand" class="qv-brand">
                <RouterLink
                  :to="productRoute"
                  class="qv-brand-link"
                  @click="close"
                >
                  {{ product.brand }}
                </RouterLink>
              </p>
              <h2 id="qv-title" class="qv-title">
                <RouterLink
                  :to="productRoute"
                  class="qv-title-link"
                  @click="close"
                >
                  {{ product.name }}
                </RouterLink>
              </h2>

              <div class="qv-price-row">
                <strong>{{ formatPrice(effectivePrice, product.currency) }}</strong>
                <span v-if="product.old_price && product.old_price > effectivePrice" class="qv-old">
                  {{ formatPrice(product.old_price, product.currency) }}
                </span>
              </div>

              <div v-if="product.variants.length > 1" class="qv-variant-block">
                <span class="qv-label">Вариант</span>
                <div class="qv-variants">
                  <button
                    v-for="v in product.variants"
                    :key="v.id"
                    type="button"
                    class="qv-variant"
                    :class="{ 'qv-variant--active': v.id === selectedVariantId }"
                    @click="selectVariant(v)"
                  >
                    <span class="qv-variant-size">{{ v.size_label }}</span>
                    <span v-if="v.color_label" class="qv-variant-color">{{ v.color_label }}</span>
                  </button>
                </div>
              </div>

              <p class="qv-stock" :class="{ 'qv-stock--out': effectiveStock <= 0 }">
                {{ effectiveStock > 0 ? `✓ В наличии${effectiveStock < 50 ? `: ${effectiveStock} шт.` : ''}` : 'Нет в наличии' }}
              </p>

              <div v-if="descriptionPreview" class="qv-desc">
                {{ descriptionPreview }}
              </div>

              <section v-if="groupedCharacteristics.length" class="qv-characteristics">
                <h3 class="qv-characteristics__heading">Характеристики</h3>
                <article
                  v-for="group in groupedCharacteristics"
                  :key="`qv-char-${group.name}`"
                  class="qv-characteristics__group"
                >
                  <h4 v-if="group.name !== 'Характеристики'" class="qv-characteristics__group-title">
                    {{ group.name }}
                  </h4>
                  <ul class="qv-characteristics__list">
                    <li
                      v-for="(row, index) in group.items"
                      :key="`qv-char-row-${group.name}-${index}`"
                      class="qv-characteristics__row"
                    >
                      <span class="qv-characteristics__name">{{ row.name }}</span>
                      <div class="qv-characteristics__values-cell">
                        <template v-for="(val, vIndex) in row.values" :key="`${row.name}-${vIndex}-${val}`">
                          <RouterLink
                            class="qv-characteristics__value-link"
                            :to="
                              characteristicsCatalogRoute(row.name, val, {
                                categorySlug: product.category?.slug,
                              })
                            "
                            :title="`Подобрать товары: ${row.name} — ${val}`"
                            @click="close"
                          >
                            {{ val }}
                          </RouterLink>
                          <span
                            v-if="vIndex < row.values.length - 1"
                            class="qv-characteristics__value-sep"
                            aria-hidden="true"
                          >
                            ,&nbsp;
                          </span>
                        </template>
                      </div>
                    </li>
                  </ul>
                </article>
              </section>

              <div class="qv-actions">
                <p v-if="quickActionError" class="qv-action-error">{{ quickActionError }}</p>
                <button
                  type="button"
                  class="qv-btn qv-btn--primary"
                  :disabled="effectiveStock <= 0 || isCartBusy"
                  @click="addToCart"
                >
                  {{ isCartBusy ? 'Добавляем…' : 'В корзину' }}
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
      <ProductImageLightbox
        v-if="product && isOpen"
        v-model="qvImageLightboxOpen"
        :slides="qvLightboxSlides"
        :anchor-index="activeImageIndex"
        :lock-body-scroll="false"
        @sync-index="activeImageIndex = $event"
      />
    </Teleport>
</template>

<style scoped>
.qv-backdrop {
  position: fixed;
  inset: 0;
  z-index: 130;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgb(15 23 42 / 48%);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.qv-dialog {
  position: relative;
  width: min(960px, 100%);
  max-height: min(92dvh, 900px);
  overflow: auto;
  border-radius: 16px;
  background: var(--card, #fff);
  border: 1px solid var(--border, #e5e7eb);
  box-shadow: 0 24px 64px rgb(15 23 42 / 18%);
}

@media (min-width: 801px) {
  .qv-dialog {
    height: calc(100dvh - 32px);
    max-height: calc(100dvh - 32px);
    min-height: min(440px, calc(100dvh - 32px));
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .qv-dialog > .qv-state,
  .qv-dialog > .qv-grid {
    flex: 1;
    min-height: 0;
  }

  .qv-grid {
    overflow: hidden;
    grid-template-rows: minmax(0, 1fr);
  }

  .qv-media {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .qv-media-inner {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
  }

  .qv-thumbs {
    flex-shrink: 0;
  }

  .qv-body {
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
}

.qv-close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 4;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 10px;
  background: var(--muted, #f3f4f6);
  color: var(--foreground);
  cursor: pointer;
}

.qv-close:hover {
  background: color-mix(in srgb, var(--muted), var(--foreground) 8%);
}

.qv-state {
  display: grid;
  place-items: center;
  gap: 12px;
  padding: 48px 24px;
  text-align: center;
  color: var(--muted-foreground);
}

.qv-linkish {
  border: none;
  background: none;
  color: var(--primary);
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
}

.qv-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  gap: 0;
}

@media (max-width: 800px) {
  .qv-grid {
    grid-template-columns: 1fr;
  }
}

.qv-media {
  border-right: 1px solid var(--border, #e5e7eb);
  padding: 12px;
  background: var(--card, #fff);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (max-width: 800px) {
  .qv-media {
    border-right: none;
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
}

.qv-media-inner {
  position: relative;
}

.qv-main-image-trigger-wrapper {
  border: none;
}

.qv-main-image-trigger {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  cursor: zoom-in;
  height: 100%;
}

.qv-main-image-trigger:focus-visible {
  outline: 2px solid var(--primary, #1d4ed8);
  outline-offset: 2px;
  border-radius: 10px;
}

.qv-main-image {
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  clip-path: inset(0 round 12px);
  background: transparent;
  border: none;
}

.qv-main-image img,
.qv-main-image-trigger img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: top center;
}

.qv-media-rail {
  position: absolute;
  right: 8px;
  top: 8px;
  z-index: 2;
  display: grid;
  gap: 6px;
}

.qv-rail-btn {
  width: 36px;
  height: 36px;
  padding: 0;
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

.qv-rail-btn svg {
  width: 15px;
  height: 15px;
  fill: currentColor;
}

.qv-rail-btn--active {
  color: var(--primary, #1d4ed8);
  border-color: color-mix(in srgb, var(--primary), transparent 65%);
  background: #fff;
}

.qv-rail-btn:hover {
  color: var(--primary, #1d4ed8);
}

.qv-thumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.qv-thumb {
  width: 56px;
  height: 56px;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  background: #fff;
}

.qv-thumb--active {
  border-color: var(--primary, #1d4ed8);
}

.qv-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.qv-body {
  padding: 24px 24px 28px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

.qv-brand {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.qv-brand-link {
  color: inherit;
  text-decoration: none;
  transition: color 0.15s ease;
}

.qv-brand-link:hover {
  color: var(--primary, #1d4ed8);
}

.qv-brand-link:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--primary), transparent 35%);
  outline-offset: 2px;
  border-radius: 3px;
}

.qv-title {
  margin: 0;
  font-size: clamp(20px, 2.4vw, 26px);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.qv-title-link {
  color: inherit;
  text-decoration: none;
  transition: color 0.15s ease;
}

.qv-title-link:hover {
  color: var(--primary, #1d4ed8);
}

.qv-title-link:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--primary), transparent 35%);
  outline-offset: 3px;
  border-radius: 4px;
}

.qv-price-row {
  display: flex;
  align-items: baseline;
  gap: 12px;
  flex-wrap: wrap;
}

.qv-price-row strong {
  font-size: 26px;
  font-weight: 700;
}

.qv-old {
  font-size: 15px;
  color: var(--muted-foreground);
  text-decoration: line-through;
}

.qv-variant-block {
  display: grid;
  gap: 8px;
}

.qv-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted-foreground);
}

.qv-variants {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.qv-variant {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--background);
  font: inherit;
  cursor: pointer;
  text-align: left;
  transition:
    border-color 0.15s ease,
    background 0.15s ease;
}

.qv-variant--active {
  border-color: color-mix(in srgb, var(--primary), transparent 45%);
  background: var(--accent, #eff6ff);
  color: var(--accent-foreground, #1e3a8a);
}

.qv-variant-size {
  font-weight: 700;
  font-size: 14px;
}

.qv-variant-color {
  font-size: 12px;
  opacity: 0.85;
}

.qv-stock {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #15803d;
}

.qv-stock--out {
  color: var(--destructive, #dc2626);
}

.qv-desc {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--foreground);
  opacity: 0.92;
}

.qv-characteristics {
  margin: 8px 0 0;
  padding-top: 12px;
  border-top: 1px solid var(--border, #e5e7eb);
  overflow-y: auto;
  scrollbar-gutter: stable;
  padding-inline-end: max(14px, 0.75rem);
}

@media (max-width: 800px) {
  .qv-characteristics {
    max-height: min(240px, 35vh);
  }
}

.qv-characteristics__heading {
  margin: 0 0 10px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--muted-foreground);
}

.qv-characteristics__group + .qv-characteristics__group {
  margin-top: 12px;
}

.qv-characteristics__group-title {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 700;
  color: var(--foreground);
}

.qv-characteristics__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.qv-characteristics__row {
  margin: 0;
  display: flex;
  align-items: baseline;
  gap: 12px 20px;
  font-size: 13px;
  line-height: 1.35;
}

.qv-characteristics__name {
  flex: 1 1 0;
  min-width: 0;
  max-width: 50%;
  color: #5b6479;
}

.qv-characteristics__values-cell {
  flex: 0 1 auto;
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0 2px;
  justify-content: flex-end;
  align-items: baseline;
  align-content: baseline;
  min-width: 0;
  max-width: 50%;
  margin: -4px -2px -4px auto;
  padding: 4px 2px;
  text-align: right;
}

.qv-characteristics__value-link {
  display: inline;
  overflow-wrap: anywhere;
  word-break: normal;
  font-weight: 600;
  color: #1f2233;
  text-decoration: underline solid;
  text-underline-offset: 2px;
  text-decoration-thickness: 1px;
  text-decoration-color: color-mix(in srgb, #1f2233, transparent 12%);
  border-radius: 2px;
  outline-offset: 2px;
  transition: color 0.15s ease;
}

.qv-characteristics__value-link:hover {
  color: var(--primary, #1d4ed8);
  text-decoration-color: color-mix(in srgb, var(--primary), transparent 35%);
}

.qv-characteristics__value-sep {
  color: inherit;
  font-weight: 600;
  user-select: none;
}

.qv-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: auto;
  padding-top: 8px;
}

.qv-action-error {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #c2410c;
}

.qv-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 46px;
  padding: 0 18px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}

.qv-btn--primary {
  background: var(--primary, #1d4ed8);
  color: var(--primary-foreground, #fff);
}

.qv-btn--primary:hover:not(:disabled) {
  background: color-mix(in srgb, var(--primary), #000 8%);
}

.qv-btn--primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* На десктопе блок фото по высоте контента — иначе contain + высокая обёртка дают «ровный»
   низ картинки без видимых нижних скруглений */
@media (min-width: 801px) {
  .qv-main-image {
    flex: 0 0 auto;
    width: 100%;
    min-height: 0;
    aspect-ratio: unset;
    overflow: hidden;
    border-radius: 12px;
    clip-path: inset(0 round 12px);
  }

  .qv-main-image img,
  .qv-main-image-trigger img {
    height: auto;
    max-height: none;
  }

  /* flex: min-height: 0 иначе overflow не даёт скролл; max-height только на мобилке */
  .qv-characteristics {
    flex: 1 1 0;
    min-height: 0;
    max-height: none;
  }
}
</style>
