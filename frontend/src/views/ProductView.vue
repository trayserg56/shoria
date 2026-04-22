<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore, type WishlistItem } from '@/stores/wishlist'
import { useCompareStore, type CompareItem } from '@/stores/compare'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { toProductRoute } from '@/lib/product-route'
import { clearStructuredData, setSeoMeta, setStructuredData } from '@/lib/seo'
import { saveRecentlyViewed } from '@/lib/recently-viewed'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import {
  buildBreadcrumbStructuredData,
  buildProductStructuredData,
} from '@/lib/seo-templates'

type ProductImage = {
  url: string
  alt: string | null
  is_cover: boolean
}

type ProductPayload = {
  id: number
  name: string
  slug: string
  sku: string | null
  description: string | null
  seo_title: string | null
  seo_description: string | null
  price: number
  old_price: number | null
  currency: string
  stock: number
  has_variants: boolean
  category: {
    name: string
    slug: string
  } | null
  tags: Array<{
    code: string
    label: string
  }>
  variants: Array<{
    id: number
    size_label: string
    sku: string | null
    price: number | null
    stock: number
  }>
  images: ProductImage[]
}

type RecommendedProduct = {
  id: number
  name: string
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
}

type RecommendationsPayload = {
  source: 'co_purchase' | 'co_view' | 'featured_fallback'
  data: RecommendedProduct[]
}

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()
const { items: cartItems } = storeToRefs(cartStore)
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()

const product = ref<ProductPayload | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const addError = ref('')
const compareMessage = ref('')
const activeImageIndex = ref(0)
const selectedVariantId = ref<number | null>(null)
const recommendations = ref<RecommendedProduct[]>([])
const recommendationsSlider = ref<HTMLElement | null>(null)
const isCartBusy = ref(false)

const slug = computed(() => String(route.params.slug ?? ''))
const currentCategorySlug = computed(() => String(route.params.categorySlug ?? ''))
const activeImage = computed(() => product.value?.images[activeImageIndex.value] ?? null)
const selectedVariant = computed(
  () => product.value?.variants.find((variant) => variant.id === selectedVariantId.value) ?? null,
)
const effectivePrice = computed(() => selectedVariant.value?.price ?? product.value?.price ?? 0)
const effectiveStock = computed(() => selectedVariant.value?.stock ?? product.value?.stock ?? 0)
const isWishlisted = computed(() => (product.value ? wishlistStore.has(product.value.id) : false))
const isCompared = computed(() => (product.value ? compareStore.has(product.value.id) : false))
const selectedCartVariantId = computed(() => (product.value?.has_variants ? selectedVariantId.value : null))
const currentCartQty = computed(() => {
  if (!product.value) {
    return 0
  }

  const variantId = selectedCartVariantId.value ?? null

  return cartItems.value
    .filter(
      (item) =>
        item.product_id === product.value?.id &&
        (item.product_variant_id ?? null) === variantId,
    )
    .reduce((total, item) => total + item.qty, 0)
})
const catalogCategoryLink = computed(() =>
  product.value?.category?.slug
    ? { path: '/catalog', query: { category: product.value.category.slug } }
    : { path: '/catalog' },
)

function formatPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function scrollSlider(target: HTMLElement | null, direction: 'prev' | 'next') {
  if (!target) {
    return
  }

  const shift = Math.max(target.clientWidth * 0.82, 260)
  target.scrollBy({
    left: direction === 'next' ? shift : -shift,
    behavior: 'smooth',
  })
}

