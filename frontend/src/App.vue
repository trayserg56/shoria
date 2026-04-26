<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AuthModal from '@/components/AuthModal.vue'
import { captureFirstTouchAttribution } from '@/lib/attribution'
import { fetchJson } from '@/lib/api'
import { toProductRoute } from '@/lib/product-route'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useCompareStore } from '@/stores/compare'

const cartStore = useCartStore()
const { totalItems } = storeToRefs(cartStore)
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const { totalItems: wishlistTotalItems } = storeToRefs(wishlistStore)
const { totalItems: compareTotalItems } = storeToRefs(compareStore)
const { isAuthenticated, user } = storeToRefs(authStore)
const route = useRoute()
const router = useRouter()
const authModalOpen = ref(false)
const currentYear = new Date().getFullYear()
const headerSearchInput = ref((route.query.q as string | undefined) ?? '')
const categoryMenuRef = ref<HTMLDetailsElement | null>(null)
const searchSuggestions = ref<SearchSuggestion[]>([])
const isSearchFocused = ref(false)
let suggestDebounce: number | null = null
let suggestRequestId = 0

type SearchSuggestion = {
  id: number
  name: string
  slug: string
  price: number
  currency: string
  image_url: string | null
  category: {
    name: string
    slug: string
  } | null
}

type SearchSuggestResponse = {
  query: string
  suggestions: SearchSuggestion[]
}

type NavigationMenuItem = {
  id: number
  label: string
  path: string
  is_external: boolean
  open_in_new_tab: boolean
}

type NavigationResponse = {
  header: NavigationMenuItem[]
  footer: {
    customers: NavigationMenuItem[]
    account: NavigationMenuItem[]
  }
}

type HeaderCategory = {
  id: number
  name: string
  slug: string
  subcategories?: HeaderCategory[]
}

type CityOption = {
  name: string
  region: string
  district: string
}

type StoredCity = {
  name: string
  region: string
  district: string
  source: 'auto' | 'manual' | 'default'
}

type RussianCityEntry = {
  name?: string
  district?: string
  subject?: string
}

const CITY_STORAGE_KEY = 'shoria.city.v1'
const cityPickerOpen = ref(false)
const citySearch = ref('')
const citySelection = ref<StoredCity>({
  name: 'Москва',
  region: 'Москва',
  district: 'Центральный',
  source: 'default',
})
const selectedDistrict = ref('')
const selectedRegion = ref('')
let cityCatalogPromise: Promise<void> | null = null

const fallbackCityOptions: CityOption[] = [
  { name: 'Москва', region: 'Москва', district: 'Центральный' },
  { name: 'Санкт-Петербург', region: 'Санкт-Петербург', district: 'Северо-Западный' },
  { name: 'Екатеринбург', region: 'Свердловская область', district: 'Уральский' },
  { name: 'Челябинск', region: 'Челябинская область', district: 'Уральский' },
]
const cityOptions = ref<CityOption[]>(fallbackCityOptions)

const cityLabel = computed(() => citySelection.value.name)
const citySearchNormalized = computed(() => citySearch.value.trim().toLowerCase())
const filteredCities = computed(() => {
  if (!citySearchNormalized.value) {
    return cityOptions.value
  }

  return cityOptions.value.filter((item) =>
    [item.name, item.region, item.district].some((value) => value.toLowerCase().includes(citySearchNormalized.value)),
  )
})
const districtOptions = computed(() => [...new Set(filteredCities.value.map((item) => item.district))])
const regionOptions = computed(() => {
  const base = filteredCities.value.filter((item) => !selectedDistrict.value || item.district === selectedDistrict.value)
  return [...new Set(base.map((item) => item.region))]
})
const cityOptionsForList = computed(() =>
  filteredCities.value.filter((item) =>
    (!selectedDistrict.value || item.district === selectedDistrict.value)
    && (!selectedRegion.value || item.region === selectedRegion.value),
  ),
)
const popularCities = computed(() =>
  cityOptions.value.filter((item) => ['Москва', 'Санкт-Петербург', 'Екатеринбург', 'Челябинск'].includes(item.name)),
)

