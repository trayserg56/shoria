<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { fetchJson } from '@/lib/api'
import { setSeoMeta } from '@/lib/seo'
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

type PaginatedProducts = {
  current_page: number
  last_page: number
  data: Product[]
}

const route = useRoute()
const router = useRouter()

const categories = ref<Category[]>([])
const categoryAccordions = ref<Record<string, boolean>>({})
const products = ref<PaginatedProducts>({
  current_page: 1,
  last_page: 1,
  data: [],
})
const isLoading = ref(false)
const hasError = ref(false)
const priceMinInput = ref((route.query.price_min as string | undefined) ?? '')
const priceMaxInput = ref((route.query.price_max as string | undefined) ?? '')

const activeCategory = computed(() => (route.query.category as string | undefined) ?? '')
const activeQuery = computed(() => (route.query.q as string | undefined) ?? '')
const activeSort = computed(() => (route.query.sort as string | undefined) ?? '')
const activePriceMin = computed(() => (route.query.price_min as string | undefined) ?? '')
const activePriceMax = computed(() => (route.query.price_max as string | undefined) ?? '')
const activeInStock = computed(() => (route.query.in_stock as string | undefined) === '1')
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
const page = computed(() => Number(route.query.page ?? '1'))
const hasActiveFilters = computed(
  () =>
    Boolean(activeCategory.value) ||
    Boolean(activeQuery.value) ||
    Boolean(activeSort.value) ||
    Boolean(activePriceMin.value) ||
    Boolean(activePriceMax.value) ||
    activeInStock.value ||
    activeTags.value.length > 0,
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
const filterSections = ref({
  sort: true,
  price: true,
  tags: false,
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

  if (activeTags.value.length) {
    query.set('tags', activeTags.value.join(','))
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
      tags: activeTags.value.join(',') || 'none',
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
    ...(activeTags.value.length ? { tags: activeTags.value.join(',') } : {}),
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

function toggleTagFilter(tagCode: string) {
  const current = new Set(activeTags.value)

  if (current.has(tagCode)) {
    current.delete(tagCode)
  } else {
    current.add(tagCode)
  }

  const nextTags = [...current]

  void router.push({
    path: '/catalog',
    query: {
      ...buildBaseCatalogQuery(),
      ...(nextTags.length ? { tags: nextTags.join(',') } : { tags: undefined }),
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
            </div>
          </div>

          <div class="sidebar-section">
            <button type="button" class="sidebar-section__toggle" @click="toggleFilterSection('price')">
              <span>Цена и наличие</span>
              <span class="sidebar-section__chevron">{{ filterSections.price ? '−' : '+' }}</span>
            </button>
            <div v-if="filterSections.price" class="sidebar-section__body">
              <form class="toolbar__price" @submit.prevent="applyFilterControls">
                <div class="toolbar__price-fields">
                  <input v-model="priceMinInput" type="number" min="0" placeholder="от" />
                  <input v-model="priceMaxInput" type="number" min="0" placeholder="до" />
                </div>
                <button type="submit">Применить</button>
              </form>

              <button
                class="toolbar__stock"
                :class="{ 'toolbar__stock--active': activeInStock }"
                @click="toggleInStock"
              >
                Только в наличии
              </button>
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
                  v-for="tag in tagOptions"
                  :key="tag.code"
                  class="tag-chip"
                  :class="{ 'tag-chip--active': activeTags.includes(tag.code) }"
                  @click="toggleTagFilter(tag.code)"
                >
                  {{ tag.label }}
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
                <div v-for="category in categories" :key="category.id" class="category-group">
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
                      :key="subcategory.id"
                      type="button"
                      class="chip chip--subcategory"
                      :class="{ 'chip--active': activeCategory === subcategory.slug }"
                      @click="selectCategory(subcategory.slug)"
                    >
                      {{ subcategory.name }}
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
        <section v-if="products.data.length" class="catalog-grid">
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

    <p v-if="isLoading" class="status">Загружаем каталог...</p>
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

.toolbar__price button {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
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

.tag-chip--active {
  border-color: #1f2233;
  background: #1f2233;
  color: #fff;
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
  text-align: left;
  border: 0;
  background: transparent;
  padding: 0;
  color: #20253a;
  font-weight: 700;
  box-shadow: none;
}

.chip--subcategory {
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
