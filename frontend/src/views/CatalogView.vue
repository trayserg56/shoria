<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { buildCatalogPath } from '@/lib/catalog-path'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { setSeoMeta } from '@/lib/seo'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'

type Category = {
  id: number
  name: string
  slug: string
  description?: string | null
  image_url?: string | null
  icon_url?: string | null
  seo_title?: string | null
  seo_description?: string | null
  subcategories?: Category[]
}

type Product = {
  id: number
  name: string
  brand?: string | null
  slug: string
  price: number
  old_price: number | null
  stock: number
  currency: string
  image_url: string | null
  tags: {
    code: string
    label: string
  }[]
  reviews_summary?: {
    count: number
    average: number | null
  }
  category: {
    name: string
    slug: string
  } | null
}

type FacetOption = {
  value: string
  count: number
}

type TagFacetOption = {
  code: string
  label: string
  count: number
}

type CategoryFacetOption = {
  slug: string
  count: number
}

type CatalogFacets = {
  categories: CategoryFacetOption[]
  tags: TagFacetOption[]
  brands: FacetOption[]
  colors: FacetOption[]
  sizes: FacetOption[]
  characteristics: {
    name: string
    values: FacetOption[]
  }[]
  on_sale: {
    count: number
  }
}

type PaginatedProducts = {
  current_page: number
  last_page: number
  data: Product[]
  filters: CatalogFacets
}

const route = useRoute()
const router = useRouter()

const categories = ref<Category[]>([])
const products = ref<PaginatedProducts>({
  current_page: 1,
  last_page: 1,
  data: [],
  filters: {
    categories: [],
    tags: [],
    brands: [],
    colors: [],
    sizes: [],
    characteristics: [],
    on_sale: {
      count: 0,
    },
  },
})
const isLoading = ref(true)
const hasError = ref(false)
const hasLoadedProducts = ref(false)
const priceMinInput = ref((route.query.price_min as string | undefined) ?? '')
const priceMaxInput = ref((route.query.price_max as string | undefined) ?? '')
let priceApplyTimeout: ReturnType<typeof setTimeout> | null = null

function normalizeRouteSegment(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item).trim())
      .filter(Boolean)
  }

  if (typeof value === 'string') {
    return value
      .split('/')
      .map((item) => item.trim())
      .filter(Boolean)
  }

  return []
}

const activeCategorySegments = computed(() => {
  const categorySlug = typeof route.params.categorySlug === 'string' ? route.params.categorySlug.trim() : ''
  const subcategorySlug =
    typeof route.params.subcategorySlug === 'string' ? route.params.subcategorySlug.trim() : ''
  const deepPathSegments = normalizeRouteSegment(route.params.deepPath)

  const segments = [categorySlug, subcategorySlug, ...deepPathSegments].filter(Boolean)

  if (segments.length > 0) {
    return segments
  }

  const queryCategory = typeof route.query.category === 'string' ? route.query.category.trim() : ''
  return queryCategory ? [queryCategory] : []
})

const activeCategory = computed(() => {
  if (!activeCategorySegments.value.length) {
    return ''
  }

  return activeCategorySegments.value[activeCategorySegments.value.length - 1] ?? ''
})
const activeQuery = computed(() => (route.query.q as string | undefined) ?? '')
const activeSort = computed(() => (route.query.sort as string | undefined) ?? '')
const activePriceMin = computed(() => (route.query.price_min as string | undefined) ?? '')
const activePriceMax = computed(() => (route.query.price_max as string | undefined) ?? '')
const activeInStock = computed(() => (route.query.in_stock as string | undefined) === '1')
const activeOnSale = computed(() => (route.query.on_sale as string | undefined) === '1')
const activeTags = computed(() => {
  const raw = (route.query.tags as string | undefined) ?? ''

  if (!raw) {
    return []
  }

  return raw
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean)
})
const activeBrands = computed(() => {
  const raw = (route.query.brands as string | undefined) ?? ''

  if (!raw) {
    return []
  }

  return raw
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
})
const activeColors = computed(() => {
  const raw = (route.query.colors as string | undefined) ?? ''

  if (!raw) {
    return []
  }

  return raw
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
})
const activeSizes = computed(() => {
  const raw = (route.query.sizes as string | undefined) ?? ''

  if (!raw) {
    return []
  }

  return raw
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
})
const activeCharacteristics = computed(() => {
  const raw = (route.query.characteristics as string | undefined) ?? ''

  if (!raw) {
    return []
  }

  return raw
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
})
const page = computed(() => Number(route.query.page ?? '1'))
const hasCatalogQueryContext = computed(
  () =>
    Boolean(activeQuery.value) ||
    Boolean(activeSort.value) ||
    Boolean(activePriceMin.value) ||
    Boolean(activePriceMax.value) ||
    activeInStock.value ||
    activeOnSale.value ||
    activeTags.value.length > 0 ||
    activeBrands.value.length > 0 ||
    activeColors.value.length > 0 ||
    activeSizes.value.length > 0 ||
    activeCharacteristics.value.length > 0 ||
    page.value > 1,
)
const hasActiveFilters = computed(
  () =>
    Boolean(activeCategory.value) ||
    hasCatalogQueryContext.value,
)
const isCategoryLanding = computed(() => !activeCategory.value && !hasCatalogQueryContext.value)
const activeCategoryPathNodes = computed(() => findCategoryPathNodesBySegments(activeCategorySegments.value))
const activeCategoryNode = computed(() => {
  const nodes = activeCategoryPathNodes.value
  return nodes.length ? nodes[nodes.length - 1] : null
})
const activeCategoryPathNames = computed(() => activeCategoryPathNodes.value.map((node) => node.name))
const childSubcategories = computed(() => {
  if (!activeCategoryNode.value?.subcategories?.length) {
    return []
  }

  return activeCategoryNode.value.subcategories
})
const isInitialCatalogLoad = computed(() => isLoading.value && !hasLoadedProducts.value)
const shouldShowSubcategorySkeleton = computed(
  () =>
    isLoading.value &&
    !isCategoryLanding.value &&
    (childSubcategories.value.length > 0 || isInitialCatalogLoad.value),
)
const subcategorySkeletonCount = computed(() => {
  if (childSubcategories.value.length > 0) {
    return Math.min(childSubcategories.value.length, 4)
  }

  return 4
})
const breadcrumbItems = computed(() => {
  if (!activeCategoryPathNodes.value.length) {
    return []
  }

  const segments: string[] = []

  return activeCategoryPathNodes.value.map((node, index) => {
    segments.push(node.slug)

    return {
      label: node.name,
      path: buildCatalogPath(segments),
      isLast: index === activeCategoryPathNodes.value.length - 1,
    }
  })
})
const activeCategoryPath = computed(() => {
  if (activeCategorySegments.value.length > 0) {
    return buildCatalogPath(activeCategorySegments.value)
  }

  return '/catalog'
})