const defaultNavigation: NavigationResponse = {
  header: [
    { id: -1, label: 'Главная', path: '/', is_external: false, open_in_new_tab: false },
    { id: -2, label: 'Каталог', path: '/catalog', is_external: false, open_in_new_tab: false },
    { id: -20, label: 'Бренды', path: '/brands', is_external: false, open_in_new_tab: false },
    { id: -3, label: 'Новости', path: '/news', is_external: false, open_in_new_tab: false },
      { id: -10, label: 'Доставка', path: '/pages/delivery', is_external: false, open_in_new_tab: false },
      { id: -17, label: 'Лояльность', path: '/loyalty-program', is_external: false, open_in_new_tab: false },
      { id: -11, label: 'Пользовательское соглашение', path: '/pages/user-agreement', is_external: false, open_in_new_tab: false },
  ],
  footer: {
    customers: [
      { id: -4, label: 'Каталог', path: '/catalog', is_external: false, open_in_new_tab: false },
      { id: -21, label: 'Бренды', path: '/brands', is_external: false, open_in_new_tab: false },
      { id: -5, label: 'Новости', path: '/news', is_external: false, open_in_new_tab: false },
      { id: -6, label: 'Избранное', path: '/wishlist', is_external: false, open_in_new_tab: false },
      { id: -7, label: 'Корзина', path: '/cart', is_external: false, open_in_new_tab: false },
      { id: -12, label: 'Доставка', path: '/pages/delivery', is_external: false, open_in_new_tab: false },
      { id: -13, label: 'Оплата', path: '/pages/payment', is_external: false, open_in_new_tab: false },
      { id: -14, label: 'Возврат и обмен', path: '/pages/returns', is_external: false, open_in_new_tab: false },
      { id: -18, label: 'Программа лояльности', path: '/loyalty-program', is_external: false, open_in_new_tab: false },
      { id: -15, label: 'Пользовательское соглашение', path: '/pages/user-agreement', is_external: false, open_in_new_tab: false },
      { id: -16, label: 'Политика конфиденциальности', path: '/pages/privacy-policy', is_external: false, open_in_new_tab: false },
    ],
    account: [
      { id: -8, label: 'Профиль', path: '/account', is_external: false, open_in_new_tab: false },
      { id: -19, label: 'Мои баллы', path: '/account/loyalty', is_external: false, open_in_new_tab: false },
      { id: -9, label: 'Сравнение', path: '/compare', is_external: false, open_in_new_tab: false },
    ],
  },
}

const navigationMenu = ref<NavigationResponse>(defaultNavigation)
const headerMenuItems = computed(() => navigationMenu.value.header)
const footerCustomersMenuItems = computed(() => navigationMenu.value.footer.customers)
const footerAccountMenuItems = computed(() => navigationMenu.value.footer.account)
const headerCategories = ref<HeaderCategory[]>([])

function normalizeCityName(name: string): string {
  return name
    .trim()
    .toLowerCase()
    .replace(/^г\.\s*/i, '')
    .replace(/^город\s+/i, '')
}

function normalizeRegionName(name: string): string {
  return name
    .trim()
    .toLowerCase()
    .replace(/\s+/g, ' ')
}

async function ensureCityCatalogLoaded() {
  if (cityCatalogPromise) {
    await cityCatalogPromise
    return
  }

  cityCatalogPromise = (async () => {
    try {
      const response = await fetch('/data/russian-cities.json')
      if (!response.ok) {
        return
      }

      const payload = await response.json() as RussianCityEntry[]
      const mapped = payload
        .map((item): CityOption | null => {
          const name = item.name?.trim()
          if (!name) {
            return null
          }

          return {
            name,
            region: item.subject?.trim() || 'Не указан',
            district: item.district?.trim() || 'Не указан',
          }
        })
        .filter((item): item is CityOption => item !== null)
        .sort((a, b) => a.name.localeCompare(b.name, 'ru'))

      if (mapped.length) {
        cityOptions.value = mapped
      }
    } catch (error) {
      console.warn('City catalog loading failed:', error)
    }
  })()

  await cityCatalogPromise
}

function applyCity(city: StoredCity) {
  citySelection.value = city
  selectedDistrict.value = city.district
  selectedRegion.value = city.region
  localStorage.setItem(CITY_STORAGE_KEY, JSON.stringify(city))
}