async function loadProduct() {
  if (!slug.value) {
    return
  }

  isLoading.value = true

  try {
    product.value = await fetchJson<ProductPayload>(`/api/products/${slug.value}`)
    const recommendationsPayload = await fetchJson<RecommendationsPayload>(
      `/api/products/${slug.value}/recommendations`,
    )
    recommendations.value = recommendationsPayload.data
    hasError.value = false
    activeImageIndex.value = 0
    selectedVariantId.value =
      product.value.variants.find((variant) => variant.stock > 0)?.id ??
      product.value.variants[0]?.id ??
      null
    const actualCategorySlug = product.value.category?.slug ?? ''

    if (actualCategorySlug && currentCategorySlug.value !== actualCategorySlug) {
      await router.replace(toProductRoute(product.value))
      return
    }

    setSeoMeta({
      title:
        product.value.seo_title?.trim() ||
        `${product.value.name} — ${product.value.category?.name ? `${product.value.category.name} · ` : ''}Shoria`,
      description:
        product.value.seo_description?.trim() ||
        product.value.description?.trim() ||
        `Купить ${product.value.name} в Shoria: актуальная цена, наличие и рекомендации.`,
      robots: 'index,follow',
      canonical: `${window.location.origin}/product/${product.value.category?.slug ?? ''}/${product.value.slug}`.replace(
        /\/product\/\//,
        '/product/',
      ),
    })
    setStructuredData([
      buildBreadcrumbStructuredData([
        { name: 'Главная', path: '/' },
        { name: 'Каталог', path: '/catalog' },
        ...(product.value.category
          ? [{ name: product.value.category.name, path: `/catalog?category=${product.value.category.slug}` }]
          : []),
        {
          name: product.value.name,
          path: `/product/${product.value.category?.slug ?? ''}/${product.value.slug}`.replace('//', '/'),
        },
      ]),
      buildProductStructuredData({
        slug: `${product.value.category?.slug ? `${product.value.category.slug}/` : ''}${product.value.slug}`,
        name: product.value.name,
        description: product.value.description,
        sku: product.value.sku,
        price: effectivePrice.value,
        currency: product.value.currency,
        imageUrl: product.value.images[0]?.url ?? null,
        categoryName: product.value.category?.name ?? null,
        availability: effectiveStock.value > 0 ? 'InStock' : 'OutOfStock',
      }),
    ])

    void trackEvent('view_product', {
      slug: product.value.slug,
      price: product.value.price,
      category: product.value.category?.slug ?? null,
    })

    saveRecentlyViewed({
      id: product.value.id,
      slug: product.value.slug,
      name: product.value.name,
      price: product.value.price,
      old_price: product.value.old_price,
      stock: effectiveStock.value,
      currency: product.value.currency,
      image_url: product.value.images[0]?.url ?? null,
      category: product.value.category,
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
    product.value = null
    recommendations.value = []
    clearStructuredData()
  } finally {
    isLoading.value = false
  }
}

async function addToCart() {
  if (!product.value) {
    return
  }

  if (product.value.has_variants && !selectedVariantId.value) {
    addError.value = 'Выберите размер перед добавлением в корзину.'
    return
  }

  addError.value = ''

  isCartBusy.value = true

  try {
    await cartStore.addItemBySlug(product.value.slug, 1, selectedVariantId.value ?? undefined)
  } catch (error) {
    console.error(error)
    addError.value = 'Не удалось добавить товар в корзину.'
    return
  } finally {
    isCartBusy.value = false
  }

  void trackEvent('add_to_cart', {
    slug: product.value.slug,
    price: effectivePrice.value,
    variant_id: selectedVariantId.value,
    qty: 1,
  })
}

async function changeCartQty(direction: 'inc' | 'dec') {
  if (!product.value || isCartBusy.value) {
    return
  }

  if (product.value.has_variants && !selectedVariantId.value) {
    addError.value = 'Выберите размер перед изменением количества.'
    return
  }

  addError.value = ''
  isCartBusy.value = true

  const variantId = selectedCartVariantId.value ?? null
  const entry = cartItems.value.find(
    (item) =>
      item.product_id === product.value?.id &&
      (item.product_variant_id ?? null) === variantId,
  )

  try {
    if (direction === 'inc') {
      if (!entry) {
        await cartStore.addItemBySlug(product.value.slug, 1, selectedVariantId.value ?? undefined)
      } else {
        await cartStore.updateQty(entry.id, entry.qty + 1)
      }
    } else if (entry) {
      if (entry.qty <= 1) {
        await cartStore.removeItem(entry.id)
      } else {
        await cartStore.updateQty(entry.id, entry.qty - 1)
      }
    }
  } catch (error) {
    console.error(error)
    addError.value = 'Не удалось изменить количество в корзине.'
  } finally {
    isCartBusy.value = false
  }
}

function currentWishlistItem(): WishlistItem | null {
  if (!product.value) {
    return null
  }

  return {
    id: product.value.id,
    slug: product.value.slug,
    name: product.value.name,
    price: product.value.price,
    old_price: product.value.old_price,
    stock: effectiveStock.value,
    currency: product.value.currency,
    image_url: product.value.images[0]?.url ?? null,
    category: product.value.category,
  }
}

function toggleWishlist() {
  const item = currentWishlistItem()

  if (!item) {
    return
  }

  const added = wishlistStore.toggle(item)

  void trackEvent('toggle_wishlist', {
    slug: item.slug,
    action: added ? 'added' : 'removed',
  })
}

function currentCompareItem(): CompareItem | null {
  if (!product.value) {
    return null
  }

  return {
    id: product.value.id,
    slug: product.value.slug,
    name: product.value.name,
    price: product.value.price,
    old_price: product.value.old_price,
    currency: product.value.currency,
    image_url: product.value.images[0]?.url ?? null,
    stock: effectiveStock.value,
    category: product.value.category,
    tags: product.value.tags,
  }
}

function toggleCompare() {
  const item = currentCompareItem()

  if (!item) {
    return
  }

  const result = compareStore.toggle(item)
  compareMessage.value = result.overflow
    ? 'В сравнении максимум 4 товара. Самый старый элемент был заменен.'
    : result.active
      ? 'Товар добавлен в сравнение.'
      : 'Товар удален из сравнения.'

  void trackEvent('toggle_compare', {
    slug: item.slug,
    action: result.active ? 'added' : 'removed',
    source: 'product',
  })
}

onMounted(loadProduct)

watch(
  () => route.fullPath,
  async () => {
    await loadProduct()
  },
)
</script>

<template>
  <main class="product-page">
    <p v-if="isLoading" class="status">Загружаем карточку товара...</p>
    <p v-if="hasError" class="status status--warn">Товар не найден или API недоступно.</p>

    <section v-if="product" class="product-layout">
      <nav class="breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <RouterLink to="/catalog">Каталог</RouterLink>
        <template v-if="product.category">
          <span>/</span>
          <RouterLink :to="catalogCategoryLink">{{ product.category.name }}</RouterLink>
        </template>
        <span>/</span>
        <span>{{ product.name }}</span>
      </nav>

      <div class="gallery">
        <img
          v-if="activeImage"
          class="gallery__main"
          :src="activeImage.url"
          :alt="activeImage.alt ?? product.name"
        />
        <div class="gallery__thumbs">
          <button
            v-for="(image, index) in product.images"
            :key="`${image.url}-${index}`"
            type="button"
            class="thumb"
            :class="{ 'thumb--active': index === activeImageIndex }"
            @click="activeImageIndex = index"
          >
            <img :src="image.url" :alt="image.alt ?? product.name" />
          </button>
        </div>
      </div>

      <article class="details">
        <p class="details__category">{{ product.category?.name ?? 'Sneakers' }}</p>
        <h1>{{ product.name }}</h1>
        <p class="details__sku">SKU: {{ product.sku ?? 'N/A' }}</p>

        <div class="price-row">
          <strong>{{ formatPrice(effectivePrice, product.currency) }}</strong>
          <s v-if="product.old_price">{{ formatPrice(product.old_price, product.currency) }}</s>
        </div>

        <div v-if="product.has_variants" class="sizes">
          <p class="sizes__title">Размер</p>
          <div class="sizes__grid">
            <button
              v-for="variant in product.variants"
              :key="variant.id"
              type="button"
              class="size-chip"
              :class="{ 'size-chip--active': selectedVariantId === variant.id }"
              :disabled="variant.stock <= 0"
              @click="selectedVariantId = variant.id"
            >
              {{ variant.size_label }}
            </button>
          </div>
        </div>

        <p class="details__stock">В наличии: {{ effectiveStock }} шт.</p>
        <p class="details__description">{{ product.description ?? 'Описание скоро обновим.' }}</p>

        <div class="cta-row">
          <button
            v-if="currentCartQty === 0"
            type="button"
            class="buy-button"
            :disabled="effectiveStock <= 0 || isCartBusy"
            @click="addToCart"
          >
            {{
              effectiveStock <= 0
                ? 'Нет в наличии'
                : isCartBusy
                  ? 'Добавляем...'
                  : 'Добавить в корзину'
            }}
          </button>
          <div v-else class="buy-stepper">
            <span class="buy-stepper__label">В корзине</span>
            <div class="buy-stepper__controls">
              <button type="button" :disabled="isCartBusy" @click="changeCartQty('dec')">−</button>
              <strong>{{ currentCartQty }}</strong>
              <button type="button" :disabled="isCartBusy" @click="changeCartQty('inc')">+</button>
            </div>
          </div>
          <button
            type="button"
            class="icon-button icon-button--wishlist"
            :class="{ 'icon-button--active': isWishlisted }"
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
            class="icon-button icon-button--compare"
            :class="{ 'icon-button--active': isCompared }"
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
        <p v-if="addError" class="status status--warn">{{ addError }}</p>
        <p v-if="compareMessage" class="status">{{ compareMessage }}</p>
      </article>
    </section>

    <section v-if="recommendations.length" class="recommendations">
      <header class="recommendations__header">
        <h2>Рекомендуем посмотреть</h2>
        <p>Похожие интересы других покупателей.</p>
      </header>
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(recommendationsSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(recommendationsSlider, 'next')">→</button>
      </div>
      <div ref="recommendationsSlider" class="recommendations__slider">
        <UnifiedProductCard
          v-for="item in recommendations"
          :key="item.id"
          :product="item"
          source="product_recommendations"
          class="slider-card"
        />
      </div>
    </section>
  </main>
</template>

<style scoped>
.product-page {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 24px 0 56px;
}

.breadcrumbs {
  grid-column: 1 / -1;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--color-text-soft);
}

.breadcrumbs a {
  color: inherit;
}

.product-layout {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 22px;
}

.gallery {
  border-radius: 22px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
}

.gallery__main {
  width: 100%;
  height: min(62vw, 560px);
  object-fit: cover;
  display: block;
}

.gallery__thumbs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
  gap: 8px;
  padding: 10px;
}

.thumb {
  border: 1px solid #dfdcd5;
  border-radius: 10px;
  background: #fff;
  padding: 4px;
  cursor: pointer;
}

.thumb--active {
  border-color: #f35b04;
}

.thumb img {
  width: 100%;
  height: 58px;
  object-fit: cover;
  display: block;
  border-radius: 6px;
}

.details {
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  padding: 24px 20px;
}

.details__category {
  color: #7f8ca8;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.details h1 {
  margin-top: 8px;
  font-size: clamp(34px, 6vw, 58px);
  line-height: 0.95;
  font-family: var(--font-display);
}

.details__sku {
  margin-top: 6px;
  color: #5c6477;
  font-size: 14px;
}

.price-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
}

