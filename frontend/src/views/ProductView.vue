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
import AppSkeleton from '@/components/AppSkeleton.vue'
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
  characteristics: Array<{
    group: string | null
    name: string
    value: string
  }>
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
  selected_variant_slug: string | null
  variants: ProductVariantPayload[]
  images: ProductImage[]
}

type RecommendedProduct = {
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
const selectedColorLabel = ref<string | null>(null)
const recommendations = ref<RecommendedProduct[]>([])
const recommendationsSlider = ref<HTMLElement | null>(null)
const isCartBusy = ref(false)

const slug = computed(() => String(route.params.slug ?? ''))
const currentCategorySlug = computed(() => String(route.params.categorySlug ?? ''))
const currentVariantSlug = computed(() => String(route.params.variantSlug ?? '').trim())
const activeImage = computed(() => product.value?.images[activeImageIndex.value] ?? null)
const selectedVariant = computed(
  () => product.value?.variants.find((variant) => variant.id === selectedVariantId.value) ?? null,
)
const selectedSizeLabel = computed(() => selectedVariant.value?.size_label ?? null)
const availableColors = computed(() => {
  if (!product.value) {
    return []
  }

  return Array.from(
    new Set(
      product.value.variants
        .map((variant) => variant.color_label?.trim() ?? '')
        .filter((label) => label !== ''),
    ),
  )
})
const hasColorOptions = computed(() => availableColors.value.length > 0)
const colorOptions = computed(() =>
  availableColors.value.map((label) => {
    const variants = product.value?.variants.filter(
      (variant) => (variant.color_label?.trim() ?? '') === label,
    ) ?? []

    return {
      label,
      available: variants.some((variant) => variant.stock > 0),
    }
  }),
)
const variantsByColor = computed(() => {
  if (!product.value) {
    return []
  }

  if (!selectedColorLabel.value) {
    return product.value.variants
  }

  return product.value.variants.filter(
    (variant) => (variant.color_label?.trim() ?? '') === selectedColorLabel.value,
  )
})
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
const groupedCharacteristics = computed(() => {
  if (!product.value?.characteristics?.length) {
    return []
  }

  const groups = new Map<string, Array<{ name: string; value: string }>>()

  for (const item of product.value.characteristics) {
    const groupName = item.group?.trim() || 'Характеристики'

    if (!groups.has(groupName)) {
      groups.set(groupName, [])
    }

    groups.get(groupName)?.push({
      name: item.name,
      value: item.value,
    })
  }

  return Array.from(groups.entries()).map(([name, items]) => ({
    name,
    items,
  }))
})

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

function resolveInitialVariant(data: ProductPayload): ProductVariantPayload | null {
  if (!data.variants.length) {
    return null
  }

  if (data.selected_variant_slug) {
    const bySelectedSlug = data.variants.find((variant) => variant.slug === data.selected_variant_slug)

    if (bySelectedSlug) {
      return bySelectedSlug
    }
  }

  if (currentVariantSlug.value) {
    const byRouteSlug = data.variants.find((variant) => variant.slug === currentVariantSlug.value)

    if (byRouteSlug) {
      return byRouteSlug
    }
  }

  return data.variants.find((variant) => variant.stock > 0) ?? data.variants[0] ?? null
}

function variantsForColor(colorLabel: string): ProductVariantPayload[] {
  if (!product.value) {
    return []
  }

  return product.value.variants.filter(
    (variant) => (variant.color_label?.trim() ?? '') === colorLabel,
  )
}

function findBestVariantForColor(colorLabel: string): ProductVariantPayload | null {
  const variants = variantsForColor(colorLabel)

  if (!variants.length) {
    return null
  }

  if (selectedSizeLabel.value) {
    const sameSizeInStock = variants.find(
      (variant) => variant.size_label === selectedSizeLabel.value && variant.stock > 0,
    )

    if (sameSizeInStock) {
      return sameSizeInStock
    }
  }

  const firstInStock = variants.find((variant) => variant.stock > 0)

  if (firstInStock) {
    return firstInStock
  }

  if (selectedSizeLabel.value) {
    const sameSizeAny = variants.find((variant) => variant.size_label === selectedSizeLabel.value)

    if (sameSizeAny) {
      return sameSizeAny
    }
  }

  return variants[0] ?? null
}

async function openVariant(variant: ProductVariantPayload) {
  if (!product.value) {
    return
  }

  if (currentVariantSlug.value === variant.slug) {
    selectedVariantId.value = variant.id
    selectedColorLabel.value = variant.color_label?.trim() || null
    return
  }

  await router.replace(
    toProductRoute({
      slug: product.value.slug,
      category: product.value.category,
      variant_slug: variant.slug,
    }),
  )
}

async function selectColor(colorLabel: string) {
  selectedColorLabel.value = colorLabel

  const candidate = findBestVariantForColor(colorLabel)

  if (!candidate) {
    return
  }

  await openVariant(candidate)
}

async function loadProduct() {
  if (!slug.value) {
    return
  }

  isLoading.value = true

  try {
    const variantQuery = currentVariantSlug.value
      ? `?variant=${encodeURIComponent(currentVariantSlug.value)}`
      : ''

    product.value = await fetchJson<ProductPayload>(`/api/products/${slug.value}${variantQuery}`)
    const recommendationsPayload = await fetchJson<RecommendationsPayload>(
      `/api/products/${slug.value}/recommendations`,
    )
    recommendations.value = recommendationsPayload.data
    hasError.value = false
    activeImageIndex.value = 0
    const initialVariant = resolveInitialVariant(product.value)
    selectedVariantId.value = initialVariant?.id ?? null
    selectedColorLabel.value = initialVariant?.color_label?.trim() || null
    const actualCategorySlug = product.value.category?.slug ?? ''
    const selectedVariantSlug = initialVariant?.slug ?? null

    if (actualCategorySlug && currentCategorySlug.value !== actualCategorySlug) {
      await router.replace(
        toProductRoute({
          slug: product.value.slug,
          category: product.value.category,
          variant_slug: currentVariantSlug.value || selectedVariantSlug,
        }),
      )
      return
    }

    if (
      currentVariantSlug.value &&
      selectedVariantSlug &&
      currentVariantSlug.value !== selectedVariantSlug
    ) {
      await router.replace(
        toProductRoute({
          slug: product.value.slug,
          category: product.value.category,
          variant_slug: selectedVariantSlug,
        }),
      )
      return
    }

    const canonicalPath = router.resolve(
      toProductRoute({
        slug: product.value.slug,
        category: product.value.category,
        variant_slug: selectedVariantSlug,
      }),
    ).href

    setSeoMeta({
      title:
        product.value.seo_title?.trim() ||
        `${product.value.name} — ${product.value.category?.name ? `${product.value.category.name} · ` : ''}Shoria`,
      description:
        product.value.seo_description?.trim() ||
        product.value.description?.trim() ||
        `Купить ${product.value.name} в Shoria: актуальная цена, наличие и рекомендации.`,
      robots: 'index,follow',
      canonical: `${window.location.origin}${canonicalPath}`,
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
          path: canonicalPath,
        },
      ]),
      buildProductStructuredData({
        slug: canonicalPath.replace(/^\/product\//, ''),
        name: product.value.name,
        description: product.value.description,
        sku: selectedVariant.value?.sku ?? product.value.sku,
        price: effectivePrice.value,
        currency: product.value.currency,
        imageUrl: product.value.images[0]?.url ?? null,
        categoryName: product.value.category?.name ?? null,
        availability: effectiveStock.value > 0 ? 'InStock' : 'OutOfStock',
      }),
    ])

    void trackEvent('view_product', {
      slug: product.value.slug,
      price: effectivePrice.value,
      category: product.value.category?.slug ?? null,
      variant_slug: selectedVariantSlug,
    })

    saveRecentlyViewed({
      id: product.value.id,
      slug: product.value.slug,
      name: product.value.name,
      price: effectivePrice.value,
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
    price: effectivePrice.value,
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
    price: effectivePrice.value,
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
    <section v-if="isLoading && !product" class="product-layout product-layout--skeleton" aria-hidden="true">
      <div class="gallery gallery--skeleton">
        <AppSkeleton width="100%" height="560px" radius="22px" />
      </div>
      <article class="details details--skeleton">
        <AppSkeleton width="24%" height="14px" />
        <AppSkeleton width="58%" height="46px" />
        <AppSkeleton width="28%" height="14px" />
        <AppSkeleton width="32%" height="28px" />
        <AppSkeleton width="38%" height="16px" />
        <AppSkeleton width="100%" height="16px" />
        <AppSkeleton width="88%" height="16px" />
        <AppSkeleton width="100%" height="54px" radius="16px" />
      </article>
    </section>
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
        <p v-if="product.brand" class="details__brand">Бренд: {{ product.brand }}</p>
        <div v-if="product.tags.length" class="details__tags">
          <span v-for="tag in product.tags" :key="`details-tag-${tag.code}`" class="details__tag">
            {{ tag.label }}
          </span>
        </div>
        <p class="details__sku">SKU: {{ product.sku ?? 'N/A' }}</p>

        <div class="price-row">
          <strong>{{ formatPrice(effectivePrice, product.currency) }}</strong>
          <s v-if="product.old_price">{{ formatPrice(product.old_price, product.currency) }}</s>
        </div>

        <div v-if="product.has_variants" class="sizes">
          <div v-if="hasColorOptions" class="sizes__group">
            <p class="sizes__title">Цвет</p>
            <div class="sizes__grid sizes__grid--colors">
              <button
                v-for="color in colorOptions"
                :key="color.label"
                type="button"
                class="size-chip size-chip--color"
                :class="{
                  'size-chip--active': selectedColorLabel === color.label,
                  'size-chip--unavailable': !color.available,
                }"
                @click="selectColor(color.label)"
              >
                <span class="size-chip__label">{{ color.label }}</span>
                <span v-if="!color.available" class="size-chip__meta">нет в наличии</span>
              </button>
            </div>
          </div>
          <div class="sizes__group">
            <p class="sizes__title">Размер</p>
            <div class="sizes__grid">
              <button
                v-for="variant in variantsByColor"
                :key="variant.id"
                type="button"
                class="size-chip"
                :class="{
                  'size-chip--active': selectedVariantId === variant.id,
                  'size-chip--unavailable': variant.stock <= 0,
                }"
                @click="openVariant(variant)"
              >
                <span class="size-chip__label">{{ variant.size_label }}</span>
                <span v-if="variant.stock <= 0" class="size-chip__meta">нет в наличии</span>
              </button>
            </div>
          </div>
        </div>

        <p class="details__stock" :class="{ 'details__stock--empty': effectiveStock <= 0 }">
          {{ effectiveStock > 0 ? `В наличии: ${effectiveStock} шт.` : 'Нет в наличии' }}
        </p>
        <p class="details__description">{{ product.description ?? 'Описание скоро обновим.' }}</p>
        <section v-if="groupedCharacteristics.length" class="details__characteristics">
          <article
            v-for="group in groupedCharacteristics"
            :key="`char-group-${group.name}`"
            class="details__characteristics-group"
          >
            <h3>{{ group.name }}</h3>
            <ul>
              <li v-for="(row, index) in group.items" :key="`char-row-${group.name}-${index}`">
                <span class="details__characteristics-name">{{ row.name }}</span>
                <span class="details__characteristics-dots" />
                <span class="details__characteristics-value">{{ row.value }}</span>
              </li>
            </ul>
          </article>
        </section>

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

.product-layout--skeleton {
  align-items: start;
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

.details--skeleton {
  display: grid;
  gap: 14px;
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

.details__brand {
  margin-top: 6px;
  color: #3d4760;
  font-size: 14px;
}

.details__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.details__tag {
  padding: 6px 10px;
  border-radius: 999px;
  background: #f7ecdf;
  color: #c74803;
  font-size: 12px;
  font-weight: 700;
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

.details__stock--empty {
  color: #b84a14;
  font-weight: 600;
}

.details__description {
  margin-top: 14px;
  color: #3d4760;
}

.details__characteristics {
  margin-top: 16px;
  display: grid;
  gap: 14px;
}

.details__characteristics-group h3 {
  margin: 0 0 7px;
  font-size: 22px;
}

.details__characteristics-group ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 7px;
}

.details__characteristics-group li {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: end;
  gap: 8px;
}

.details__characteristics-name {
  color: #5b6479;
}

.details__characteristics-dots {
  border-bottom: 1px solid #d8d4cd;
  transform: translateY(-4px);
}

.details__characteristics-value {
  color: #1f2233;
  font-weight: 600;
  text-align: right;
}

.sizes {
  margin-top: 14px;
  display: grid;
  gap: 12px;
}

.sizes__group {
  display: grid;
  gap: 8px;
}

.sizes__title {
  color: #4f5a74;
  font-weight: 600;
  font-size: 14px;
}

.sizes__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(86px, 1fr));
  gap: 8px;
}

.sizes__grid--colors {
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.size-chip {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  background: #fff;
  padding: 8px 10px 9px;
  font-weight: 600;
  cursor: pointer;
  display: grid;
  justify-items: center;
  gap: 3px;
  text-align: center;
}

.size-chip--active {
  border-color: #f35b04;
  color: #c74803;
  background: #fff5ed;
}

.size-chip--color {
  font-weight: 500;
}

.size-chip__label {
  line-height: 1.2;
}

.size-chip__meta {
  font-size: 11px;
  line-height: 1.1;
  color: #8b94a7;
}

.size-chip--unavailable {
  opacity: 0.72;
  border-style: dashed;
}

.size-chip--unavailable .size-chip__meta {
  color: #b84a14;
  font-weight: 600;
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