function findCityOption(name: string, regionHint?: string): CityOption | null {
  const normalized = normalizeCityName(name)
  const byName = cityOptions.value.filter((city) => normalizeCityName(city.name) === normalized)

  if (!byName.length) {
    return null
  }

  if (!regionHint) {
    return byName[0] ?? null
  }

  const normalizedRegionHint = normalizeRegionName(regionHint)
  return byName.find((city) => normalizeRegionName(city.region) === normalizedRegionHint)
    ?? byName[0]
    ?? null
}

async function detectCityByIp(): Promise<StoredCity | null> {
  const controller = new AbortController()
  const timeoutId = window.setTimeout(() => controller.abort(), 1800)

  try {
    const response = await fetch('https://ipapi.co/json/', {
      signal: controller.signal,
    })

    if (!response.ok) {
      return null
    }

    const payload = await response.json() as { city?: string; region?: string }
    const rawCity = payload.city?.trim()
    if (!rawCity) {
      return null
    }

    const matched = findCityOption(rawCity, payload.region)
    if (matched) {
      return {
        name: matched.name,
        region: matched.region,
        district: matched.district,
        source: 'auto',
      }
    }

    return {
      name: rawCity,
      region: payload.region?.trim() || 'Не определен',
      district: 'Не определен',
      source: 'auto',
    }
  } catch (error) {
    console.warn('City auto-detect skipped:', error)
    return null
  } finally {
    window.clearTimeout(timeoutId)
  }
}

async function loadCitySelection() {
  await ensureCityCatalogLoaded()

  try {
    const storedRaw = localStorage.getItem(CITY_STORAGE_KEY)
    if (storedRaw) {
      const stored = JSON.parse(storedRaw) as StoredCity
      if (stored?.name) {
        applyCity(stored)
        return
      }
    }
  } catch (error) {
    console.warn('City storage parse failed:', error)
  }

  const autoCity = await detectCityByIp()
  if (autoCity) {
    applyCity(autoCity)
    return
  }

  applyCity({
    name: 'Москва',
    region: 'Москва',
    district: 'Центральный',
    source: 'default',
  })
}

function openCityPicker() {
  citySearch.value = ''
  selectedDistrict.value = citySelection.value.district
  selectedRegion.value = citySelection.value.region
  cityPickerOpen.value = true
}

function selectDistrict(value: string) {
  selectedDistrict.value = value
  if (!regionOptions.value.includes(selectedRegion.value)) {
    selectedRegion.value = ''
  }
}

function selectRegion(value: string) {
  selectedRegion.value = value
}

function selectCity(city: CityOption) {
  applyCity({
    name: city.name,
    region: city.region,
    district: city.district,
    source: 'manual',
  })
  cityPickerOpen.value = false
}

onMounted(async () => {
  captureFirstTouchAttribution()
  wishlistStore.hydrate()
  compareStore.hydrate()
  await Promise.all([authStore.loadMe(), loadNavigationMenu(), loadHeaderCategories(), loadCitySelection()])
  await cartStore.loadCart()
})

watch(
  () => route.query.auth,
  (value) => {
    if (value === '1' && !isAuthenticated.value) {
      authModalOpen.value = true
    }
  },
  { immediate: true },
)

async function onAuthenticated() {
  await cartStore.loadCart()
  await cartStore.loadOrderHistory()
}

async function closeAuthModal() {
  authModalOpen.value = false

  if (route.query.auth === '1') {
    const query = { ...route.query }
    delete query.auth

    await router.replace({ query })
  }
}

function normalizeSearchInput(value: string) {
  return value.trim()
}

async function submitHeaderSearch() {
  const query = normalizeSearchInput(headerSearchInput.value)

  await router.push({
    path: '/catalog',
    query: query ? { q: query } : {},
  })
}

async function loadNavigationMenu() {
  try {
    const payload = await fetchJson<NavigationResponse>('/api/navigation')

    navigationMenu.value = payload
  } catch (error) {
    console.error(error)
    navigationMenu.value = defaultNavigation
  }
}

async function loadHeaderCategories() {
  try {
    const payload = await fetchJson<HeaderCategory[]>('/api/categories')
    headerCategories.value = payload
  } catch (error) {
    console.error(error)
    headerCategories.value = []
  }
}

