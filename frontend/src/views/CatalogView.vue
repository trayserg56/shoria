<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { setSeoMeta } from '@/lib/seo'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'

type Category = {
  id: number
  name: string
  slug: string
  description?: string | null
  image_url?: string | null
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
const categoryAccordions = ref<Record<string, boolean>>({})
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
const isLoading = ref(false)
const hasError = ref(false)
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
const activeCategorySlugSet = computed(() => new Set(activeCategorySegments.value))
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
const availableCategoryCounts = computed(() => {
  return products.value.filters.categories.reduce<Record<string, number>>((acc, item) => {
    acc[item.slug] = item.count

    return acc
  }, {})
})

const filterSections = ref({
  sort: true,
  price: true,
  tags: true,
  brands: true,
  colors: false,
  sizes: false,
  characteristics: false,
  categories: true,
})

function normalizeInputValue(value: unknown): string {
  if (value === null || value === undefined) {
    return ''
  }

  return String(value).trim()
}

async function loadCategories() {
  categories.value = await fetchJson<Category[]>('/api/categories')
  syncCategoryAccordions()
}

async function loadProducts() {
  if (isCategoryLanding.value) {
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
    hasError.value = false
    isLoading.value = false
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

function buildCatalogPath(pathSegments?: string[]) {
  if (!pathSegments?.length) {
    return '/catalog'
  }

  return `/catalog/${pathSegments.map((segment) => encodeURIComponent(segment)).join('/')}`
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

function onSortChange(event: Event) {
  const target = event.target as HTMLSelectElement
  const value = target.value

  navigateCatalog({
    ...buildBaseCatalogQuery(),
    ...(value ? { sort: value } : { sort: undefined }),
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

function toggleFilterSection(section: keyof typeof filterSections.value) {
  filterSections.value[section] = !filterSections.value[section]
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
  const own = categoryCountBySlug(category.slug)
  const children = category.subcategories?.reduce((sum, item) => sum + categoryDisplayCount(item), 0) ?? 0

  return own + children
}

function shouldShowCategory(category: Category): boolean {
  if (activeCategory.value === category.slug) {
    return true
  }

  if (categoryDisplayCount(category) > 0) {
    return true
  }

  return category.subcategories?.some((item) => shouldShowSubcategory(item)) ?? false
}

function shouldShowSubcategory(category: Category): boolean {
  if (activeCategorySlugSet.value.has(category.slug)) {
    return true
  }

  if (categoryDisplayCount(category) > 0) {
    return true
  }

  return category.subcategories?.some((item) => shouldShowSubcategory(item)) ?? false
}

function walkCategories(nodes: Category[], visit: (category: Category) => void) {
  for (const node of nodes) {
    visit(node)
    if (node.subcategories?.length) {
      walkCategories(node.subcategories, visit)
    }
  }
}

function syncCategoryAccordions() {
  const nextState = { ...categoryAccordions.value }

  walkCategories(categories.value, (category) => {
    if (!(category.slug in nextState)) {
      nextState[category.slug] = false
    }
  })

  for (const segment of activeCategorySegments.value) {
    nextState[segment] = true
  }

  categoryAccordions.value = nextState
}

function flattenedVisibleSubcategories(category: Category, depth = 1): Array<{ category: Category; depth: number }> {
  if (!category.subcategories?.length) {
    return []
  }

  const rows: Array<{ category: Category; depth: number }> = []

  for (const subcategory of category.subcategories) {
    if (!shouldShowSubcategory(subcategory)) {
      continue
    }

    rows.push({ category: subcategory, depth })

    if (subcategory.subcategories?.length && categoryAccordions.value[subcategory.slug]) {
      rows.push(...flattenedVisibleSubcategories(subcategory, depth + 1))
    }
  }

  return rows
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

function toggleCategoryAccordion(slug: string) {
  categoryAccordions.value = {
    ...categoryAccordions.value,
    [slug]: !categoryAccordions.value[slug],
  }
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
    syncCategoryAccordions()
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
    <nav class="breadcrumbs" aria-label="Breadcrumbs">
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
      <h1>Каталог</h1>
      <p v-if="isCategoryLanding">Выберите категорию, чтобы открыть товары и фильтры.</p>
      <p v-else>Подборки и все доступные модели из API.</p>
    </header>

    <section v-if="isCategoryLanding" class="catalog-landing">
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
        </div>
        <div class="category-showcase__content">
          <h2>{{ category.name }}</h2>
          <p v-if="category.description">{{ category.description }}</p>
          <p v-else>
            {{ category.subcategories?.length ? `${category.subcategories.length} подкатегорий` : 'Перейти к товарам' }}
          </p>
          <div class="category-showcase__cta">Открыть категорию</div>
        </div>
      </article>
    </section>

    <section v-else class="catalog-layout">
      <aside class="catalog-sidebar">
        <div class="sidebar-card">
          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('sort')">
              <span>Сортировка</span>
              <span class="sidebar-section__chevron">{{ filterSections.sort ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.sort" class="sidebar-section__body">
              <label class="toolbar__select">
                <select :value="activeSort" @change="onSortChange">
                  <option v-for="option in sortOptions" :key="option.value || 'default'" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <div class="toolbar__quick-toggles">
                <button
                  class="toolbar__stock"
                  :class="{ 'toolbar__stock--active': activeInStock }"
                  @click="toggleInStock"
                >
                  Только в наличии
                </button>
                <button
                  class="toolbar__stock"
                  :class="{ 'toolbar__stock--active': activeOnSale }"
                  @click="toggleOnSale"
                >
                  Только со скидкой <span class="chip-count">{{ products.filters.on_sale.count }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('price')">
              <span>Цена и наличие</span>
              <span class="sidebar-section__chevron">{{ filterSections.price ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.price" class="sidebar-section__body">
              <div class="toolbar__price">
                <div class="toolbar__price-fields">
                  <input v-model="priceMinInput" type="number" min="0" placeholder="от" />
                  <input v-model="priceMaxInput" type="number" min="0" placeholder="до" />
                </div>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('tags')">
              <span>Теги</span>
              <span class="sidebar-section__chevron">{{ filterSections.tags ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.tags" class="sidebar-section__body">
              <div class="tag-filters">
                <button
                  v-for="tag in availableTagOptions"
                  :key="tag.code"
                  class="tag-chip"
                  :class="{ 'tag-chip--active': activeTags.includes(tag.code) }"
                  :disabled="!isFacetValueVisible(tag.count, activeTags.includes(tag.code))"
                  @click="toggleTagFilter(tag.code)"
                >
                  {{ tag.label }} <span class="chip-count">{{ tag.count }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('brands')">
              <span>Бренды</span>
              <span class="sidebar-section__chevron">{{ filterSections.brands ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.brands" class="sidebar-section__body">
              <div class="tag-filters">
                <button
                  v-for="brand in availableBrandOptions"
                  v-show="isFacetValueVisible(brand.count, activeBrands.includes(brand.value))"
                  :key="brand.value"
                  class="tag-chip"
                  :class="{ 'tag-chip--active': activeBrands.includes(brand.value) }"
                  @click="toggleBrandFilter(brand.value)"
                >
                  {{ brand.value }} <span class="chip-count">{{ brand.count }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('colors')">
              <span>Цвета</span>
              <span class="sidebar-section__chevron">{{ filterSections.colors ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.colors" class="sidebar-section__body">
              <div class="tag-filters">
                <button
                  v-for="color in availableColorOptions"
                  v-show="isFacetValueVisible(color.count, activeColors.includes(color.value))"
                  :key="color.value"
                  class="tag-chip"
                  :class="{ 'tag-chip--active': activeColors.includes(color.value) }"
                  @click="toggleColorFilter(color.value)"
                >
                  {{ color.value }} <span class="chip-count">{{ color.count }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('sizes')">
              <span>Размеры</span>
              <span class="sidebar-section__chevron">{{ filterSections.sizes ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.sizes" class="sidebar-section__body">
              <div class="tag-filters">
                <button
                  v-for="size in availableSizeOptions"
                  v-show="isFacetValueVisible(size.count, activeSizes.includes(size.value))"
                  :key="size.value"
                  class="tag-chip"
                  :class="{ 'tag-chip--active': activeSizes.includes(size.value) }"
                  @click="toggleSizeFilter(size.value)"
                >
                  {{ size.value }} <span class="chip-count">{{ size.count }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('characteristics')">
              <span>Характеристики</span>
              <span class="sidebar-section__chevron">{{ filterSections.characteristics ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.characteristics" class="sidebar-section__body">
              <div class="characteristics-groups">
                <div v-for="group in availableCharacteristicGroups" :key="group.name" class="characteristics-group">
                  <p class="characteristics-group__title">{{ group.name }}</p>
                  <div class="tag-filters">
                    <button
                      v-for="option in group.values"
                      v-show="isFacetValueVisible(option.count, isCharacteristicActive(group.name, option.value))"
                      :key="`${group.name}:${option.value}`"
                      class="tag-chip"
                      :class="{ 'tag-chip--active': isCharacteristicActive(group.name, option.value) }"
                      @click="toggleCharacteristicFilter(group.name, option.value)"
                    >
                      {{ option.value }} <span class="chip-count">{{ option.count }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('categories')">
              <span>Категории</span>
              <span class="sidebar-section__chevron">{{ filterSections.categories ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.categories" class="sidebar-section__body">
              <div class="filters filters--root">
                <button type="button" class="chip" :class="{ 'chip--active': !activeCategory }" @click="selectCategory()">
                  Все
                </button>
              </div>

              <div class="category-groups">
                <div
                  v-for="category in categories"
                  v-show="shouldShowCategory(category)"
                  :key="category.id"
                  class="category-group"
                >
                  <div class="category-group__row">
                    <button
                      type="button"
                      class="chip chip--parent"
                      :class="{
                        'chip--active': activeCategory === category.slug,
                        'chip--with-children': category.subcategories?.length,
                      }"
                      @click="selectCategory(category.slug)"
                    >
                      {{ category.name }}
                      <span class="chip-count">{{ categoryDisplayCount(category) }}</span>
                    </button>
                    <button
                      v-if="category.subcategories?.length"
                      type="button"
                      class="subcategory-toggle"
                      :class="{ 'subcategory-toggle--open': categoryAccordions[category.slug] }"
                      @click="toggleCategoryAccordion(category.slug)"
                    >
                      {{ categoryAccordions[category.slug] ? '−' : '+' }}
                    </button>
                  </div>

                  <div
                    v-if="category.subcategories?.length && categoryAccordions[category.slug]"
                    class="subcategory-list"
                  >
                    <button
                      v-for="entry in flattenedVisibleSubcategories(category)"
                      :key="entry.category.id"
                      type="button"
                      class="chip chip--subcategory"
                      :class="{ 'chip--active': activeCategory === entry.category.slug }"
                      :style="entry.depth > 1 ? { marginLeft: `${(entry.depth - 1) * 14}px` } : undefined"
                      @click="selectCategory(entry.category.slug)"
                    >
                      {{ entry.category.name }}
                      <span class="chip-count">{{ categoryDisplayCount(entry.category) }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <button v-if="hasActiveFilters" type="button" class="sidebar-reset" @click="resetCatalogFilters">
            Сбросить фильтры
          </button>
        </div>
      </aside>

      <div class="catalog-content">
        <section v-if="childSubcategories.length" class="subcategory-strip">
          <h2>Подкатегории {{ activeCategoryNode?.name }}</h2>
          <div class="subcategory-strip__items">
            <button
              v-for="subcategory in childSubcategories"
              :key="subcategory.id"
              type="button"
              class="subcategory-strip__chip"
              :class="{ 'subcategory-strip__chip--active': activeCategory === subcategory.slug }"
              @click="selectCategory(subcategory.slug)"
            >
              {{ subcategory.name }}
            </button>
          </div>
        </section>

        <section v-if="isLoading && !products.data.length" class="catalog-grid">
          <article v-for="index in 6" :key="`catalog-skeleton-${index}`" class="catalog-skeleton-card">
            <AppSkeleton width="100%" height="250px" radius="28px 28px 0 0" />
            <div class="catalog-skeleton-card__body">
              <AppSkeleton width="30%" height="14px" />
              <AppSkeleton width="58%" height="24px" />
              <AppSkeleton width="34%" height="24px" />
              <AppSkeleton width="100%" height="52px" radius="16px" />
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
          <button v-if="hasActiveFilters" type="button" @click="resetCatalogFilters">Сбросить фильтры</button>
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
  width: min(1240px, 92vw);
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

.catalog-header p {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.catalog-landing {
  margin-top: 22px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
}

.category-showcase {
  border: 1px solid #efe2d4;
  border-radius: 24px;
  overflow: hidden;
  background: #fffdf9;
  cursor: pointer;
  transition:
    transform 0.22s ease,
    box-shadow 0.22s ease,
    border-color 0.22s ease;
}

.category-showcase:hover,
.category-showcase:focus-visible {
  transform: translateY(-4px);
  border-color: #e8c7a8;
  box-shadow: 0 18px 36px rgb(31 34 51 / 10%);
  outline: none;
}

.category-showcase__media {
  position: relative;
  aspect-ratio: 16 / 10;
  background: linear-gradient(135deg, #f4ecdf 0%, #e9ddcd 100%);
}

.category-showcase__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.category-showcase__placeholder {
  width: 100%;
  height: 100%;
  background:
    radial-gradient(circle at 20% 20%, rgb(255 255 255 / 80%) 0, rgb(255 255 255 / 0%) 45%),
    linear-gradient(140deg, #f4ecdf 0%, #eadcc9 100%);
}

.category-showcase__content {
  padding: 16px;
  display: grid;
  gap: 8px;
}

.category-showcase__content h2 {
  margin: 0;
  font-size: 26px;
  line-height: 1.05;
  font-family: var(--font-display);
  letter-spacing: 0.01em;
}

.category-showcase__content p {
  margin: 0;
  color: #5f6f88;
  font-size: 15px;
}

.category-showcase__cta {
  margin-top: 8px;
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #d7c7b6;
  color: #253049;
  background: #fff;
  font-size: 13px;
  font-weight: 700;
}

.catalog-layout {
  display: grid;
  grid-template-columns: minmax(250px, 290px) minmax(0, 1fr);
  gap: 24px;
  align-items: start;
  margin-top: 22px;
}

.catalog-sidebar {
  position: sticky;
  top: 96px;
}

.sidebar-card {
  padding: 18px;
  max-height: calc(100vh - 116px);
  overflow-y: auto;
  border: 1px solid #eadfce;
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgb(255 252 247 / 98%), rgb(255 246 235 / 88%));
  box-shadow: 0 14px 42px rgb(16 24 40 / 7%);
  backdrop-filter: blur(10px);
}

.sidebar-section + .sidebar-section {
  margin-top: 18px;
}

.sidebar-section__toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 2px 0;
  border: 0;
  background: transparent;
  color: #4d5d79;
  font: inherit;
  font-size: 15px;
  font-weight: 800;
  text-align: left;
  cursor: pointer;
}

.sidebar-section__chevron {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 1px solid #ddcfbd;
  border-radius: 999px;
  color: #b35a16;
  background: linear-gradient(180deg, #fffaf3, #fff1e2);
  box-shadow: 0 4px 10px rgb(243 91 4 / 10%);
}

.sidebar-section__body {
  margin-top: 12px;
}

.toolbar__select {
  display: grid;
}

.toolbar__select select {
  min-width: 220px;
  padding: 10px 12px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  font: inherit;
}

.toolbar__quick-toggles {
  margin-top: 10px;
  display: grid;
  gap: 8px;
}

.toolbar__price {
  display: grid;
  gap: 10px;
  color: #63718d;
}

.toolbar__price-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.toolbar__price input {
  padding: 10px 12px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  font: inherit;
}

.toolbar__stock {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
}

.toolbar__stock--active {
  border-color: #f35b04;
  background: #fff2e8;
  color: #c74803;
}

.tag-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-chip {
  padding: 8px 12px;
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  background: rgb(255 255 255 / 92%);
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    transform 0.2s ease;
}

.tag-chip:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.tag-chip--active {
  border-color: #1f2233;
  background: #1f2233;
  color: #fff;
}

.chip-count {
  margin-left: 6px;
  font-size: 12px;
  color: #7c879f;
}

.tag-chip--active .chip-count {
  color: rgb(255 255 255 / 86%);
}

.characteristics-groups {
  display: grid;
  gap: 10px;
}

.characteristics-group {
  padding: 10px;
  border: 1px solid #ecdcca;
  border-radius: 14px;
  background: rgb(255 253 249 / 92%);
}

.characteristics-group__title {
  margin: 0 0 8px;
  color: #5b6a84;
  font-size: 13px;
  font-weight: 700;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.filters--root {
  margin-bottom: 14px;
}

.chip {
  padding: 8px 14px;
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  background: rgb(255 255 255 / 92%);
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease,
    transform 0.2s ease;
}

.chip--active {
  border-color: #f35b04;
  background: linear-gradient(180deg, #fff7ef, #ffefe0);
  color: #c74803;
  box-shadow: inset 0 0 0 1px rgb(243 91 4 / 10%);
}

.chip--parent {
  flex: 1 1 auto;
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  text-align: left;
  border: 0;
  background: transparent;
  padding: 0;
  color: #20253a;
  font-weight: 700;
  box-shadow: none;
}

.chip--subcategory {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 13px;
  background: #fffdf9;
  font-size: 14px;
}

.category-groups {
  display: grid;
  gap: 10px;
}

.category-group {
  display: grid;
  gap: 8px;
  padding: 10px;
  border: 1px solid #ecdcca;
  border-radius: 18px;
  background: rgb(255 252 247 / 72%);
}

.category-group__row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.category-group__row:has(.chip--active) {
  color: #c74803;
}

.category-group__row .chip--parent.chip--active {
  color: #c74803;
}

.subcategory-toggle {
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border: 1px solid #e5cdb4;
  border-radius: 999px;
  background: linear-gradient(180deg, #fffaf4, #fff1e4);
  color: #b35a16;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 6px 14px rgb(243 91 4 / 8%);
}

.subcategory-toggle--open {
  border-color: #f0c8ad;
  background: linear-gradient(180deg, #fff3e8, #ffe8d6);
}

.subcategory-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 2px 0 0 10px;
  border-left: 2px solid #f1dfcd;
}

.sidebar-reset {
  width: 100%;
  margin-top: 18px;
  padding: 11px 14px;
  border: 1px solid #f0c8ad;
  border-radius: 12px;
  background: #fff2e8;
  color: #c74803;
  font-weight: 700;
  cursor: pointer;
}

.catalog-content {
  min-width: 0;
}

.subcategory-strip {
  margin-bottom: 16px;
  padding: 14px;
  border: 1px solid #ecdcca;
  border-radius: 18px;
  background: rgb(255 252 247 / 82%);
}

.subcategory-strip h2 {
  margin: 0 0 10px;
  font-size: 20px;
}

.subcategory-strip__items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.subcategory-strip__chip {
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid #d6d3cc;
  background: #fff;
  color: #23293a;
  cursor: pointer;
}

.subcategory-strip__chip--active {
  border-color: #f35b04;
  background: #fff1e6;
  color: #c74803;
}

.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.catalog-skeleton-card {
  overflow: hidden;
  border: 1px solid #efe2d4;
  border-radius: 28px;
  background: #fffdf9;
}

.catalog-skeleton-card__body {
  display: grid;
  gap: 12px;
  padding: 18px;
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
  border: 1px solid #d6d3cc;
  border-radius: 10px;
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
  border: 1px solid #d6d3cc;
  border-radius: 16px;
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
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}

@media (max-width: 720px) {
  .catalog-layout {
    grid-template-columns: 1fr;
  }

  .catalog-sidebar {
    position: static;
  }

  .sidebar-card {
    padding: 16px;
    max-height: none;
    overflow: visible;
  }

  .toolbar__select select {
    min-width: 100%;
  }

  .toolbar__price-fields {
    grid-template-columns: 1fr;
  }
}
</style>
