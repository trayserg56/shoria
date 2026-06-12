<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useOneClickCheckoutModalStore } from '@/stores/one-click-checkout-modal'
import { useWishlistStore, type WishlistItem } from '@/stores/wishlist'
import { useCompareStore, type CompareItem } from '@/stores/compare'
import { trackEvent } from '@/lib/analytics'
import { fetchJson, requestJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { buildCatalogPath } from '@/lib/catalog-path'
import { toProductRoute } from '@/lib/product-route'
import { clearStructuredData, setSeoMeta, setStructuredData } from '@/lib/seo'
import { saveRecentlyViewed } from '@/lib/recently-viewed'
import { toast } from '@/lib/toast'
import { characteristicsCatalogRoute } from '@/lib/catalog-characteristics'
import { siteSettingsInjectionKey, defaultSiteFeatureFlags, type SiteSettingsPayload } from '@/lib/site-settings'
import { useSiteCityStore } from '@/stores/site-city'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import ProductImageLightbox from '@/components/ProductImageLightbox.vue'
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
    values: string[]
  }>
  seo_title: string | null
  seo_description: string | null
  price: number
  old_price: number | null
  currency: string
  stock: number
  prices_by_city: Array<{ city: string; price: number }>
  stock_cities: string[]
  has_variants: boolean
  category: {
    name: string
    slug: string
  } | null
  /** Корень → лист: для хлебных крошек (родительские категории) */
  category_path?: Array<{
    name: string
    slug: string
  }>
  tags: Array<{
    code: string
    label: string
  }>
  reviews_summary: {
    count: number
    average: number | null
  }
  can_review: boolean
  my_review: ProductReviewPayload | null
  selected_variant_slug: string | null
  variants: ProductVariantPayload[]
  images: ProductImage[]
}

type ProductReviewPayload = {
  id: number
  rating: number
  review_text: string
  author_name: string
  target?: {
    product_name: string | null
    variant_label: string | null
    variant_slug: string | null
  }
  is_verified_purchase: boolean
  created_at: string
  updated_at: string
}

type ProductReviewsPayload = {
  data: ProductReviewPayload[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    scope?: 'all' | 'variant'
  }
  summary: {
    count: number
    average: number | null
  }
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
  reviews_summary?: {
    count: number
    average: number | null
  }
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
const authStore = useAuthStore()
const cartStore = useCartStore()
const { items: cartItems } = storeToRefs(cartStore)
const { isAuthenticated } = storeToRefs(authStore)
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const cityStore = useSiteCityStore()
const siteSettingsRef = inject(siteSettingsInjectionKey) as Ref<SiteSettingsPayload> | undefined
const wishlistFeatureEnabled = computed(
  () => siteSettingsRef?.value.feature_flags.wishlist ?? defaultSiteFeatureFlags.wishlist,
)
const compareFeatureEnabled = computed(
  () => siteSettingsRef?.value.feature_flags.product_compare ?? defaultSiteFeatureFlags.product_compare,
)
const oneClickModalStore = useOneClickCheckoutModalStore()

const product = ref<ProductPayload | null>(null)
const isLoading = ref(true)
const hasError = ref(false)
const addError = ref('')
const activeImageIndex = ref(0)
const selectedVariantId = ref<number | null>(null)
const selectedColorLabel = ref<string | null>(null)
const recommendations = ref<RecommendedProduct[]>([])
const recommendationsSlider = ref<HTMLElement | null>(null)
const isRecommendationsLoading = ref(false)
const isCartBusy = ref(false)
const reviews = ref<ProductReviewPayload[]>([])
const reviewsMeta = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})
const isReviewsLoading = ref(false)
const isReviewSubmitting = ref(false)
const reviewRating = ref(0)
const reviewText = ref('')
const reviewError = ref('')
const reviewSuccess = ref('')
const reviewsScope = ref<'all' | 'variant'>('all')
const imageLightboxOpen = ref(false)

const slug = computed(() => String(route.params.slug ?? ''))
const currentCategorySlug = computed(() => String(route.params.categorySlug ?? ''))
const currentVariantSlug = computed(() => String(route.params.variantSlug ?? '').trim())
const activeImage = computed(() => product.value?.images[activeImageIndex.value] ?? null)
const imageLightboxSlides = computed(() => {
  if (!product.value?.images?.length) {
    return []
  }

  const name = product.value.name

  return product.value.images.map((img) => ({
    url: img.url,
    alt: img.alt ?? name,
  }))
})
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
const productCategoryBreadcrumbItems = computed((): Array<{ name: string; path: string }> => {
  const payload = product.value
  if (!payload) {
    return []
  }

  const nodes = payload.category_path
  if (nodes?.length) {
    return nodes.map((node, index) => ({
      name: node.name,
      path: buildCatalogPath(nodes.slice(0, index + 1).map((n) => n.slug)),
    }))
  }

  if (payload.category) {
    return [{ name: payload.category.name, path: buildCatalogPath([payload.category.slug]) }]
  }

  return []
})

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

function formatPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function formatReviewDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
  }).format(new Date(value))
}

function renderStars(value: number) {
  return '★'.repeat(value) + '☆'.repeat(Math.max(0, 5 - value))
}

function reviewInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) {
    const a = parts[0]?.[0]
    const b = parts[1]?.[0]
    if (a && b) {
      return (a + b).toUpperCase()
    }
  }

  const compact = name.trim().replace(/\s+/g, '')
  return compact.slice(0, 2).toUpperCase() || '?'
}

function formatDeliveryPeriod(min: number | null, max: number | null) {
  if (min == null && max == null) {
    return ''
  }

  return min === max ? `${min} дн.` : `${min}–${max} дн.`
}

function formatReviewCount(value: number) {
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

async function loadReviews(page = 1) {
  if (!slug.value) {
    return
  }

  isReviewsLoading.value = true

  try {
    const params = new URLSearchParams({
      page: String(page),
    })

    if (reviewsScope.value === 'variant' && selectedVariant.value?.slug) {
      params.set('variant_slug', selectedVariant.value.slug)
    }

    const response = await fetchJson<ProductReviewsPayload>(
      `/api/products/${slug.value}/reviews?${params.toString()}`,
    )

    reviews.value = response.data
    reviewsMeta.value = {
      current_page: response.meta.current_page,
      last_page: response.meta.last_page,
      total: response.meta.total,
    }
  } catch (error) {
    console.error(error)
    reviews.value = []
    reviewsMeta.value = {
      current_page: 1,
      last_page: 1,
      total: 0,
    }
  } finally {
    isReviewsLoading.value = false
  }
}

function setReviewsScope(scope: 'all' | 'variant') {
  if (scope === reviewsScope.value) {
    return
  }

  reviewsScope.value = scope
  void loadReviews(1)
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

type DeliveryEstimateOption = {
  code: string
  name: string
  fee: number
  period_min: number | null
  period_max: number | null
}

const deliveryEstimates = ref<DeliveryEstimateOption[]>([])
const deliveryEstimateLoading = ref(false)
const deliveryEstimateError = ref('')

async function loadDeliveryEstimate() {
  const city = cityStore.name.trim()
  if (!city) {
    deliveryEstimates.value = []
    return
  }

  deliveryEstimateLoading.value = true
  deliveryEstimateError.value = ''

  try {
    const response = await requestJson<{ data: DeliveryEstimateOption[] }>(
      `/api/delivery/estimate?city=${encodeURIComponent(city)}`,
    )
    deliveryEstimates.value = response.data
  } catch (error) {
    console.error(error)
    deliveryEstimateError.value = 'Не удалось рассчитать стоимость доставки.'
  } finally {
    deliveryEstimateLoading.value = false
  }
}

async function loadProduct() {
  if (!slug.value) {
    return
  }

  isLoading.value = true
  isRecommendationsLoading.value = true

  try {
    const variantQuery = currentVariantSlug.value
      ? `?variant=${encodeURIComponent(currentVariantSlug.value)}`
      : ''

    product.value = await fetchJson<ProductPayload>(`/api/products/${slug.value}${variantQuery}`)
    hasError.value = false
    activeImageIndex.value = 0
    const initialVariant = resolveInitialVariant(product.value)
    selectedVariantId.value = initialVariant?.id ?? null
    selectedColorLabel.value = initialVariant?.color_label?.trim() || null
    reviewError.value = ''
    reviewSuccess.value = ''
    reviewRating.value = product.value.my_review?.rating ?? 0
    reviewText.value = product.value.my_review?.review_text ?? ''
    isLoading.value = false

    const recommendationsPromise = fetchJson<RecommendationsPayload>(`/api/products/${slug.value}/recommendations`)
      .then((recommendationsPayload) => {
        recommendations.value = recommendationsPayload.data
      })
      .catch((error) => {
        console.error(error)
        recommendations.value = []
      })
      .finally(() => {
        isRecommendationsLoading.value = false
      })

    await Promise.all([recommendationsPromise, loadReviews(1)])
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

    const canonicalResolved = router.resolve(
      toProductRoute({
        slug: product.value.slug,
        category: product.value.category,
        variant_slug: selectedVariantSlug,
      }),
    )
    const canonicalPath = canonicalResolved.href
    const productPagePathForBreadcrumbs = canonicalResolved.fullPath

    setSeoMeta({
      title:
        product.value.seo_title?.trim() ||
        `${product.value.name} — ${product.value.category?.name ? `${product.value.category.name} · ` : ''}Shoria`,
      description:
        product.value.seo_description?.trim() ||
        product.value.description?.trim() ||
        `Купить ${product.value.name} в Shoria: актуальная цена, наличие и рекомендации.`,
      robots: 'index,follow',
      canonical: canonicalPath.startsWith('http') ? canonicalPath : `${window.location.origin}${canonicalPath}`,
    })
    setStructuredData([
      buildBreadcrumbStructuredData([
        { name: 'Главная', path: '/' },
        { name: 'Каталог', path: '/catalog' },
        ...productCategoryBreadcrumbItems.value,
        {
          name: product.value.name,
          path: productPagePathForBreadcrumbs,
        },
      ]),
      buildProductStructuredData({
        slug: productPagePathForBreadcrumbs.replace(/^\/product\//, ''),
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
    reviews.value = []
    reviewsMeta.value = {
      current_page: 1,
      last_page: 1,
      total: 0,
    }
    clearStructuredData()
  } finally {
    isRecommendationsLoading.value = false
    isLoading.value = false
  }
}

async function oneClickBuy() {
  if (!product.value) {
    return
  }

  if (product.value.has_variants && !selectedVariantId.value) {
    addError.value = 'Выберите размер перед быстрым заказом.'
    return
  }

  if (effectiveStock.value <= 0) {
    return
  }

  addError.value = ''

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
    productVariantId: selectedVariantId.value ?? undefined,
    qty: 1,
    productPrice: effectivePrice.value,
    currency: product.value.currency,
    source: 'product_page',
  })
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

  void trackEvent('toggle_compare', {
    slug: item.slug,
    action: result.active ? 'added' : 'removed',
    source: 'product',
  })
}

function setReviewRating(value: number) {
  reviewRating.value = value
}

async function submitReview() {
  if (!product.value || !isAuthenticated.value) {
    return
  }

  reviewError.value = ''
  reviewSuccess.value = ''

  if (reviewRating.value < 1 || reviewRating.value > 5) {
    reviewError.value = 'Поставьте оценку от 1 до 5.'
    return
  }

  if (!reviewText.value.trim()) {
    reviewError.value = 'Добавьте текст отзыва.'
    return
  }

  isReviewSubmitting.value = true

  try {
    const response = await requestJson<{
      review: ProductReviewPayload
      summary: {
        count: number
        average: number | null
      }
      is_new: boolean
    }>(`/api/products/${product.value.slug}/reviews`, {
      method: 'POST',
      body: JSON.stringify({
        rating: reviewRating.value,
        review_text: reviewText.value.trim(),
        variant_slug: selectedVariant.value?.slug ?? null,
      }),
    })

    product.value.my_review = response.review
    product.value.can_review = true
    product.value.reviews_summary = response.summary
    reviewSuccess.value = response.is_new ? 'Отзыв опубликован.' : 'Отзыв обновлён.'
    await loadReviews(reviewsMeta.value.current_page)
  } catch (error) {
    console.error(error)
    reviewError.value = 'Не удалось сохранить отзыв. Проверьте, покупали ли вы этот товар.'
  } finally {
    isReviewSubmitting.value = false
  }
}

onMounted(loadProduct)
watch(() => cityStore.name, loadDeliveryEstimate, { immediate: true })

watch(
  () => route.fullPath,
  async () => {
    await loadProduct()
  },
)

watch(
  () => selectedVariant.value?.slug ?? null,
  () => {
    if (reviewsScope.value === 'variant') {
      void loadReviews(1)
    }
  },
)
</script>

<template>
  <main class="product-page">
    <nav v-if="isLoading && !product" class="breadcrumbs" aria-label="Breadcrumbs" aria-hidden="true">
      <AppSkeleton width="58px" height="18px" />
      <span>/</span>
      <AppSkeleton width="64px" height="18px" />
      <span>/</span>
      <AppSkeleton width="112px" height="18px" />
      <span>/</span>
      <AppSkeleton width="180px" height="18px" />
    </nav>

    <section v-if="isLoading && !product" class="product-layout product-layout--skeleton" aria-hidden="true">
      <div class="gallery gallery--skeleton">
        <div class="gallery__thumbs gallery__thumbs--skeleton">
          <AppSkeleton
            v-for="index in 4"
            :key="`gallery-thumb-skeleton-${index}`"
            width="100%"
            height="68px"
            radius="10px"
          />
        </div>
        <AppSkeleton class="gallery__main-skeleton" width="100%" height="min(62vw, 560px)" radius="16px" style="aspect-ratio: 1 / 1" />
      </div>
      <article class="details details--skeleton">
        <AppSkeleton width="24%" height="12px" />
        <AppSkeleton width="72%" height="54px" />
        <AppSkeleton width="28%" height="14px" />
        <div class="details__tags details__tags--skeleton">
          <AppSkeleton width="70px" height="28px" radius="999px" />
          <AppSkeleton width="86px" height="28px" radius="999px" />
        </div>
        <AppSkeleton width="38%" height="16px" />
        <div class="sizes__grid sizes__grid--skeleton">
          <AppSkeleton
            v-for="index in 4"
            :key="`size-skeleton-${index}`"
            width="86px"
            height="44px"
            radius="12px"
          />
        </div>
        <AppSkeleton width="100%" height="16px" />
        <AppSkeleton width="88%" height="16px" />
      </article>
      <aside class="buy-box buy-box--skeleton">
        <AppSkeleton width="42%" height="36px" />
        <AppSkeleton width="38%" height="16px" />
        <AppSkeleton width="100%" height="54px" radius="16px" />
        <AppSkeleton width="100%" height="48px" radius="12px" />
      </aside>
    </section>
    <p v-if="hasError" class="status status--warn">Товар не найден или API недоступно.</p>

    <section v-if="product" class="product-layout">
      <nav class="breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <RouterLink to="/catalog">Каталог</RouterLink>
        <template v-for="(crumb, crumbIndex) in productCategoryBreadcrumbItems" :key="`${crumb.path}-${crumbIndex}`">
          <span>/</span>
          <RouterLink :to="crumb.path">{{ crumb.name }}</RouterLink>
        </template>
        <span>/</span>
        <span>{{ product.name }}</span>
      </nav>

      <div class="gallery">
        <div class="gallery__thumbs">
          <button
            v-for="(image, index) in product.images"
            :key="`${image.url}-${index}`"
            type="button"
            class="thumb"
            :class="{ 'thumb--active': index === activeImageIndex }"
            @click="activeImageIndex = index"
          >
            <img :src="resolveImageSrc(image.url)" :alt="image.alt ?? product.name" @error="applyImageFallback" />
          </button>
        </div>
        <button
          v-if="activeImage"
          type="button"
          class="gallery__main-trigger"
          :aria-label="`Открыть фото на весь экран (${activeImageIndex + 1} из ${product.images.length})`"
          @click="imageLightboxOpen = true"
        >
          <img
            class="gallery__main"
            :src="resolveImageSrc(activeImage.url)"
            :alt="activeImage.alt ?? product.name"
            @error="applyImageFallback"
          />
        </button>
      </div>

      <ProductImageLightbox
        v-model="imageLightboxOpen"
        :slides="imageLightboxSlides"
        :anchor-index="activeImageIndex"
        @sync-index="activeImageIndex = $event"
      />

      <article class="details">
        <p class="details__category">{{ product.category?.name ?? 'Sneakers' }}</p>
        <h1>{{ product.name }}</h1>
        <RouterLink
          v-if="product.brand"
          class="details__brand details__brand-link"
          :to="{ path: '/catalog', query: { brands: product.brand } }"
        >
          Бренд: {{ product.brand }}
        </RouterLink>
        <div v-if="product.tags.length" class="details__tags">
          <span v-for="tag in product.tags" :key="`details-tag-${tag.code}`" class="details__tag">
            {{ tag.label }}
          </span>
        </div>
        <p class="details__sku">SKU: {{ product.sku ?? 'N/A' }}</p>
        <p class="details__reviews-summary">
          <template v-if="product.reviews_summary.count > 0">
            ★ {{ product.reviews_summary.average?.toFixed(1) ?? '—' }} ·
            {{ formatReviewCount(product.reviews_summary.count) }}
          </template>
          <template v-else>Пока нет отзывов</template>
        </p>

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
                <span class="details__characteristics-dots" aria-hidden="true" />
                <div class="details__characteristics-values-cell">
                  <template v-for="(val, vIndex) in row.values" :key="`${row.name}-${vIndex}-${val}`">
                    <RouterLink
                      class="details__characteristics-value-link"
                      :to="
                        characteristicsCatalogRoute(row.name, val, {
                          categorySlug: product.category?.slug ?? null,
                        })
                      "
                      :title="`Подобрать товары: ${row.name} — ${val}`"
                    >
                      {{ val }}
                    </RouterLink>
                    <span
                      v-if="vIndex < row.values.length - 1"
                      class="details__characteristics-value-sep"
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

      </article>

      <aside class="buy-box">
        <div class="price-row">
          <strong>{{ formatPrice(effectivePrice, product.currency) }}</strong>
          <s v-if="product.old_price">{{ formatPrice(product.old_price, product.currency) }}</s>
        </div>

        <p class="details__stock" :class="{ 'details__stock--empty': effectiveStock <= 0 }">
          {{ effectiveStock > 0 ? `В наличии: ${effectiveStock} шт.` : 'Нет в наличии' }}
          <span
            v-if="effectiveStock > 0 && product.stock_cities?.length"
            class="details__stock-cities"
          >· {{ product.stock_cities.join(', ') }}</span>
        </p>

        <ul
          v-if="product.prices_by_city?.length"
          class="details__city-prices"
        >
          <li v-for="cp in product.prices_by_city" :key="cp.city">
            <span class="details__city-prices-city">{{ cp.city }}</span>
            <span class="details__city-prices-price">{{ formatPrice(cp.price, product.currency) }}</span>
          </li>
        </ul>

        <div class="cta-stack">
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
              <div class="buy-stepper__controls">
                <button type="button" :disabled="isCartBusy" @click="changeCartQty('dec')">−</button>
                <strong>{{ currentCartQty }}</strong>
                <button type="button" :disabled="isCartBusy" @click="changeCartQty('inc')">+</button>
              </div>
            </div>
            <button
              v-if="wishlistFeatureEnabled"
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
              v-if="compareFeatureEnabled"
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
          <button
            v-if="effectiveStock > 0"
            type="button"
            class="one-click-btn"
            :disabled="isCartBusy"
            @click="oneClickBuy"
          >
            Купить в 1 клик
          </button>
        </div>
        <p v-if="addError" class="status status--warn">{{ addError }}</p>

        <section class="delivery-estimate">
          <h3 class="delivery-estimate__title">Стоимость доставки</h3>
          <p v-if="deliveryEstimateLoading" class="delivery-estimate__hint">Считаем стоимость…</p>
          <p v-else-if="deliveryEstimateError" class="delivery-estimate__hint">{{ deliveryEstimateError }}</p>
          <ul v-else-if="deliveryEstimates.length" class="delivery-estimate__list">
            <li v-for="option in deliveryEstimates" :key="option.code">
              <span>{{ option.name }}</span>
              <span>
                {{ option.fee > 0 ? formatPrice(option.fee, product?.currency ?? 'RUB') : 'Бесплатно' }}
                <template v-if="formatDeliveryPeriod(option.period_min, option.period_max)">
                  · {{ formatDeliveryPeriod(option.period_min, option.period_max) }}
                </template>
              </span>
            </li>
          </ul>
        </section>
      </aside>
    </section>

    <section v-if="isRecommendationsLoading" class="recommendations recommendations--skeleton" aria-hidden="true">
      <header class="recommendations__header">
        <AppSkeleton width="260px" height="40px" />
      </header>
      <div class="recommendations__slider">
        <article v-for="index in 4" :key="`recommendation-skeleton-${index}`" class="slider-card slider-card--skeleton">
          <div class="slider-card__skeleton-media">
            <AppSkeleton width="100%" height="100%" radius="12px" />
            <div class="slider-card__skeleton-rail">
              <AppSkeleton width="36px" height="36px" radius="10px" />
              <AppSkeleton width="36px" height="36px" radius="10px" />
            </div>
          </div>
          <div class="slider-card__skeleton-body">
            <AppSkeleton width="64%" height="24px" />
            <AppSkeleton width="32%" height="13px" />
            <AppSkeleton width="26%" height="13px" />
            <AppSkeleton width="70%" height="22px" />
            <AppSkeleton width="54%" height="14px" />
          </div>
          <div class="slider-card__skeleton-actions">
            <AppSkeleton width="128px" height="34px" radius="10px" />
          </div>
        </article>
      </div>
    </section>

    <section v-else-if="recommendations.length" class="recommendations">
      <header class="recommendations__header">
        <h2>Рекомендуем посмотреть</h2>
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

    <section v-if="product" class="details__reviews product-reviews">
      <div class="details__reviews-head">
        <template v-if="isReviewsLoading">
          <AppSkeleton width="220px" height="26px" />
          <AppSkeleton width="180px" height="16px" />
          <div v-if="product.has_variants" class="details__reviews-tabs details__reviews-tabs--skeleton">
            <AppSkeleton width="96px" height="34px" radius="999px" />
            <AppSkeleton width="104px" height="34px" radius="999px" />
          </div>
        </template>
        <template v-else>
          <div class="reviews-head">
            <div class="reviews-head__titles">
              <h3>Отзывы покупателей</h3>
              <p v-if="product.reviews_summary.count > 0" class="reviews-head__sub">
                {{ formatReviewCount(product.reviews_summary.count) }}
              </p>
              <p v-else class="reviews-head__sub">Пока отзывов нет — будьте первым.</p>
            </div>
            <div
              v-if="product.reviews_summary.count > 0"
              class="reviews-head__badge"
            >
              <span class="reviews-head__badge-score">{{
                product.reviews_summary.average != null ? product.reviews_summary.average.toFixed(1) : '—'
              }}</span>
              <span class="reviews-head__badge-label">из 5</span>
            </div>
          </div>
          <div v-if="product.has_variants" class="details__reviews-tabs">
          <button
            type="button"
            class="details__reviews-tab"
            :class="{ 'details__reviews-tab--active': reviewsScope === 'all' }"
            @click="setReviewsScope('all')"
          >
            Все отзывы
          </button>
          <button
            type="button"
            class="details__reviews-tab"
            :class="{ 'details__reviews-tab--active': reviewsScope === 'variant' }"
            :disabled="!selectedVariant?.slug"
            @click="setReviewsScope('variant')"
          >
            Этот вариант
          </button>
          </div>
        </template>
      </div>

      <form
        v-if="!isReviewsLoading && isAuthenticated && (product.can_review || product.my_review)"
        class="review-form"
        @submit.prevent="submitReview"
      >
        <p class="review-form__title">
          {{ product.my_review ? 'Обновить ваш отзыв' : 'Оставить отзыв' }}
        </p>
        <div class="review-form__stars">
          <button
            v-for="star in 5"
            :key="`star-${star}`"
            type="button"
            class="review-form__star"
            :class="{ 'review-form__star--active': star <= reviewRating }"
            :aria-label="`Поставить ${star} из 5`"
            @click="setReviewRating(star)"
          >
            ★
          </button>
        </div>
        <textarea
          v-model="reviewText"
          class="review-form__textarea"
          rows="4"
          maxlength="2000"
          placeholder="Расскажите, как товар показал себя в использовании"
        />
        <div class="review-form__footer">
          <button type="submit" class="review-form__submit" :disabled="isReviewSubmitting">
            {{ isReviewSubmitting ? 'Сохраняем...' : product.my_review ? 'Обновить отзыв' : 'Опубликовать отзыв' }}
          </button>
          <p v-if="reviewError" class="review-form__status review-form__status--error">{{ reviewError }}</p>
          <p v-if="reviewSuccess" class="review-form__status review-form__status--success">{{ reviewSuccess }}</p>
        </div>
      </form>
      <p v-else-if="!isReviewsLoading && isAuthenticated" class="details__reviews-note">
        Оставить отзыв можно только после покупки этого товара.
      </p>
      <p v-else-if="!isReviewsLoading" class="details__reviews-note">
        Войдите в аккаунт, чтобы оставлять отзывы к купленным товарам.
      </p>

      <div v-if="isReviewsLoading" class="details__reviews-skeleton" aria-hidden="true">
        <AppSkeleton width="68%" height="18px" />
        <AppSkeleton width="100%" height="84px" radius="14px" />
        <AppSkeleton width="100%" height="84px" radius="14px" />
      </div>

      <ul v-else-if="reviews.length" class="details__reviews-list">
        <li v-for="review in reviews" :key="`product-review-${review.id}`" class="details__review review-card">
          <div class="review-card__avatar" aria-hidden="true">{{ reviewInitials(review.author_name) }}</div>
          <div class="review-card__main">
            <div class="review-card__top">
              <div class="review-card__who">
                <strong class="review-card__name">{{ review.author_name }}</strong>
                <p class="details__review-target review-card__target">
                  {{ review.target?.product_name ?? product.name }}
                  <template v-if="review.target?.variant_label"> · {{ review.target.variant_label }}</template>
                </p>
              </div>
              <span class="details__review-stars review-card__stars" :title="`${review.rating} из 5`">{{
                renderStars(review.rating)
              }}</span>
            </div>
            <p class="details__review-text">{{ review.review_text }}</p>
            <p class="details__review-meta review-card__meta">
              <span>{{ formatReviewDate(review.updated_at || review.created_at) }}</span>
              <span v-if="review.is_verified_purchase" class="details__review-verified">Покупка подтверждена</span>
            </p>
          </div>
        </li>
      </ul>
    </section>
  </main>
</template>

<style scoped>
.product-page {
  width: min(var(--layout-max-width), 92vw);
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
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.85fr) minmax(280px, 340px);
  gap: 24px;
  align-items: start;
}

.product-layout--skeleton {
  align-items: start;
}

.gallery {
  display: flex;
  gap: 12px;
  border-radius: 22px;
  background: #fff;
  padding: 12px;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  position: sticky;
  top: calc(var(--header-h, 0px) + 16px);
}

.gallery--skeleton {
  position: static;
}

.gallery__main-skeleton {
  flex: 1 1 auto;
  display: block;
}

.gallery__main-trigger {
  display: block;
  flex: 1 1 auto;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  cursor: zoom-in;
  border-radius: 16px;
  overflow: hidden;
}

.gallery__main-trigger:focus-visible {
  outline: 2px solid var(--color-accent, #bf4b08);
  outline-offset: -2px;
}

.gallery__main {
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  display: block;
  border-radius: 16px;
}

.gallery__thumbs {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 76px;
  max-height: 100%;
  overflow-y: auto;
}

.gallery__thumbs--skeleton {
  flex-direction: column;
}

.thumb {
  flex: 0 0 auto;
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

.buy-box {
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  padding: 24px 20px;
  position: sticky;
  top: calc(var(--header-h, 0px) + 16px);
  display: flex;
  flex-direction: column;
}

.buy-box--skeleton {
  display: grid;
  gap: 14px;
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

.details__brand-link {
  text-decoration: none;
}

.details__brand-link:hover {
  color: var(--color-accent, #bf4b08);
}

.details__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.details__tags--skeleton {
  margin-top: 0;
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
  margin-top: 0;
}

.price-row strong {
  font-size: 34px;
}

.price-row s {
  color: #8a95ab;
  font-size: 18px;
}

.details__reviews-summary {
  margin: 4px 0 0;
  color: #5a6379;
  font-size: 14px;
}

.details__stock {
  margin-top: 10px;
  color: #1f2233;
}

.details__stock-cities {
  color: #5a6379;
  font-size: 13px;
}

.details__city-prices {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.details__city-prices li {
  display: flex;
  gap: 6px;
  align-items: center;
  background: var(--color-surface, #f5f5f7);
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 13px;
}

.details__city-prices-city {
  color: #5a6379;
}

.details__city-prices-price {
  font-weight: 600;
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
  padding: 0;
}

.details__characteristics-name {
  color: #4f5a74;
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
}

.details__characteristics-dots {
  border-bottom: 1px dotted color-mix(in srgb, #4f5a74, transparent 52%);
  margin: 0 6px 5px;
  min-width: 12px;
  height: 0;
  align-self: end;
}

.details__characteristics-values-cell {
  display: inline-flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0 2px;
  align-items: baseline;
  min-width: 0;
}

.details__characteristics-value-link {
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

.details__characteristics-value-link:hover {
  color: #1d4ed8;
  text-decoration-color: color-mix(in srgb, #1d4ed8, transparent 35%);
}

.details__characteristics-value-sep {
  color: inherit;
  font-weight: 600;
  user-select: none;
}

.details__characteristics-value-link:focus-visible {
  color: #1d4ed8;
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

.sizes__grid--skeleton {
  grid-template-columns: repeat(auto-fit, minmax(86px, max-content));
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

.details__reviews {
  margin-top: 18px;
  padding: clamp(16px, 2.5vw, 22px);
  border-radius: 16px;
  background: var(--card);
  border: 1px solid var(--border);
  display: grid;
  gap: 16px;
}

.product-reviews {
  margin-top: 24px;
}

.reviews-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.reviews-head__titles h3 {
  font-size: clamp(18px, 2.2vw, 22px);
  font-weight: 700;
  color: var(--foreground);
  letter-spacing: -0.02em;
}

.reviews-head__sub {
  margin-top: 4px;
  color: var(--muted-foreground);
  font-size: 14px;
  line-height: 1.35;
}

.reviews-head__badge {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 64px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--muted);
}

.reviews-head__badge-score {
  font-size: 22px;
  font-weight: 700;
  line-height: 1.1;
  color: var(--foreground);
  font-variant-numeric: tabular-nums;
}

.reviews-head__badge-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted-foreground);
  margin-top: 2px;
}

.details__reviews-tabs {
  margin-top: 4px;
  display: inline-flex;
  gap: 8px;
  padding: 4px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--muted);
}

.details__reviews-tab {
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted-foreground);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 12px;
  cursor: pointer;
}

.details__reviews-tab--active {
  background: var(--foreground);
  color: var(--background);
}

.details__reviews-tab:disabled {
  opacity: 0.55;
  cursor: default;
}

.details__reviews-note {
  color: var(--muted-foreground);
  font-size: 14px;
  line-height: 1.45;
}

.review-form {
  display: grid;
  gap: 10px;
  margin-top: 4px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}

.review-form__title {
  font-size: 15px;
  font-weight: 600;
}

.review-form__stars {
  display: flex;
  gap: 6px;
}

.review-form__star {
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--card);
  color: var(--muted-foreground);
  width: 40px;
  height: 36px;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
}

.review-form__star--active {
  border-color: var(--foreground);
  color: var(--foreground);
  background: var(--muted);
}

.review-form__textarea {
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--card);
  padding: 10px 12px;
  color: var(--color-text);
  font: inherit;
  resize: vertical;
  min-height: 96px;
}

.review-form__footer {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.review-form__submit {
  border: 1px solid var(--foreground);
  border-radius: 12px;
  background: var(--foreground);
  color: var(--background);
  min-height: 40px;
  padding: 0 14px;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.review-form__submit:disabled {
  opacity: 0.6;
  cursor: default;
}

.review-form__status {
  font-size: 13px;
}

.review-form__status--error {
  color: #a83a0f;
}

.review-form__status--success {
  color: #185f2d;
}

.details__reviews-skeleton {
  display: grid;
  gap: 8px;
}

.details__reviews-list {
  display: grid;
  gap: 12px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.details__review.review-card {
  border: 1px solid var(--border);
  background: var(--card);
  border-radius: 14px;
  padding: 14px 16px;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
  align-items: start;
}

.review-card__avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--foreground);
  background: var(--muted);
  border: 1px solid var(--border);
}

.review-card__main {
  min-width: 0;
  display: grid;
  gap: 8px;
}

.review-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.review-card__who {
  min-width: 0;
}

.review-card__name {
  font-size: 15px;
  color: var(--foreground);
}

.review-card__target {
  margin-top: 2px;
}

.review-card__stars {
  flex-shrink: 0;
  font-size: 12px;
  line-height: 1.2;
}

.details__review-stars {
  color: var(--foreground);
  letter-spacing: 0.04em;
  opacity: 0.85;
}

.details__review-text {
  font-size: 14px;
  line-height: 1.5;
  color: var(--foreground);
  margin: 0;
}

.details__review-target {
  margin: 0;
  color: var(--muted-foreground);
  font-size: 13px;
}

.review-card__meta {
  margin: 0;
}

.details__review-meta {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  color: var(--muted-foreground);
  font-size: 12px;
}

.details__review-verified {
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, #185f2d 25%, var(--border));
  background: color-mix(in srgb, #ecf8ef 85%, var(--card));
  color: #14532d;
  padding: 3px 10px;
  font-weight: 600;
  font-size: 11px;
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

.cta-stack {
  margin-top: 18px;
  display: grid;
  gap: 10px;
}

.cta-row {
  display: flex;
  align-items: end;
  gap: 10px;
}

.one-click-btn {
  width: 100%;
  border-radius: 12px;
  padding: 12px 14px;
  border: 1px solid var(--card-fg, #1f2233);
  background: transparent;
  color: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  font-family: inherit;
}

.one-click-btn:hover:not(:disabled) {
  border-color: rgb(31 34 51 / 72%);
  background: rgb(247 246 242);
}

.one-click-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.buy-stepper {
  flex: 1 1 auto;
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

.delivery-estimate {
  margin-top: 18px;
  padding: 16px 18px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--background);
}

.delivery-estimate__title {
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 10px;
}

.delivery-estimate__hint {
  margin: 10px 0 0;
  font-size: 13px;
  color: #64748b;
}

.delivery-estimate__list {
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
}

.delivery-estimate__list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
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
  scroll-padding-inline: 12px;
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
  flex: 0 0 clamp(220px, 20vw, 272px);
}

.slider-card--skeleton {
  display: flex;
  flex: 0 0 clamp(220px, 20vw, 272px);
  flex-direction: column;
  padding: 10px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 8%);
  overflow: hidden;
}

.slider-card__skeleton-media {
  position: relative;
  aspect-ratio: 4 / 3;
  border: 1px solid color-mix(in srgb, var(--border), transparent 35%);
  border-radius: 12px;
  overflow: hidden;
}

.slider-card__skeleton-rail {
  position: absolute;
  top: 10px;
  right: 10px;
  display: grid;
  gap: 5px;
}

.slider-card__skeleton-body {
  display: grid;
  flex: 1;
  gap: 6px;
  padding: 10px 2px 6px;
}

.slider-card__skeleton-actions {
  display: flex;
  min-height: 48px;
  align-items: center;
  padding: 0 2px 2px;
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

@media (max-width: 1180px) {
  .product-layout {
    grid-template-columns: 1fr 1fr;
  }

  .buy-box {
    grid-column: 1 / -1;
    position: static;
  }
}

@media (max-width: 880px) {
  .product-layout {
    grid-template-columns: 1fr;
  }

  .gallery {
    position: static;
  }

  .gallery__thumbs {
    width: 64px;
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