/** Заголовок страницы: текущая категория/подкатегория или «Каталог» */
const catalogPageTitle = computed(() => activeCategoryNode.value?.name ?? 'Каталог')

const sortOptions = [
  { value: '', label: 'По умолчанию' },
  { value: 'price_asc', label: 'Сначала дешевле' },
  { value: 'price_desc', label: 'Сначала дороже' },
  { value: 'name_asc', label: 'По названию А-Я' },
  { value: 'name_desc', label: 'По названию Я-А' },
]

const tagOptions = [
  { code: 'hit', label: 'Хит' },
  { code: 'new', label: 'Новинка' },
  { code: 'customer_choice', label: 'Выбор покупателей' },
]

const fallbackTagFacetOptions = tagOptions.map((item) => ({
  ...item,
  count: 0,
}))

const availableTagOptions = computed(() => {
  if (products.value.filters.tags.length) {
    return products.value.filters.tags
  }

  return fallbackTagFacetOptions
})

function withActiveFacetValues(options: FacetOption[], activeValues: string[]): FacetOption[] {
  const map = new Map(options.map((item) => [item.value, item] as const))

  for (const value of activeValues) {
    if (!map.has(value)) {
      map.set(value, { value, count: 0 })
    }
  }

  return [...map.values()]
}

const availableBrandOptions = computed(() =>
  withActiveFacetValues(products.value.filters.brands, activeBrands.value),
)
const availableColorOptions = computed(() =>
  withActiveFacetValues(products.value.filters.colors, activeColors.value),
)
const availableSizeOptions = computed(() =>
  withActiveFacetValues(products.value.filters.sizes, activeSizes.value),
)
const availableCharacteristicGroups = computed(() => {
  const groupsMap = new Map<string, FacetOption[]>()

  for (const group of products.value.filters.characteristics) {
    groupsMap.set(group.name, [...group.values])
  }

  for (const token of activeCharacteristics.value) {
    const pair = parseCharacteristicToken(token)

    if (!pair) {
      continue
    }

    const currentValues = groupsMap.get(pair.name) ?? []
    const withActiveValues = withActiveFacetValues(currentValues, [pair.value])
    groupsMap.set(pair.name, withActiveValues)
  }

  return [...groupsMap.entries()]
    .map(([name, values]) => ({
      name,
      values: [...values].sort((a, b) => a.value.localeCompare(b.value, 'ru')),
    }))
    .sort((a, b) => a.name.localeCompare(b.name, 'ru'))
})
const showTagFilterSection = computed(() =>
  availableTagOptions.value.some((tag) => isFacetValueVisible(tag.count, activeTags.value.includes(tag.code))),
)
const showBrandFilterSection = computed(() =>
  availableBrandOptions.value.some((brand) => isFacetValueVisible(brand.count, activeBrands.value.includes(brand.value))),
)
const showColorFilterSection = computed(() =>
  availableColorOptions.value.some((color) => isFacetValueVisible(color.count, activeColors.value.includes(color.value))),
)
const showSizeFilterSection = computed(() =>
  availableSizeOptions.value.some((size) => isFacetValueVisible(size.count, activeSizes.value.includes(size.value))),
)
const showCharacteristicsFilterSection = computed(() =>
  availableCharacteristicGroups.value.some((group) =>
    group.values.some((option) => isFacetValueVisible(option.count, isCharacteristicActive(group.name, option.value))),
  ),
)
const availableCategoryCounts = computed(() => {
  return products.value.filters.categories.reduce<Record<string, number>>((acc, item) => {
    acc[item.slug] = item.count

    return acc
  }, {})
})

function normalizeInputValue(value: unknown): string {
  if (value === null || value === undefined) {
    return ''
  }

  return String(value).trim()
}

async function loadCategories() {
  try {
    categories.value = await fetchJson<Category[]>('/api/categories')
  } catch (error) {
    console.error('Failed to load categories', error)
    categories.value = []
  }
}

