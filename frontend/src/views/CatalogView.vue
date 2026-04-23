<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { setSeoMeta } from '@/lib/seo'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'

type Category = {
  id: number
  name: string
  slug: string
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

const activeCategory = computed(() => (route.query.category as string | undefined) ?? '')
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
const page = computed(() => Number(route.query.page ?? '1'))
const hasActiveFilters = computed(
  () =>
    Boolean(activeCategory.value) ||
    Boolean(activeQuery.value) ||
    Boolean(activeSort.value) ||
    Boolean(activePriceMin.value) ||
    Boolean(activePriceMax.value) ||
    activeInStock.value ||
    activeOnSale.value ||
    activeTags.value.length > 0 ||
    activeBrands.value.length > 0 ||
    activeColors.value.length > 0 ||
    activeSizes.value.length > 0,
)
const activeCategoryLabel = computed(() => {
  for (const category of categories.value) {
    if (category.slug === activeCategory.value) {
      return category.name
    }

    const child = category.subcategories?.find((item) => item.slug === activeCategory.value)

    if (child) {
      return `${category.name} / ${child.name}`
    }
  }

  return ''
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
    ...(activeCategory.value ? { category: activeCategory.value } : {}),
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
  }
}

function selectCategory(slug = '') {
  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      category: slug || undefined,
      page: undefined,
    },
  })
}

function goToPage(nextPage: number) {
  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextPage > 1 ? { page: String(nextPage) } : {}),
    },
  })
}

function applyFilterControls() {
  const normalizedMin = normalizeInputValue(priceMinInput.value)
  const normalizedMax = normalizeInputValue(priceMaxInput.value)

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(normalizedMin ? { price_min: normalizedMin } : { price_min: undefined }),
      ...(normalizedMax ? { price_max: normalizedMax } : { price_max: undefined }),
      page: undefined,
    },
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

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(value ? { sort: value } : { sort: undefined }),
      page: undefined,
    },
  })
}

function toggleInStock() {
  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(activeInStock.value ? { in_stock: undefined } : { in_stock: '1' }),
      page: undefined,
    },
  })
}

function toggleOnSale() {
  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(activeOnSale.value ? { on_sale: undefined } : { on_sale: '1' }),
      page: undefined,
    },
  })
}

function toggleTagFilter(tagCode: string) {
  const nextTags = toggleMultiValue(activeTags.value, tagCode)

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextTags.length ? { tags: nextTags.join(',') } : { tags: undefined }),
      page: undefined,
    },
  })
}

function toggleBrandFilter(brand: string) {
  const nextBrands = toggleMultiValue(activeBrands.value, brand)

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextBrands.length ? { brands: nextBrands.join(',') } : { brands: undefined }),
      page: undefined,
    },
  })
}

function toggleColorFilter(color: string) {
  const nextColors = toggleMultiValue(activeColors.value, color)

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextColors.length ? { colors: nextColors.join(',') } : { colors: undefined }),
      page: undefined,
    },
  })
}

function toggleSizeFilter(size: string) {
  const nextSizes = toggleMultiValue(activeSizes.value, size)

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextSizes.length ? { sizes: nextSizes.join(',') } : { sizes: undefined }),
      page: undefined,
    },
  })
}

function resetCatalogFilters() {
  void router.push({
    path: '/catalog',
    query: {},
  })
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

function isFacetValueVisible(count: number, isActive: boolean) {
  return count > 0 || isActive
}

function categoryCountBySlug(slug: string): number {
  return availableCategoryCounts.value[slug] ?? 0
}

function categoryDisplayCount(category: Category): number {
  const own = categoryCountBySlug(category.slug)
  const children = category.subcategories?.reduce((sum, item) => sum + categoryCountBySlug(item.slug), 0) ?? 0

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
  if (activeCategory.value === category.slug) {
    return true
  }

  return categoryCountBySlug(category.slug) > 0
}

function hasActiveSubcategory(category: Category): boolean {
  return category.subcategories?.some((item) => item.slug === activeCategory.value) ?? false
}

function syncCategoryAccordions() {
  const nextState = { ...categoryAccordions.value }

  for (const category of categories.value) {
    if (!(category.slug in nextState)) {
      nextState[category.slug] = hasActiveSubcategory(category)
    }

    if (hasActiveSubcategory(category)) {
      nextState[category.slug] = true
    }
  }

  categoryAccordions.value = nextState
}

function toggleCategoryAccordion(slug: string) {
  categoryAccordions.value = {
    ...categoryAccordions.value,
    [slug]: !categoryAccordions.value[slug],
  }
}

function findActiveCategoryMeta() {
  for (const category of categories.value) {
    if (category.slug === activeCategory.value) {
      return category
    }

    const child = category.subcategories?.find((item) => item.slug === activeCategory.value)

    if (child) {
      return child
    }
  }

  return null
}

function syncCatalogSeo() {
  const activeCategoryMeta = findActiveCategoryMeta()

  if (!activeCategoryMeta) {
    return
  }

  setSeoMeta({
    title: activeCategoryMeta.seo_title?.trim() || `${activeCategoryMeta.name} — каталог Shoria`,
    description:
      activeCategoryMeta.seo_description?.trim() ||
      `Подборка товаров Shoria в категории ${activeCategoryMeta.name}: фильтры, поиск и быстрый выбор.`,
    robots: 'index,follow',
    canonical: `${window.location.origin}/catalog?category=${activeCategoryMeta.slug}`,
  })
}

onMounted(async () => {
  priceMinInput.value = activePriceMin.value
  priceMaxInput.value = activePriceMax.value
  await loadCategories()
  syncCatalogSeo()
  await loadProducts()
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
      <template v-if="activeCategoryLabel">
        <span>/</span>
        <span>{{ activeCategoryLabel }}</span>
      </template>
    </nav>

    <header class="catalog-header">
      <h1>Каталог</h1>
      <p>Подборки и все доступные модели из API.</p>
    </header>

    <section class="catalog-layout">
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
                      v-for="subcategory in category.subcategories"
                      v-show="shouldShowSubcategory(subcategory)"
                      :key="subcategory.id"
                      type="button"
                      class="chip chip--subcategory"
                      :class="{ 'chip--active': activeCategory === subcategory.slug }"
                      @click="selectCategory(subcategory.slug)"
                    >
                      {{ subcategory.name }}
                      <span class="chip-count">{{ categoryCountBySlug(subcategory.slug) }}</span>
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