.price-row strong {
  font-size: 30px;
}

.price-row s {
  color: #8a95ab;
  font-size: 18px;
}

.details__stock {
  margin-top: 10px;
  color: #1f2233;
}

.details__description {
  margin-top: 14px;
  color: #3d4760;
}

.sizes {
  margin-top: 14px;
}

.sizes__title {
  margin-bottom: 8px;
  color: #4f5a74;
  font-weight: 600;
}

.sizes__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(86px, 1fr));
  gap: 8px;
}

.size-chip {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  background: #fff;
  padding: 8px 10px;
  font-weight: 600;
  cursor: pointer;
}

.size-chip--active {
  border-color: #f35b04;
  color: #c74803;
  background: #fff5ed;
}

.size-chip:disabled {
  opacity: 0.45;
  cursor: default;
}

.buy-button {
  flex: 1 1 auto;
  min-height: 56px;
  padding: 12px 14px;
  border: none;
  border-radius: 12px;
  background: #f35b04;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.buy-button:disabled {
  opacity: 0.5;
  cursor: default;
}

.cta-row {
  display: flex;
  align-items: end;
  gap: 10px;
  margin-top: 18px;
}

.buy-stepper {
  flex: 1 1 auto;
  position: relative;
  min-height: 56px;
}

.buy-stepper__label {
  position: absolute;
  top: -18px;
  left: 0;
  color: #5b6b89;
  font-size: 12px;
  font-weight: 600;
}