async function loadProducts() {
  if (isCategoryLanding.value) {
    isLoading.value = true
    try {
      const landingPreview = await fetchJson<PaginatedProducts>('/api/products')
      products.value = {
        current_page: 1,
        last_page: 1,
        data: [],
        filters: landingPreview.filters,
      }
      hasLoadedProducts.value = true
      hasError.value = false
    } catch (error) {
      console.error(error)
      hasError.value = true
      products.value = {
        current_page: 1,
        last_page: 1,
        data: [],
        filters: {
          categories: [],
          tags: [],
          brands: [],
          colors: [],
          sizes: [],
          characteristics: [],
          on_sale: { count: 0 },
        },
      }
      hasLoadedProducts.value = true
    } finally {
      isLoading.value = false
    }
    return
  }

  isLoading.value = true

  const query = new URLSearchParams()

  if (activeCategory.value) {
    query.set('category', activeCategory.value)
  }

  if (page.value > 1) {
    query.set('page', String(page.value))
  }

  if (activeQuery.value) {
    query.set('q', activeQuery.value)
  }

  if (activeSort.value) {
    query.set('sort', activeSort.value)
  }

  if (activePriceMin.value) {
    query.set('price_min', activePriceMin.value)
  }

  if (activePriceMax.value) {
    query.set('price_max', activePriceMax.value)
  }

  if (activeInStock.value) {
    query.set('in_stock', '1')
  }

  if (activeOnSale.value) {
    query.set('on_sale', '1')
  }

  if (activeTags.value.length) {
    query.set('tags', activeTags.value.join(','))
  }

  if (activeBrands.value.length) {
    query.set('brands', activeBrands.value.join(','))
  }

  if (activeColors.value.length) {
    query.set('colors', activeColors.value.join(','))
  }

  if (activeSizes.value.length) {
    query.set('sizes', activeSizes.value.join(','))
  }

  if (activeCharacteristics.value.length) {
    query.set('characteristics', activeCharacteristics.value.join(','))
  }

  const suffix = query.toString() ? `?${query.toString()}` : ''

  try {
    products.value = await fetchJson<PaginatedProducts>(`/api/products${suffix}`)
    hasLoadedProducts.value = true
    hasError.value = false
    void trackEvent('view_catalog', {
      category: activeCategory.value || 'all',
      page: page.value,
      products_count: products.value.data.length,
      sort: activeSort.value || 'default',
      in_stock: activeInStock.value,
      on_sale: activeOnSale.value,
      tags: activeTags.value.join(',') || 'none',
      brands: activeBrands.value.join(',') || 'none',
      colors: activeColors.value.join(',') || 'none',
      sizes: activeSizes.value.join(',') || 'none',
      characteristics: activeCharacteristics.value.join(',') || 'none',
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

function buildBaseCatalogQuery() {
  return {
    ...(activeQuery.value ? { q: activeQuery.value } : {}),
    ...(activeSort.value ? { sort: activeSort.value } : {}),
    ...(activePriceMin.value ? { price_min: activePriceMin.value } : {}),
    ...(activePriceMax.value ? { price_max: activePriceMax.value } : {}),
    ...(activeInStock.value ? { in_stock: '1' } : {}),
    ...(activeOnSale.value ? { on_sale: '1' } : {}),
    ...(activeTags.value.length ? { tags: activeTags.value.join(',') } : {}),
    ...(activeBrands.value.length ? { brands: activeBrands.value.join(',') } : {}),
    ...(activeColors.value.length ? { colors: activeColors.value.join(',') } : {}),
    ...(activeSizes.value.length ? { sizes: activeSizes.value.join(',') } : {}),
    ...(activeCharacteristics.value.length ? { characteristics: activeCharacteristics.value.join(',') } : {}),
  }
}

function resolveCategoryPathBySlug(slug: string): string[] | null {
  const walk = (nodes: Category[], trail: string[]): string[] | null => {
    for (const node of nodes) {
      const nextTrail = [...trail, node.slug]

      if (node.slug === slug) {
        return nextTrail
      }

      const nested = walk(node.subcategories ?? [], nextTrail)
      if (nested) {
        return nested
      }
    }

    return null
  }

  return walk(categories.value, [])
}

function findCategoryPathNodesBySegments(segments: string[]): Category[] {
  if (!segments.length) {
    return []
  }

  const pathNodes: Category[] = []
  let cursor = categories.value

  for (const segment of segments) {
    const node = cursor.find((item) => item.slug === segment)

    if (!node) {
      return []
    }

    pathNodes.push(node)
    cursor = node.subcategories ?? []
  }

  return pathNodes
}

function navigateCatalog(query: Record<string, string | undefined>, mode: 'replace' | 'push' = 'replace') {
  const navigate = mode === 'push' ? router.push : router.replace
  void navigate.call(router, { path: activeCategoryPath.value, query })
}

function selectCategory(slug = '') {
  if (!slug) {
    navigateCatalog({}, 'push')
    return
  }
  const query = {
    ...buildBaseCatalogQuery(),
    page: undefined,
  }
  const resolved = resolveCategoryPathBySlug(slug)
  const path = resolved ? buildCatalogPath(resolved) : buildCatalogPath([slug])

  void router.push({
    path,
    query,
  })
}

function goToPage(nextPage: number) {
  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextPage > 1 ? { page: String(nextPage) } : {}),
  })
}

function applyFilterControls() {
  const normalizedMin = normalizeInputValue(priceMinInput.value)
  const normalizedMax = normalizeInputValue(priceMaxInput.value)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(normalizedMin ? { price_min: normalizedMin } : { price_min: undefined }),
    ...(normalizedMax ? { price_max: normalizedMax } : { price_max: undefined }),
    page: undefined,
  })
}

function schedulePriceApply() {
  if (priceApplyTimeout) {
    clearTimeout(priceApplyTimeout)
  }

  priceApplyTimeout = setTimeout(() => {
    const normalizedMin = normalizeInputValue(priceMinInput.value)
    const normalizedMax = normalizeInputValue(priceMaxInput.value)

    if (normalizedMin === activePriceMin.value && normalizedMax === activePriceMax.value) {
      return
    }

    applyFilterControls()
  }, 350)
}

function onSortChange(value: string | number | null) {
  const raw = value === null || value === undefined ? '' : String(value)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(raw ? { sort: raw } : { sort: undefined }),
    page: undefined,
  })
}

function toggleInStock() {
  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(activeInStock.value ? { in_stock: undefined } : { in_stock: '1' }),
    page: undefined,
  })
}

function toggleOnSale() {
  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(activeOnSale.value ? { on_sale: undefined } : { on_sale: '1' }),
    page: undefined,
  })
}

function toggleTagFilter(tagCode: string) {
  const nextTags = toggleMultiValue(activeTags.value, tagCode)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextTags.length ? { tags: nextTags.join(',') } : { tags: undefined }),
    page: undefined,
  })
}

function toggleBrandFilter(brand: string) {
  const nextBrands = toggleMultiValue(activeBrands.value, brand)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextBrands.length ? { brands: nextBrands.join(',') } : { brands: undefined }),
    page: undefined,
  })
}

function toggleColorFilter(color: string) {
  const nextColors = toggleMultiValue(activeColors.value, color)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextColors.length ? { colors: nextColors.join(',') } : { colors: undefined }),
    page: undefined,
  })
}

function toggleSizeFilter(size: string) {
  const nextSizes = toggleMultiValue(activeSizes.value, size)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextSizes.length ? { sizes: nextSizes.join(',') } : { sizes: undefined }),
    page: undefined,
  })
}