async function loadSuggestions(query: string) {
  const normalizedQuery = query.trim()

  if (normalizedQuery.length < 2) {
    searchSuggestions.value = []
    return
  }

  const requestId = ++suggestRequestId

  try {
    const response = await fetchJson<SearchSuggestResponse>(
      `/api/search/suggest?q=${encodeURIComponent(normalizedQuery)}`,
    )

    if (requestId !== suggestRequestId) {
      return
    }

    searchSuggestions.value = response.suggestions
  } catch (error) {
    console.error(error)
    searchSuggestions.value = []
  }
}

function onSearchFocus() {
  isSearchFocused.value = true
}

function onSearchBlur() {
  window.setTimeout(() => {
    isSearchFocused.value = false
  }, 120)
}

async function openSuggestion(item: SearchSuggestion) {
  searchSuggestions.value = []
  isSearchFocused.value = false
  headerSearchInput.value = item.name
  await router.push(toProductRoute(item))
}

watch(
  () => route.fullPath,
  () => {
    headerSearchInput.value = (route.query.q as string | undefined) ?? ''
    searchSuggestions.value = []
    if (categoryMenuRef.value?.open) {
      categoryMenuRef.value.open = false
    }
  },
)

watch(headerSearchInput, (value) => {
  if (suggestDebounce !== null) {
    window.clearTimeout(suggestDebounce)
  }

  suggestDebounce = window.setTimeout(() => {
    void loadSuggestions(value)
  }, 220)
})
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <button type="button" class="topbar__city" @click="openCityPicker">
        <span class="topbar__city-icon">📍</span>
        <span>{{ cityLabel }}</span>
      </button>
      <RouterLink to="/" class="brand">Shoria</RouterLink>
      <nav class="topbar__nav">
        <details v-if="headerCategories.length" ref="categoryMenuRef" class="topbar__categories">
          <summary class="topbar__categories-summary">Категории</summary>
          <div class="topbar__categories-dropdown">
            <div v-for="category in headerCategories" :key="`header-category-${category.id}`" class="topbar__category-group">
              <RouterLink :to="`/catalog/${category.slug}`" class="topbar__category-parent">
                {{ category.name }}
              </RouterLink>
              <div v-if="category.subcategories?.length" class="topbar__subcategory-list">
                <RouterLink
                  v-for="subcategory in category.subcategories"
                  :key="`header-subcategory-${subcategory.id}`"
                  :to="`/catalog/${category.slug}/${subcategory.slug}`"
                  class="topbar__subcategory-item"
                >
                  {{ subcategory.name }}
                </RouterLink>
              </div>
            </div>
          </div>
        </details>
        <template v-for="item in headerMenuItems" :key="`header-${item.id}`">
          <a
            v-if="item.is_external"
            :href="item.path"
            :target="item.open_in_new_tab ? '_blank' : undefined"
            :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
          >
            {{ item.label }}
          </a>
          <RouterLink v-else :to="item.path">{{ item.label }}</RouterLink>
        </template>
        <RouterLink v-if="isAuthenticated" to="/account">Профиль</RouterLink>
        <button v-else type="button" class="auth-btn" @click="authModalOpen = true">Вход / Регистрация</button>
      </nav>
      <form class="topbar__search" @submit.prevent="submitHeaderSearch">
        <input
          v-model="headerSearchInput"
          type="search"
          placeholder="Поиск по каталогу"
          @focus="onSearchFocus"
          @blur="onSearchBlur"
        />
        <button type="submit" aria-label="Искать">
          <span>🔍</span>
        </button>
        <div v-if="isSearchFocused && searchSuggestions.length" class="topbar__suggestions">
          <button
            v-for="item in searchSuggestions"
            :key="item.id"
            type="button"
            class="topbar__suggestion"
            @mousedown.prevent="openSuggestion(item)"
          >
            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
            <span class="topbar__suggestion-body">
              <strong>{{ item.name }}</strong>
              <small>{{ item.category?.name ?? 'Sneakers' }}</small>
            </span>
          </button>
        </div>
      </form>
      <div class="topbar__actions">
        <RouterLink class="icon-pill icon-pill--wishlist" to="/wishlist" aria-label="Избранное">
          <span class="icon-pill__icon">❤</span>
          <span class="icon-pill__count">{{ wishlistTotalItems }}</span>
        </RouterLink>
        <RouterLink class="icon-pill icon-pill--compare" to="/compare" aria-label="Сравнение">
          <span class="icon-pill__icon">⚖</span>
          <span class="icon-pill__count">{{ compareTotalItems }}</span>
        </RouterLink>
        <RouterLink class="icon-pill icon-pill--cart" to="/cart" aria-label="Корзина">
          <span class="icon-pill__icon">🛒</span>
          <span class="icon-pill__count">{{ totalItems }}</span>
        </RouterLink>
        <span v-if="isAuthenticated && user" class="user-pill">{{ user.name }}</span>
      </div>
    </header>
    <Teleport to="body">
      <div v-if="cityPickerOpen" class="city-modal" @click.self="cityPickerOpen = false">
        <section class="city-modal__card">
          <header class="city-modal__header">
            <h2>Выберите город</h2>
            <button type="button" class="city-modal__close" @click="cityPickerOpen = false">×</button>
          </header>

          <input
            v-model.trim="citySearch"
            type="search"
            placeholder="Найти город"
            class="city-modal__search"
          />

          <div class="city-modal__popular">
            <button
              v-for="item in popularCities"
              :key="`popular-city-${item.name}-${item.region}`"
              type="button"
              class="city-modal__chip"
              @click="selectCity(item)"
            >
              {{ item.name }}
            </button>
          </div>

          <div class="city-modal__columns">
            <div class="city-modal__column">
              <p class="city-modal__label">Округ</p>
              <button
                v-for="district in districtOptions"
                :key="`district-${district}`"
                type="button"
                class="city-modal__item"
                :class="{ 'city-modal__item--active': selectedDistrict === district }"
                @click="selectDistrict(district)"
              >
                {{ district }}
              </button>
            </div>
            <div class="city-modal__column">
              <p class="city-modal__label">Область</p>
              <button
                v-for="region in regionOptions"
                :key="`region-${region}`"
                type="button"
                class="city-modal__item"
                :class="{ 'city-modal__item--active': selectedRegion === region }"
                @click="selectRegion(region)"
              >
                {{ region }}
              </button>
            </div>
            <div class="city-modal__column city-modal__column--city">
              <p class="city-modal__label">Город</p>
              <button
                v-for="city in cityOptionsForList"
                :key="`city-${city.name}-${city.region}`"
                type="button"
                class="city-modal__item"
                :class="{ 'city-modal__item--active': citySelection.name === city.name && citySelection.region === city.region }"
                @click="selectCity(city)"
              >
                {{ city.name }}
              </button>
            </div>
          </div>
        </section>
      </div>
    </Teleport>
    <RouterView />
    <footer class="footer">
      <div class="footer__grid">
        <div>
          <p class="footer__brand">Shoria Store</p>
          <p class="footer__text">Шаблонный e-commerce проект для быстрого запуска витрины.</p>
        </div>
        <div>
          <p class="footer__title">Покупателям</p>
          <nav class="footer__links">
            <template v-for="item in footerCustomersMenuItems" :key="`footer-customers-${item.id}`">
              <a
                v-if="item.is_external"
                :href="item.path"
                :target="item.open_in_new_tab ? '_blank' : undefined"
                :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
              >
                {{ item.label }}
              </a>
              <RouterLink v-else :to="item.path">{{ item.label }}</RouterLink>
            </template>
          </nav>
        </div>
        <div>
          <p class="footer__title">Аккаунт</p>
          <nav class="footer__links">
            <template v-for="item in footerAccountMenuItems" :key="`footer-account-${item.id}`">
              <a
                v-if="item.is_external"
                :href="item.path"
                :target="item.open_in_new_tab ? '_blank' : undefined"
                :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
              >
                {{ item.label }}
              </a>
              <RouterLink v-else :to="item.path">{{ item.label }}</RouterLink>
            </template>
          </nav>
        </div>
        <div>
          <p class="footer__title">Контакты</p>
          <p class="footer__text">Email: support@shoria.store</p>
          <p class="footer__text">Телефон: +7 (900) 000-00-00</p>
        </div>
      </div>
      <p class="footer__copy">© {{ currentYear }} Shoria. Все права защищены.</p>
    </footer>
    <AuthModal :open="authModalOpen" @close="closeAuthModal" @authenticated="onAuthenticated" />
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
}

