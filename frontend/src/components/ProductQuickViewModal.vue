<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { X } from 'lucide-vue-next'
import { closeProductQuickView, productQuickViewState } from '@/lib/product-quick-view'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toProductRoute } from '@/lib/product-route'
import { trackEvent } from '@/lib/analytics'
import { useCartStore } from '@/stores/cart'
import Loader from '@/components/ui/loader/Loader.vue'

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
}

const cartStore = useCartStore()

const product = ref<ProductPayload | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const selectedVariantId = ref<number | null>(null)
const activeImageIndex = ref(0)
const isCartBusy = ref(false)

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

      return
    }

    void loadProduct(state.slug)
  },
  { immediate: true },
)

watch(selectedVariantId, () => {
  activeImageIndex.value = 0
})

watch(isOpen, (open) => {
  if (open) {
    window.addEventListener('keydown', onEscape)
  } else {
    window.removeEventListener('keydown', onEscape)
  }
})

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    e.preventDefault()
    close()
  }
}

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEscape)
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
              <div class="qv-main-image">
                <img
                  v-if="activeImage"
                  :src="resolveImageSrc(activeImage.url)"
                  :alt="activeImage.alt || product.name"
                  loading="eager"
                  decoding="async"
                  @error="applyImageFallback"
                />
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
              <p v-if="product.brand" class="qv-brand">{{ product.brand }}</p>
              <h2 id="qv-title" class="qv-title">{{ product.name }}</h2>

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

              <div class="qv-actions">
                <button
                  type="button"
                  class="qv-btn qv-btn--primary"
                  :disabled="effectiveStock <= 0 || isCartBusy"
                  @click="addToCart"
                >
                  {{ isCartBusy ? 'Добавляем…' : 'В корзину' }}
                </button>
                <RouterLink :to="productRoute" class="qv-btn qv-btn--outline" @click="close">
                  Открыть карточку
                </RouterLink>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
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
}

.qv-dialog {
  position: relative;
  width: min(960px, 100%);
  max-height: min(92vh, 900px);
  overflow: auto;
  border-radius: 16px;
  background: var(--card, #fff);
  border: 1px solid var(--border, #e5e7eb);
  box-shadow: 0 24px 64px rgb(15 23 42 / 18%);
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
  padding: 16px;
  background: var(--muted, #f9fafb);
}

@media (max-width: 800px) {
  .qv-media {
    border-right: none;
    border-bottom: 1px solid var(--border, #e5e7eb);
  }
}

.qv-main-image {
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
  border: 1px solid var(--border);
}

.qv-main-image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qv-thumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
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

.qv-title {
  margin: 0;
  font-size: clamp(20px, 2.4vw, 26px);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
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

.qv-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: auto;
  padding-top: 8px;
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

.qv-btn--outline {
  background: transparent;
  border-color: var(--border);
  color: var(--foreground);
}

.qv-btn--outline:hover {
  border-color: color-mix(in srgb, var(--primary), transparent 50%);
  color: var(--primary);
}
</style>