function toggleCharacteristicFilter(name: string, value: string) {
  const token = buildCharacteristicToken(name, value)
  const nextCharacteristics = toggleMultiValue(activeCharacteristics.value, token)

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(nextCharacteristics.length
      ? { characteristics: nextCharacteristics.join(',') }
      : { characteristics: undefined }),
    page: undefined,
  })
}

function resetCatalogFilters() {
  navigateCatalog({})
}

function toggleMultiValue(currentValues: string[], value: string) {
  const current = new Set(currentValues)

  if (current.has(value)) {
    current.delete(value)
  } else {
    current.add(value)
  }

  return [...current]
}

function buildCharacteristicToken(name: string, value: string) {
  return `${name}::${value}`
}

function parseCharacteristicToken(token: string): { name: string; value: string } | null {
  const separatorIndex = token.indexOf('::')

  if (separatorIndex === -1) {
    return null
  }

  const name = token.slice(0, separatorIndex)
  const value = token.slice(separatorIndex + 2)

  if (!name || !value) {
    return null
  }

  return {
    name: name.trim(),
    value: value.trim(),
  }
}

function isCharacteristicActive(name: string, value: string) {
  const token = buildCharacteristicToken(name, value)
  return activeCharacteristics.value.includes(token)
}

function isFacetValueVisible(count: number, isActive: boolean) {
  return count > 0 || isActive
}

function categoryCountBySlug(slug: string): number {
  return availableCategoryCounts.value[slug] ?? 0
}

function categoryDisplayCount(category: Category): number {
  return categoryCountBySlug(category.slug)
}

function isKnownCategorySlug(slug: string): boolean {
  if (!slug) {
    return true
  }

  return resolveCategoryPathBySlug(slug) !== null
}

function ensureValidCategoryFilter(): boolean {
  if (!activeCategory.value || isKnownCategorySlug(activeCategory.value)) {
    if (activeCategorySegments.value.length) {
      const nodes = findCategoryPathNodesBySegments(activeCategorySegments.value)

      if (nodes.length !== activeCategorySegments.value.length) {
        void router.replace({
          path: '/catalog',
          query: { ...route.query, page: undefined },
        })

        return true
      }
    }

    return false
  }

  void router.replace({
    path: '/catalog',
    query: { ...route.query, page: undefined },
  })

  return true
}

function migrateLegacyCategoryQuery(): boolean {
  const categoryFromQuery = typeof route.query.category === 'string' ? route.query.category.trim() : ''
  const hasCategoryParam = activeCategorySegments.value.length > 0

  if (!categoryFromQuery || hasCategoryParam) {
    if (activeCategorySegments.value.length === 1) {
      const resolved = resolveCategoryPathBySlug(activeCategorySegments.value[0] ?? '')

      if (resolved && resolved.length > 1) {
        void router.replace({
          path: buildCatalogPath(resolved),
          query: route.query,
        })

        return true
      }
    }

    return false
  }

  const resolved = resolveCategoryPathBySlug(categoryFromQuery)
  const path = resolved ? buildCatalogPath(resolved) : buildCatalogPath([categoryFromQuery])

  void router.replace({
    path,
    query: {
      ...route.query,
      category: undefined,
    },
  })

  return true
}

function findActiveCategoryMeta() {
  return activeCategoryNode.value
}

function syncCatalogSeo() {
  const activeCategoryMeta = findActiveCategoryMeta()

  if (!activeCategoryMeta) {
    setSeoMeta({
      title: 'Каталог — Shoria',
      description: 'Категории и товары Shoria: удобный выбор, фильтры, бренды и быстрый переход к карточкам.',
      robots: 'index,follow',
      canonical: `${window.location.origin}/catalog`,
    })
    return
  }

  setSeoMeta({
    title: activeCategoryMeta.seo_title?.trim() || `${activeCategoryMeta.name} — каталог Shoria`,
    description:
      activeCategoryMeta.seo_description?.trim() ||
      `Подборка товаров Shoria в категории ${activeCategoryMeta.name}: фильтры, поиск и быстрый выбор.`,
    robots: 'index,follow',
    canonical: `${window.location.origin}${activeCategoryPath.value}`,
  })
}

onMounted(async () => {
  priceMinInput.value = activePriceMin.value
  priceMaxInput.value = activePriceMax.value
  await loadCategories()
  const migratedLegacyCategory = migrateLegacyCategoryQuery()
  if (migratedLegacyCategory) {
    return
  }
  const fixedInvalidCategory = ensureValidCategoryFilter()
  syncCatalogSeo()
  if (!fixedInvalidCategory) {
    await loadProducts()
  }
})

watch(
  () => route.query.price_min,
  (nextValue) => {
    priceMinInput.value = (nextValue as string | undefined) ?? ''
  },
)

watch(
  () => route.query.price_max,
  (nextValue) => {
    priceMaxInput.value = (nextValue as string | undefined) ?? ''
  },
)

watch(
  [categories, activeCategory],
  () => {
    ensureValidCategoryFilter()
    syncCatalogSeo()
  },
  { deep: true },
)

watch(
  () => route.fullPath,
  async () => {
    await loadProducts()
  },
)

watch(priceMinInput, schedulePriceApply)
watch(priceMaxInput, schedulePriceApply)

onBeforeUnmount(() => {
  if (priceApplyTimeout) {
    clearTimeout(priceApplyTimeout)
  }
})
</script>