.topbar {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 16px 0 6px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  grid-template-areas:
    'city city city'
    'brand nav actions'
    'search search search';
  align-items: center;
  gap: 10px 14px;
}

.topbar__city {
  grid-area: city;
  justify-self: start;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  padding: 0;
  background: transparent;
  color: #3f4860;
  font: inherit;
  cursor: pointer;
}

.topbar__city:hover {
  color: var(--color-accent, #bf4b08);
}

.topbar__city-icon {
  font-size: 14px;
}

.brand {
  grid-area: brand;
  color: #1f2233;
  text-decoration: none;
  font-family: var(--font-display);
  font-size: 42px;
  line-height: 1;
}

.topbar__nav {
  grid-area: nav;
  display: flex;
  align-items: center;
  justify-self: center;
  gap: 8px;
  flex-wrap: wrap;
}

.topbar__nav a {
  padding: 8px 12px;
  border-radius: 999px;
  color: #1f2233;
  text-decoration: none;
}

.topbar__categories {
  position: relative;
}

.topbar__categories-summary {
  list-style: none;
  padding: 8px 12px;
  border-radius: 999px;
  color: #1f2233;
  cursor: pointer;
  user-select: none;
}

.topbar__categories-summary::-webkit-details-marker {
  display: none;
}

.topbar__categories[open] .topbar__categories-summary {
  background: #1f2233;
  color: #fff;
}

.topbar__categories-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 20;
  min-width: min(760px, 86vw);
  max-height: min(68vh, 520px);
  overflow: auto;
  padding: 14px;
  border-radius: 16px;
  border: 1px solid #e0ddd6;
  background: #fff;
  box-shadow: 0 24px 44px rgb(16 24 40 / 16%);
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr));
  gap: 12px;
}