.buy-stepper__controls {
  width: 100%;
  display: grid;
  grid-template-columns: 44px 1fr 44px;
  align-items: center;
  gap: 8px;
  border: 1px solid #d7d4ce;
  border-radius: 12px;
  background: #fff;
  min-height: 56px;
  padding: 6px;
}

.buy-stepper__controls strong {
  text-align: center;
  font-size: 30px;
}

.buy-stepper__controls button {
  height: 42px;
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  background: #f8f7f4;
  font: inherit;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
}

.buy-stepper__controls button:disabled {
  opacity: 0.6;
  cursor: default;
}

.icon-button {
  flex: 0 0 auto;
  width: 56px;
  height: 56px;
  display: grid;
  place-items: center;
  border: 1px solid #d7d4ce;
  border-radius: 12px;
  background: #fff;
  color: #1f2233;
  cursor: pointer;
}

.icon-button--wishlist.icon-button--active {
  border-color: #f35b04;
  background: #fff2e8;
  color: #c74803;
}

.icon-button--compare {
  color: #3a4d73;
}

.icon-button--compare.icon-button--active {
  border-color: #4465a8;
  background: #eef2fb;
  color: #2f4b8b;
}

.icon-button svg {
  width: 22px;
  height: 22px;
  fill: currentColor;
}

.status {
  color: #4d5b75;
}

.status--warn {
  color: #b95b09;
}

.recommendations {
  margin-top: 24px;
}

.recommendations__header h2 {
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 42px);
  line-height: 0.95;
}

.recommendations__header p {
  margin-top: 6px;
  color: #5f6b86;
}

.recommendations__slider {
  margin-top: 14px;
  display: flex;
  gap: 14px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  padding-bottom: 4px;
}

.recommendations__slider::-webkit-scrollbar {
  display: none;
}

.recommendations__slider > * {
  scroll-snap-align: start;
}

.slider-card {
  flex: 0 0 clamp(230px, 24vw, 300px);
}

.section__head-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin: -4px 0 10px;
}

.slider-nav {
  width: 36px;
  height: 36px;
  border: 1px solid #d8d0c4;
  border-radius: 50%;
  background: #fff;
  cursor: pointer;
}

@media (max-width: 880px) {
  .product-layout {
    grid-template-columns: 1fr;
  }

  .cta-row {
    flex-wrap: wrap;
  }

  .buy-button,
  .buy-stepper {
    flex-basis: 100%;
  }

  .icon-button {
    flex: 1 1 calc(50% - 5px);
    width: auto;
  }
}
</style>