<template>
  <main class="catalog">
    <nav v-if="isInitialCatalogLoad && activeCategorySegments.length" class="breadcrumbs" aria-label="Breadcrumbs" aria-hidden="true">
      <AppSkeleton width="58px" height="18px" />
      <span>/</span>
      <AppSkeleton width="64px" height="18px" />
      <span>/</span>
      <AppSkeleton width="124px" height="18px" />
    </nav>
    <nav v-else class="breadcrumbs" aria-label="Breadcrumbs">
      <RouterLink to="/">Главная</RouterLink>
      <span>/</span>
      <RouterLink to="/catalog">Каталог</RouterLink>
      <template v-for="item in breadcrumbItems" :key="item.path">
        <span>/</span>
        <RouterLink v-if="!item.isLast" :to="item.path">{{ item.label }}</RouterLink>
        <span v-else>{{ item.label }}</span>
      </template>
    </nav>

    <header class="catalog-header">
      <h1>{{ catalogPageTitle }}</h1>
    </header>

    <section v-if="isCategoryLanding && isLoading" class="catalog-landing" aria-hidden="true">
      <article v-for="index in 8" :key="`category-landing-skeleton-${index}`" class="category-showcase category-showcase--skeleton">
        <div class="category-showcase__media">
          <AppSkeleton width="100%" height="100%" radius="0" />
          <div class="category-showcase__overlay category-showcase__overlay--skeleton">
            <div class="category-showcase__text">
              <AppSkeleton width="42%" height="28px" />
              <AppSkeleton width="26%" height="18px" />
            </div>
            <AppSkeleton width="34px" height="34px" radius="999px" />
          </div>
        </div>
      </article>
    </section>

    <section v-else-if="isCategoryLanding && !categories.length && !isLoading" class="catalog-empty-landing">
      <p>Не удалось загрузить категории. Проверьте соединение или попробуйте позже.</p>
      <RouterLink to="/catalog">Обновить страницу</RouterLink>
    </section>

    <section v-else-if="isCategoryLanding && categories.length" class="catalog-landing">
      <article
        v-for="category in categories"
        :key="category.id"
        class="category-showcase"
        role="button"
        tabindex="0"
        @click="selectCategory(category.slug)"
        @keydown.enter.prevent="selectCategory(category.slug)"
      >
        <div class="category-showcase__media">
          <img
            :src="resolveImageSrc(category.image_url)"
            :alt="category.name"
            loading="lazy"
            @error="applyImageFallback"
          />
          <div class="category-showcase__overlay">
            <div class="category-showcase__text">
              <h2>{{ category.name }}</h2>
              <p>{{ categoryDisplayCount(category) }} товаров</p>
            </div>
            <span class="category-showcase__arrow">›</span>
          </div>
        </div>
      </article>
    </section>

    <section v-else class="catalog-layout">
      <aside class="catalog-sidebar">
        <ScrollArea class="sidebar-scroll">
          <div class="sidebar-card">
            <div v-if="isInitialCatalogLoad" class="sidebar-skeleton" aria-hidden="true">
              <div v-for="index in 6" :key="`filter-skeleton-${index}`" class="sidebar-skeleton__section">
                <AppSkeleton width="52%" height="18px" />
                <AppSkeleton width="20px" height="20px" radius="999px" />
              </div>
              <AppSkeleton width="100%" height="44px" radius="8px" />
            </div>

          <template v-else>
            <div class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Сортировка</span>
            </div>
            <div class="sidebar-section__body">
              <div class="toolbar__select">
                <select
                  id="catalog-sort"
                  class="toolbar__select-control"
                  aria-label="Сортировка"
                  :value="activeSort"
                  @change="onSortChange(($event.target as HTMLSelectElement).value)"
                >
                  <option
                    v-for="opt in sortOptions"
                    :key="opt.value === '' ? '_default' : opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="toolbar__quick-toggles">
                <label class="filter-check-item" :class="{ 'filter-check-item--active': activeInStock }" @click.prevent="toggleInStock">
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeInStock"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>Только в наличии</span>
                  </div>
                </label>
                <label class="filter-check-item" :class="{ 'filter-check-item--active': activeOnSale }" @click.prevent="toggleOnSale">
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeOnSale"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>Только со скидкой</span>
                  </div>
                  <span class="chip-count">{{ products.filters.on_sale.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Цена и наличие</span>
            </div>
            <div class="sidebar-section__body">
              <div class="toolbar__price">
                <div class="toolbar__price-fields">
                  <div class="toolbar__price-field">
                    <span class="toolbar__price-label">От, ₽</span>
                    <input
                      v-model="priceMinInput"
                      class="toolbar__price-input"
                      type="number"
                      min="0"
                      inputmode="numeric"
                      autocomplete="off"
                      placeholder=""
                    />
                  </div>
                  <div class="toolbar__price-field">
                    <span class="toolbar__price-label">До, ₽</span>
                    <input
                      v-model="priceMaxInput"
                      class="toolbar__price-input"
                      type="number"
                      min="0"
                      inputmode="numeric"
                      autocomplete="off"
                      placeholder=""
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="showTagFilterSection" class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Теги</span>
            </div>
            <div class="sidebar-section__body">
              <div class="tag-filters">
                <label
                  v-for="tag in availableTagOptions"
                  :key="tag.code"
                  class="filter-check-item"
                  :class="{
                    'filter-check-item--active': activeTags.includes(tag.code),
                    'filter-check-item--disabled': !isFacetValueVisible(tag.count, activeTags.includes(tag.code)),
                  }"
                  @click.prevent="
                    isFacetValueVisible(tag.count, activeTags.includes(tag.code)) && toggleTagFilter(tag.code)
                  "
                >
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeTags.includes(tag.code)"
                      :disabled="!isFacetValueVisible(tag.count, activeTags.includes(tag.code))"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>{{ tag.label }}</span>
                  </div>
                  <span class="chip-count">{{ tag.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div v-if="showBrandFilterSection" class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Бренды</span>
            </div>
            <div class="sidebar-section__body">
              <div class="tag-filters">
                <label
                  v-for="brand in availableBrandOptions"
                  v-show="isFacetValueVisible(brand.count, activeBrands.includes(brand.value))"
                  :key="brand.value"
                  class="filter-check-item"
                  :class="{ 'filter-check-item--active': activeBrands.includes(brand.value) }"
                  @click.prevent="toggleBrandFilter(brand.value)"
                >
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeBrands.includes(brand.value)"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>{{ brand.value }}</span>
                  </div>
                  <span class="chip-count">{{ brand.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div v-if="showColorFilterSection" class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Цвета</span>
            </div>
            <div class="sidebar-section__body">
              <div class="tag-filters">
                <label
                  v-for="color in availableColorOptions"
                  v-show="isFacetValueVisible(color.count, activeColors.includes(color.value))"
                  :key="color.value"
                  class="filter-check-item"
                  :class="{ 'filter-check-item--active': activeColors.includes(color.value) }"
                  @click.prevent="toggleColorFilter(color.value)"
                >
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeColors.includes(color.value)"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>{{ color.value }}</span>
                  </div>
                  <span class="chip-count">{{ color.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div v-if="showSizeFilterSection" class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Размеры</span>
            </div>
            <div class="sidebar-section__body">
              <div class="tag-filters">
                <label
                  v-for="size in availableSizeOptions"
                  v-show="isFacetValueVisible(size.count, activeSizes.includes(size.value))"
                  :key="size.value"
                  class="filter-check-item"
                  :class="{ 'filter-check-item--active': activeSizes.includes(size.value) }"
                  @click.prevent="toggleSizeFilter(size.value)"
                >
                  <div class="filter-check-item__left">
                    <input
                      type="checkbox"
                      class="filter-native-checkbox"
                      :checked="activeSizes.includes(size.value)"
                      tabindex="-1"
                      aria-hidden="true"
                    />
                    <span>{{ size.value }}</span>
                  </div>
                  <span class="chip-count">{{ size.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div v-if="showCharacteristicsFilterSection" class="sidebar-section">
            <div class="sidebar-section__title" role="presentation">
              <span>Характеристики</span>
            </div>
            <div class="sidebar-section__body">
              <div class="characteristics-groups">
                <div v-for="group in availableCharacteristicGroups" :key="group.name" class="characteristics-group">
                  <p class="characteristics-group__title">{{ group.name }}</p>
                  <div class="tag-filters">
                    <label
                      v-for="option in group.values"
                      v-show="isFacetValueVisible(option.count, isCharacteristicActive(group.name, option.value))"
                      :key="`${group.name}:${option.value}`"
                      class="filter-check-item"
                      :class="{ 'filter-check-item--active': isCharacteristicActive(group.name, option.value) }"
                      @click.prevent="toggleCharacteristicFilter(group.name, option.value)"
                    >
                      <div class="filter-check-item__left">
                        <input
                          type="checkbox"
                          class="filter-native-checkbox"
                          :checked="isCharacteristicActive(group.name, option.value)"
                          tabindex="-1"
                          aria-hidden="true"
                        />
                        <span>{{ option.value }}</span>
                      </div>
                      <span class="chip-count">{{ option.count }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

            <Button v-if="hasActiveFilters" type="button" variant="ghost" class="sidebar-reset" @click="resetCatalogFilters">
              Сбросить фильтры
            </Button>
          </template>
          </div>
        </ScrollArea>
      </aside>

      <div class="catalog-content">
        <section v-if="shouldShowSubcategorySkeleton" class="subcategory-strip" aria-hidden="true">
          <div class="subcategory-strip__grid">
            <article
              v-for="index in subcategorySkeletonCount"
              :key="`subcategory-skeleton-${index}`"
              class="subcategory-card subcategory-card--skeleton"
            >
              <AppSkeleton width="100%" height="100%" />
              <div class="subcategory-card__skeleton-overlay">
                <AppSkeleton width="58%" height="24px" />
                <AppSkeleton width="34%" height="18px" />
              </div>
            </article>
          </div>
        </section>

        <section v-else-if="childSubcategories.length" class="subcategory-strip">
          <div class="subcategory-strip__grid">
            <article
              v-for="subcategory in childSubcategories"
              :key="subcategory.id"
              class="subcategory-card"
              :class="{ 'subcategory-card--active': activeCategory === subcategory.slug }"
              role="button"
              tabindex="0"
              @click="selectCategory(subcategory.slug)"
              @keydown.enter.prevent="selectCategory(subcategory.slug)"
            >
              <div class="subcategory-card__media">
                <img
                  :src="resolveImageSrc(subcategory.image_url)"
                  :alt="subcategory.name"
                  loading="lazy"
                  @error="applyImageFallback"
                />
                <div class="subcategory-card__overlay">
                  <div class="subcategory-card__text">
                    <h3>{{ subcategory.name }}</h3>
                    <p>{{ categoryDisplayCount(subcategory) }} товаров</p>
                  </div>
                  <span class="subcategory-card__arrow">›</span>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section v-if="isLoading" class="catalog-grid" aria-hidden="true">
          <article v-for="index in 6" :key="`catalog-skeleton-${index}`" class="catalog-skeleton-card">
            <div class="catalog-skeleton-card__media">
              <AppSkeleton width="100%" height="100%" radius="12px" />
              <div class="catalog-skeleton-card__rail">
                <AppSkeleton width="36px" height="36px" radius="10px" />
                <AppSkeleton width="36px" height="36px" radius="10px" />
                <AppSkeleton width="36px" height="36px" radius="10px" />
              </div>
            </div>
            <div class="catalog-skeleton-card__body">
              <AppSkeleton width="64%" height="24px" />
              <AppSkeleton width="34%" height="13px" />
              <AppSkeleton width="30%" height="13px" />
              <AppSkeleton width="76%" height="22px" />
              <AppSkeleton width="58%" height="14px" />
            </div>
            <div class="catalog-skeleton-card__actions">
              <AppSkeleton width="128px" height="34px" radius="10px" />
            </div>
          </article>
        </section>

        <section v-else-if="products.data.length" class="catalog-grid">
          <UnifiedProductCard
            v-for="product in products.data"
            :key="product.id"
            :product="product"
            source="catalog_grid"
          />
        </section>

        <section v-else-if="!isLoading && !hasError" class="empty-results">
          <h2>Ничего не найдено</h2>
          <p>Попробуйте изменить поиск или сбросить фильтры каталога.</p>
          <Button v-if="hasActiveFilters" type="button" variant="secondary" @click="resetCatalogFilters">
            Сбросить фильтры
          </Button>
        </section>

        <footer v-if="products.last_page > 1" class="pagination">
          <button :disabled="products.current_page <= 1" @click="goToPage(products.current_page - 1)">
            Назад
          </button>
          <span>Страница {{ products.current_page }} из {{ products.last_page }}</span>
          <button :disabled="products.current_page >= products.last_page" @click="goToPage(products.current_page + 1)">
            Дальше
          </button>
        </footer>
      </div>
    </section>

    <p v-if="hasError" class="status status--warn">Ошибка загрузки каталога. Проверь backend API.</p>
  </main>
</template>

<style scoped>
.catalog {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 24px 0 52px;
}

.breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--color-text-soft);
}

.breadcrumbs a {
  color: inherit;
}

.catalog-header h1 {
  font-family: var(--font-display);
  font-size: clamp(44px, 7vw, 84px);
  line-height: 0.9;
}

.catalog-empty-landing {
  margin-top: 22px;
  padding: 28px 20px;
  border-radius: 16px;
  border: 1px dashed var(--border);
  background: var(--muted);
  text-align: center;
  color: var(--muted-foreground);
  font-size: 15px;
  line-height: 1.5;
}

.catalog-empty-landing p {
  margin: 0 0 12px;
}

.catalog-empty-landing a {
  font-weight: 600;
  color: var(--foreground);
}

.catalog-landing {
  margin-top: 22px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.category-showcase {
  border: 1px solid color-mix(in srgb, var(--border) 85%, #fff);
  border-radius: 20px;
  overflow: hidden;
  background: transparent;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.category-showcase:hover,
.category-showcase:focus-visible {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--ring) 35%, var(--border));
  box-shadow:
    0 12px 24px rgb(15 23 42 / 10%),
    0 2px 6px rgb(15 23 42 / 6%);
  outline: none;
}

.category-showcase--skeleton {
  pointer-events: none;
}

.category-showcase--skeleton .category-showcase__media > :deep(.app-skeleton) {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
}

.category-showcase__overlay--skeleton {
  background: linear-gradient(180deg, transparent 25%, rgb(15 23 42 / 55%) 100%);
}

.category-showcase__media {
  position: relative;
  aspect-ratio: 5 / 3;
  background: #f1f5f9;
}

.category-showcase__media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.category-showcase__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;
  padding: 18px;
  background: linear-gradient(180deg, rgb(0 0 0 / 4%) 35%, rgb(0 0 0 / 52%) 100%);
}

.category-showcase__text h2 {
  margin: 0;
  color: #fff;
  font-size: clamp(30px, 2.2vw, 38px);
  line-height: 1.1;
  font-family: var(--font-display);
  letter-spacing: -0.01em;
}

.category-showcase__text p {
  margin: 0;
  color: rgb(255 255 255 / 88%);
  font-size: 15px;
  font-weight: 600;
}

.category-showcase__arrow {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  color: #fff;
  background: rgb(255 255 255 / 14%);
  border: 1px solid rgb(255 255 255 / 22%);
  font-size: 28px;
  line-height: 1;
}

.catalog-layout {
  display: grid;
  grid-template-columns: minmax(208px, 252px) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
  margin-top: 22px;
}

.catalog-sidebar {
  position: sticky;
  /* Иначе верх фильтра уходит под липкий .site-header (top:0, z-index:50) — видно только «середину» панели */
  top: calc(var(--site-header-sticky-offset, 118px) + 10px);
  z-index: 40;
  align-self: start;
}

.sidebar-scroll {
  height: auto;
  max-height: calc(100vh - var(--site-header-sticky-offset, 118px) - 24px);
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 5%);
}

.sidebar-card {
  padding: 8px 12px 12px;
  min-width: 0;
}

.sidebar-section + .sidebar-section {
  margin-top: 0;
}

.sidebar-section {
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}

.sidebar-card .sidebar-section:last-of-type {
  border-bottom: none;
  padding-bottom: 6px;
}

.sidebar-section__title {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 3px 0;
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.35;
  letter-spacing: -0.02em;
  text-align: left;
  color: var(--foreground);
}

.sidebar-section__body {
  margin-top: 0;
  padding: 6px 0 4px;
  border-top: 0;
}

.sidebar-skeleton {
  display: grid;
  gap: 10px;
}

.sidebar-skeleton__section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 0;
  gap: 8px;
}

.toolbar__select {
  display: grid;
}

.toolbar__select-control {
  width: 100%;
  min-width: 0;
  min-height: 38px;
  padding: 0 32px 0 10px;
  box-sizing: border-box;
  border-radius: 8px;
  border: 1px solid var(--border);
  background-color: var(--background);
  color: var(--foreground);
  font-family: inherit;
  font-size: 14px;
  line-height: 1.25;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
}

.toolbar__select-control:focus {
  outline: none;
  border-color: var(--foreground);
}

.toolbar__quick-toggles {
  margin-top: 8px;
  display: grid;
  gap: 1px;
}

.toolbar__price {
  display: grid;
  gap: 7px;
  color: var(--muted-foreground);
  font-size: 11px;
}

.toolbar__price-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px;
}

.toolbar__price-field {
  display: grid;
  gap: 4px;
}

.toolbar__price-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--muted-foreground);
  line-height: 1.3;
}

.toolbar__price input {
  min-height: 36px;
}

.toolbar__price-input {
  width: 100%;
  box-sizing: border-box;
  min-height: 36px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--background);
  color: var(--foreground);
  font-family: inherit;
  font-size: 14px;
  line-height: 1.25;
  box-shadow: none;
}

.toolbar__price-input:focus {
  outline: none;
  border-color: var(--foreground);
}

.tag-filters {
  display: grid;
  gap: 0;
}

.filter-check-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 0;
  border-radius: 0;
  background: transparent;
  min-width: 0;
  min-height: 0;
  height: auto;
  padding: 5px 2px;
  color: var(--foreground);
  font-size: 13px;
  font-weight: 400;
  line-height: 1.3;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.filter-check-item__left {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 1 1 0;
  min-width: 0;
}

.filter-check-item__left span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.filter-native-checkbox {
  width: 18px;
  height: 18px;
  margin: 0;
  flex-shrink: 0;
  cursor: pointer;
  accent-color: #09090b;
  pointer-events: none;
}

.filter-check-item--disabled .filter-native-checkbox {
  opacity: 0.5;
}

.filter-check-item--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.filter-check-item:hover {
  background: color-mix(in srgb, var(--muted), transparent 40%);
  border-color: transparent;
}

.filter-check-item--active {
  background: transparent;
  border-color: transparent;
  color: var(--foreground);
  font-weight: 600;
}

.filter-check-item--active:hover {
  background: color-mix(in srgb, var(--muted), transparent 40%);
  border-color: transparent;
}

.chip-count {
  flex: 0 0 auto;
  margin-left: 0;
  padding: 0;
  min-width: 2.5em;
  text-align: right;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.3;
  font-variant-numeric: tabular-nums;
  color: var(--muted-foreground);
  background: transparent;
  border-radius: 0;
}

.chip-count::before {
  content: '(';
}

.chip-count::after {
  content: ')';
}

.filter-check-item--active .chip-count {
  color: var(--muted-foreground);
  background: transparent;
}

.characteristics-groups {
  display: grid;
  gap: 6px;
}

.characteristics-group {
  padding: 5px 0 6px;
  border: 0;
  border-bottom: 1px solid color-mix(in srgb, var(--border), transparent 50%);
  border-radius: 0;
  background: transparent;
}

.characteristics-group:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.characteristics-group__title {
  margin: 0 0 4px;
  color: var(--muted-foreground);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  line-height: 1.3;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filters--root {
  margin-bottom: 14px;
}

.chip {
  padding: 7px 12px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--background);
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease,
    transform 0.2s ease;
}

.chip--active {
  border-color: var(--primary);
  background: var(--primary);
  color: var(--primary-foreground);
  box-shadow: none;
}

.sidebar-reset {
  width: 100%;
  margin-top: 4px;
  padding: 11px 2px 4px;
  border: 0;
  border-top: 1px solid var(--border);
  border-radius: 0;
  background: transparent;
  color: var(--foreground);
  font-size: 12px;
  line-height: 1.35;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.15s ease,
    opacity 0.15s ease;
}

.sidebar-reset:hover {
  border-color: transparent;
  background: transparent;
  color: var(--primary);
  opacity: 0.92;
}

.catalog-content {
  min-width: 0;
}

.subcategory-strip {
  margin-bottom: 16px;
}

.subcategory-strip__items {
  display: none;
}

.subcategory-strip__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.subcategory-card {
  position: relative;
  border: 1px solid color-mix(in srgb, var(--border) 85%, #fff);
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.subcategory-card:hover,
.subcategory-card:focus-visible {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--ring) 35%, var(--border));
  box-shadow:
    0 8px 18px rgb(15 23 42 / 9%),
    0 1px 4px rgb(15 23 42 / 6%);
  outline: none;
}

.subcategory-card--active {
  border-color: color-mix(in srgb, var(--ring) 45%, var(--border));
}

.subcategory-card__media {
  position: relative;
  aspect-ratio: 1 / 1;
  background: #f1f5f9;
}

.subcategory-card__media img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.subcategory-card__overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
  padding: 12px;
  background: linear-gradient(180deg, rgb(0 0 0 / 2%) 38%, rgb(0 0 0 / 50%) 100%);
}

.subcategory-card__text h3 {
  margin: 0;
  color: #fff;
  font-size: 18px;
  line-height: 1.1;
  font-family: var(--font-display);
}

.subcategory-card__text p {
  margin: 0;
  color: rgb(255 255 255 / 86%);
  font-size: 13px;
  font-weight: 600;
}

.subcategory-card__arrow {
  flex: 0 0 auto;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  color: #fff;
  background: rgb(255 255 255 / 14%);
  border: 1px solid rgb(255 255 255 / 24%);
  font-size: 24px;
  line-height: 1;
}

.subcategory-card--skeleton {
  aspect-ratio: 1 / 1;
  background: #f1f5f9;
  pointer-events: none;
}

.subcategory-card--skeleton > :deep(.app-skeleton) {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: 0;
}

.subcategory-card__skeleton-overlay {
  position: absolute;
  inset: auto 12px 12px;
  display: grid;
  gap: 8px;
}

.catalog-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 1360px) {
  .catalog-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .subcategory-strip__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 1180px) {
  .subcategory-strip__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .catalog-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.catalog-skeleton-card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 23 42 / 8%);
  padding: 10px;
}