.topbar__category-group {
  display: grid;
  gap: 7px;
}

.topbar__category-parent {
  padding: 6px 8px;
  border-radius: 10px;
  font-weight: 700;
  text-decoration: none;
  color: #1f2233;
}

.topbar__category-parent:hover {
  background: #f6f3ed;
}

.topbar__subcategory-list {
  display: grid;
  gap: 4px;
}

.topbar__subcategory-item {
  padding: 5px 8px;
  border-radius: 9px;
  color: #4b5773;
  text-decoration: none;
  font-size: 14px;
}

.topbar__subcategory-item:hover {
  background: #fff2e8;
  color: #1f2233;
}

.auth-btn {
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #e0ddd6;
  background: #fff;
  color: #1f2233;
  font: inherit;
  cursor: pointer;
}

.topbar__nav a.router-link-active {
  background: #1f2233;
  color: #fff;
}

.topbar__actions {
  grid-area: actions;
  display: flex;
  align-items: center;
  justify-self: end;
  gap: 8px;
}

.topbar__search {
  grid-area: search;
  position: relative;
  width: min(760px, 100%);
  justify-self: center;
}

.topbar__search input {
  width: 100%;
  padding: 9px 44px 9px 11px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  font: inherit;
}

.topbar__search > button {
  position: absolute;
  top: 50%;
  right: 6px;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: #f2f4f8;
  display: grid;
  place-items: center;
  font: inherit;
  cursor: pointer;
}

.topbar__suggestions {
  position: absolute;
  z-index: 12;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  padding: 8px;
  border: 1px solid #d6d3cc;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 32px rgb(16 24 40 / 14%);
}

.topbar__suggestion {
  position: static;
  transform: none;
  height: auto;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  gap: 10px;
  width: 100%;
  padding: 8px;
  border: none;
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  overflow: hidden;
}

.topbar__suggestion:hover {
  background: #fff2e8;
}

.topbar__suggestion img {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  object-fit: cover;
}

