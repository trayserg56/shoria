<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { setSeoMeta } from '@/lib/seo'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select } from '@/components/ui/select'

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
  sort: false,
  price: false,
  tags: false,
  brands: false,
  colors: false,
  sizes: false,
  characteristics: false,
  categories: false,
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
    isLoading.value = true
    try {
      const landingPreview = await fetchJson<PaginatedProducts>('/api/products')
      products.value = {
        current_page: 1,
        last_page: 1,
        data: [],
        filters: landingPreview.filters,
      }
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

function visibleChildren(category: Category): Category[] {
  return (category.subcategories ?? []).filter((item) => shouldShowSubcategory(item))
}

const categoryTreeRows = computed(() => {
  const rows: Array<{
    category: Category
    depth: number
    hasChildren: boolean
    expanded: boolean
  }> = []

  const walk = (nodes: Category[], depth: number) => {
    for (const node of nodes) {
      const visible = depth === 0 ? shouldShowCategory(node) : shouldShowSubcategory(node)

      if (!visible) {
        continue
      }

      const children = visibleChildren(node)
      const expanded = Boolean(categoryAccordions.value[node.slug])

      rows.push({
        category: node,
        depth,
        hasChildren: children.length > 0,
        expanded,
      })

      if (children.length > 0 && expanded) {
        walk(children, depth + 1)
      }
    }
  }

  walk(categories.value, 0)
  return rows
})

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

    <section v-else-if="isCategoryLanding" class="catalog-landing">
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
          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('sort')">
              <span>Сортировка</span>
              <span class="sidebar-section__chevron">{{ filterSections.sort ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.sort" class="sidebar-section__body">
              <label class="toolbar__select">
                <Select
                  :value="activeSort"
                  class="toolbar__select-control"
                  @change="onSortChange"
                >
                  <option v-for="option in sortOptions" :key="option.value || 'default'" :value="option.value">
                    {{ option.label }}
                  </option>
                </Select>
              </label>
              <div class="toolbar__quick-toggles">
                <label class="filter-check-item" :class="{ 'filter-check-item--active': activeInStock }" @click.prevent="toggleInStock">
                  <div class="filter-check-item__left">
                    <Checkbox :checked="activeInStock" class="filter-check-item__checkbox" />
                    <span>Только в наличии</span>
                  </div>
                </label>
                <label class="filter-check-item" :class="{ 'filter-check-item--active': activeOnSale }" @click.prevent="toggleOnSale">
                  <div class="filter-check-item__left">
                    <Checkbox :checked="activeOnSale" class="filter-check-item__checkbox" />
                    <span>Только со скидкой</span>
                  </div>
                  <span class="chip-count">{{ products.filters.on_sale.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('price')">
              <span>Цена и наличие</span>
              <span class="sidebar-section__chevron">{{ filterSections.price ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.price" class="sidebar-section__body">
              <div class="toolbar__price">
                <div class="toolbar__price-fields">
                  <Input v-model="priceMinInput" class="toolbar__price-input" type="number" min="0" placeholder="от" />
                  <Input v-model="priceMaxInput" class="toolbar__price-input" type="number" min="0" placeholder="до" />
                </div>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('categories')">
              <span>Категории</span>
              <span class="sidebar-section__chevron">{{ filterSections.categories ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.categories" class="sidebar-section__body">
              <div class="category-tree">
                <button
                  type="button"
                  class="category-tree__all"
                  :class="{ 'category-tree__all--active': !activeCategory }"
                  @click="selectCategory()"
                >
                  <span>Все категории</span>
                </button>

                <div class="category-tree__list" role="tree" aria-label="Дерево категорий">
                  <div
                    v-for="row in categoryTreeRows"
                    :key="row.category.id"
                    class="category-tree__row"
                    :style="{ paddingLeft: `${row.depth * 20}px` }"
                    role="treeitem"
                    :aria-expanded="row.hasChildren ? row.expanded : undefined"
                  >
                    <button
                      v-if="row.hasChildren"
                      type="button"
                      class="category-tree__expander"
                      :class="{ 'category-tree__expander--open': row.expanded }"
                      @click="toggleCategoryAccordion(row.category.slug)"
                    >
                      {{ row.expanded ? '⌄' : '›' }}
                    </button>
                    <span v-else class="category-tree__expander-placeholder" />

                    <button
                      type="button"
                      class="category-tree__label"
                      :class="{ 'category-tree__label--active': activeCategory === row.category.slug }"
                      @click="selectCategory(row.category.slug)"
                    >
                      <span class="category-tree__name">{{ row.category.name }}</span>
                      <span class="category-tree__count">{{ categoryDisplayCount(row.category) }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('tags')">
              <span>Теги</span>
              <span class="sidebar-section__chevron">{{ filterSections.tags ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.tags" class="sidebar-section__body">
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
                    <Checkbox
                      :checked="activeTags.includes(tag.code)"
                      :disabled="!isFacetValueVisible(tag.count, activeTags.includes(tag.code))"
                      class="filter-check-item__checkbox"
                    />
                    <span>{{ tag.label }}</span>
                  </div>
                  <span class="chip-count">{{ tag.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('brands')">
              <span>Бренды</span>
              <span class="sidebar-section__chevron">{{ filterSections.brands ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.brands" class="sidebar-section__body">
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
                    <Checkbox :checked="activeBrands.includes(brand.value)" class="filter-check-item__checkbox" />
                    <span>{{ brand.value }}</span>
                  </div>
                  <span class="chip-count">{{ brand.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('colors')">
              <span>Цвета</span>
              <span class="sidebar-section__chevron">{{ filterSections.colors ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.colors" class="sidebar-section__body">
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
                    <Checkbox :checked="activeColors.includes(color.value)" class="filter-check-item__checkbox" />
                    <span>{{ color.value }}</span>
                  </div>
                  <span class="chip-count">{{ color.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('sizes')">
              <span>Размеры</span>
              <span class="sidebar-section__chevron">{{ filterSections.sizes ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.sizes" class="sidebar-section__body">
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
                    <Checkbox :checked="activeSizes.includes(size.value)" class="filter-check-item__checkbox" />
                    <span>{{ size.value }}</span>
                  </div>
                  <span class="chip-count">{{ size.count }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="sidebar-section">
            <Button type="button" variant="ghost" class="sidebar-section__toggle" @click="toggleFilterSection('characteristics')">
              <span>Характеристики</span>
              <span class="sidebar-section__chevron">{{ filterSections.characteristics ? '−' : '+' }}</span>
            </Button>
            <div v-if="filterSections.characteristics" class="sidebar-section__body">
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
                        <Checkbox
                          :checked="isCharacteristicActive(group.name, option.value)"
                          class="filter-check-item__checkbox"
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

            <Button v-if="hasActiveFilters" type="button" variant="secondary" class="sidebar-reset" @click="resetCatalogFilters">
              Сбросить фильтры
            </Button>
          </div>
        </ScrollArea>
      </aside>

      <div class="catalog-content">
        <section v-if="isLoading && childSubcategories.length" class="subcategory-strip" aria-hidden="true">
          <div class="subcategory-strip__grid">
            <article
              v-for="index in Math.min(childSubcategories.length, 3)"
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
  grid-template-columns: minmax(250px, 290px) minmax(0, 1fr);
  gap: 18px;
  align-items: start;
  margin-top: 22px;
}

.catalog-sidebar {
  position: sticky;
  top: 18px;
}

.sidebar-scroll {
  height: calc(100vh - 32px);
  max-height: calc(100vh - 32px);
  min-height: 320px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
}

.sidebar-card {
  padding: 12px 10px 12px 12px;
  overflow: hidden;
}

.sidebar-section + .sidebar-section {
  margin-top: 10px;
}

.sidebar-section__toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 3px 0;
  min-height: 34px;
  height: auto;
  border: 0;
  background: transparent;
  color: #1f2937;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.sidebar-section__chevron {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: 1px solid #d1d5db;
  border-radius: 999px;
  color: #64748b;
  background: #fff;
}

.sidebar-section__body {
  margin-top: 8px;
}

.toolbar__select {
  display: grid;
}

.toolbar__select-control {
  min-width: 220px;
  min-height: 40px;
  border-radius: 10px;
  border-color: #d1d5db;
  background: #fff;
  box-shadow: none;
}

.toolbar__select-control:focus-visible {
  border-color: #5b88ff;
}

.toolbar__quick-toggles {
  margin-top: 8px;
  display: grid;
  gap: 6px;
}

.toolbar__price {
  display: grid;
  gap: 6px;
  color: #64748b;
}

.toolbar__price-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 5px;
}

.toolbar__price input {
  min-height: 48px;
}

.toolbar__price-input {
  border-radius: 10px;
  border-color: #d1d5db;
  background: #fff;
  box-shadow: none;
}

.tag-filters {
  display: grid;
  gap: 3px;
}

.filter-check-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  min-height: 34px;
  height: auto;
  padding: 6px 9px;
  color: #1f2937;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    transform 0.2s ease;
}

.filter-check-item__left {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.filter-check-item__left span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.filter-check-item__checkbox {
  pointer-events: none;
}

.filter-check-item--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.filter-check-item:hover {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.filter-check-item--active {
  background: #e9edf6;
  border-color: #c5d0e4;
  color: #0f172a;
}

.filter-check-item--active:hover {
  background: #e9edf6;
  border-color: #c5d0e4;
}

.chip-count {
  margin-left: 6px;
  font-size: 11px;
  color: #64748b;
}

.filter-check-item--active .chip-count {
  color: #4c5f80;
}

.characteristics-groups {
  display: grid;
  gap: 6px;
}

.characteristics-group {
  padding: 7px 6px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.characteristics-group__title {
  margin: 0 0 6px;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
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
  border: 1px solid #d1d5db;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease,
    transform 0.2s ease;
}

.chip--active {
  border-color: #0f172a;
  background: #0f172a;
  color: #fff;
  box-shadow: none;
}

.category-tree {
  border: 0;
  border-radius: 0;
  background: #fff;
  padding: 0;
}

.category-tree__all {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  min-height: 34px;
  height: auto;
  padding: 6px 9px;
  cursor: pointer;
  margin-bottom: 6px;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease;
}

.category-tree__all--active {
  border-color: #0f172a;
  background: #0f172a;
  color: #fff;
}

.category-tree__list {
  display: grid;
  gap: 1px;
}

.category-tree__row {
  position: relative;
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  min-width: 0;
  overflow: hidden;
}

.category-tree__expander {
  flex: 0 0 18px;
  width: 18px;
  height: 18px;
  display: grid;
  place-items: center;
  border: 0;
  background: transparent;
  color: #64748b;
  font: inherit;
  font-size: 14px;
  line-height: 1;
  cursor: pointer;
  border-radius: 6px;
}

.category-tree__expander:hover {
  background: #f1f5f9;
  color: #334155;
}

.category-tree__expander--open {
  color: #334155;
}

.category-tree__expander-placeholder {
  flex: 0 0 18px;
  width: 18px;
  height: 18px;
}

.category-tree__label {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: #1f2937;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  min-height: 34px;
  height: auto;
  padding: 6px 9px;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    color 0.2s ease;
}

.category-tree__label:hover {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.category-tree__label--active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.category-tree__label--active:hover {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.category-tree__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-tree__count {
  flex: 0 0 auto;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.category-tree__label--active .category-tree__count {
  color: rgb(255 255 255 / 84%);
}

.sidebar-reset {
  width: 100%;
  margin-top: 12px;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-weight: 700;
  cursor: pointer;
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
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
  pointer-events: none;
}

.subcategory-card--skeleton :deep(.app-skeleton) {
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
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
    padding: 16px;
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