.catalog-skeleton-card__media {
  position: relative;
  aspect-ratio: 4 / 3;
  border: 1px solid rgb(226 232 240 / 90%);
  border-radius: 12px;
  overflow: hidden;
}

.catalog-skeleton-card__rail {
  position: absolute;
  top: 10px;
  right: 10px;
  display: grid;
  gap: 5px;
}

.catalog-skeleton-card__body {
  display: grid;
  flex: 1;
  gap: 6px;
  padding: 10px 2px 6px;
}

.catalog-skeleton-card__actions {
  display: flex;
  min-height: 48px;
  align-items: center;
  padding: 0 2px 2px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 24px;
}

.pagination button {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.45;
  cursor: default;
}

.status {
  margin-top: 18px;
  color: #4d5b75;
}

.status--warn {
  color: #b95b09;
}

.empty-results {
  margin-top: 18px;
  padding: 24px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}

.empty-results h2 {
  margin: 0;
  font-size: 24px;
}

.empty-results p {
  margin: 8px 0 0;
  color: #63718d;
}

.empty-results button {
  margin-top: 14px;
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}

@media (max-width: 720px) {
  .catalog-landing {
    grid-template-columns: 1fr;
  }

  .category-showcase {
    border-radius: 16px;
  }

  .category-showcase__overlay {
    padding: 14px;
  }

  .category-showcase__text h2 {
    font-size: 28px;
  }

  .category-showcase__text p {
    font-size: 14px;
  }

  .catalog-layout {
    grid-template-columns: 1fr;
  }

  .catalog-sidebar {
    position: static;
  }

  .sidebar-scroll {
    height: auto;
    max-height: none;
    overflow: visible;
  }

  .sidebar-card {
    padding: 8px 12px 14px;
  }

  .toolbar__select-control {
    min-width: 100%;
  }

  .toolbar__price-fields {
    grid-template-columns: 1fr;
  }

  .subcategory-strip__grid {
    grid-template-columns: 1fr;
  }

  .subcategory-card {
    border-radius: 12px;
  }

  .subcategory-card__overlay {
    padding: 10px;
  }

  .subcategory-card__text h3 {
    font-size: 18px;
  }

  .catalog-grid {
    grid-template-columns: 1fr;
  }
}
</style>