.topbar__suggestion-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.topbar__suggestion-body strong {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar__suggestion-body small {
  color: #6f7b95;
}

.icon-pill {
  position: relative;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid #e0ddd6;
  background: #fff;
  color: #1f2233;
  text-decoration: none;
  display: grid;
  place-items: center;
}

.icon-pill__icon {
  font-size: 17px;
  line-height: 1;
}

.icon-pill__count {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  padding: 0 5px;
  display: grid;
  place-items: center;
  background: #1f2233;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}

.icon-pill--wishlist {
  background: #fff7ef;
  border: 1px solid #f0dbc3;
  color: #8a3d0b;
}

.icon-pill--compare {
  background: #eef2fb;
  border: 1px solid #d7deee;
  color: #3a4d73;
}

.user-pill {
  padding: 8px 12px;
  border-radius: 999px;
  background: #fff7ef;
  border: 1px solid #f0dbc3;
  color: #8a3d0b;
  font-size: 14px;
}

.footer {
  width: min(1240px, 92vw);
  margin: 30px auto 24px;
  padding: 22px 20px 16px;
  border: 1px solid #e9dfd0;
  border-radius: 18px;
  background: linear-gradient(145deg, #fff9f1, #fff5e9);
}

.footer__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.footer__brand {
  font-family: var(--font-display);
  font-size: 28px;
  line-height: 1;
}

.footer__title {
  font-weight: 700;
}

.footer__links {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.footer__links a {
  color: #1f2233;
  text-decoration: none;
}

.footer__link-btn {
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  color: #1f2233;
  font: inherit;
  cursor: pointer;
}

.footer__text {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.footer__copy {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #ebdfcf;
  color: #6f7b95;
  font-size: 14px;
}

.city-modal {
  position: fixed;
  inset: 0;
  z-index: 60;
  background: rgb(17 24 39 / 52%);
  display: grid;
  place-items: center;
  padding: 20px;
}

.city-modal__card {
  width: min(980px, 94vw);
  max-height: min(78vh, 760px);
  overflow: hidden;
  border-radius: 20px;
  background: #fff;
  border: 1px solid #e6ddd0;
  box-shadow: 0 24px 54px rgb(16 24 40 / 28%);
  display: grid;
  gap: 14px;
  padding: 24px;
}

.city-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.city-modal__header h2 {
  margin: 0;
  font-size: 44px;
  line-height: 1;
  font-family: var(--font-display);
}

.city-modal__close {
  border: 0;
  background: transparent;
  color: #7c8599;
  font-size: 44px;
  line-height: 1;
  cursor: pointer;
}

.city-modal__search {
  width: 100%;
  min-height: 50px;
  border: 1px solid #d6d3cc;
  border-radius: 12px;
  padding: 0 14px;
  font: inherit;
}

.city-modal__popular {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.city-modal__chip {
  border: 1px solid #d9dbe2;
  border-radius: 999px;
  padding: 6px 12px;
  background: #f4f6f9;
  color: #1f2233;
  font: inherit;
  cursor: pointer;
}

.city-modal__columns {
  display: grid;
  grid-template-columns: 1fr 1.25fr 1fr;
  gap: 16px;
  min-height: 340px;
}

.city-modal__column {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
  padding-right: 6px;
}

.city-modal__label {
  position: sticky;
  top: 0;
  margin: 0;
  padding: 6px 0;
  background: #fff;
  font-weight: 700;
  color: #364057;
}

.city-modal__item {
  border: 0;
  border-radius: 10px;
  padding: 8px 10px;
  text-align: left;
  color: #1f2233;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.city-modal__item:hover {
  background: #f8f3ec;
}

.city-modal__item--active {
  background: #f3ede4;
  font-weight: 700;
}

@media (max-width: 1100px) {
  .topbar {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'city city'
      'brand actions'
      'nav nav'
      'search search';
  }

  .topbar__nav {
    justify-self: start;
  }
}

@media (max-width: 760px) {
  .topbar {
    gap: 8px 10px;
  }

  .brand {
    width: auto;
  }

  .topbar__nav {
    justify-self: start;
    width: 100%;
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 4px;
  }

  .topbar__search {
    width: 100%;
    justify-self: stretch;
  }

  .topbar__categories-dropdown {
    min-width: min(560px, 92vw);
    grid-template-columns: repeat(2, minmax(140px, 1fr));
  }

  .city-modal__header h2 {
    font-size: 34px;
  }

  .city-modal__columns {
    grid-template-columns: 1fr;
    min-height: auto;
  }

  .city-modal__column {
    max-height: 210px;
    padding-right: 0;
  }

  .footer__grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 560px) {
  .footer__grid {
    grid-template-columns: 1fr;
  }
}
</style>
