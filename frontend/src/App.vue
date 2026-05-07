<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { NConfigProvider, dateRuRU, ruRU, type GlobalThemeOverrides } from 'naive-ui'
import AppSkeleton from '@/components/AppSkeleton.vue'
import AuthModal from '@/components/AuthModal.vue'
import OneClickCheckoutModal from '@/components/OneClickCheckoutModal.vue'
import ProductQuickViewModal from '@/components/ProductQuickViewModal.vue'
import QuickReviewPrompt from '@/components/QuickReviewPrompt.vue'
import { buttonVariants } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { naiveThemeOverrides } from '@/theme/naive-theme'
import { cn } from '@/lib/utils'
import {
  ChevronDown,
  GitCompare,
  Heart,
  LayoutGrid,
  Menu,
  Phone,
  Search as SearchIcon,
  ShoppingCart,
  X,
} from 'lucide-vue-next'
import { Toaster, toast } from '@/lib/toast'
import 'vue-sonner/style.css'
import { closeProductQuickView } from '@/lib/product-quick-view'
import { captureFirstTouchAttribution } from '@/lib/attribution'
import { fetchJson } from '@/lib/api'
import {
  defaultSiteSettingsPayload,
  filterNavItemsByFeatureFlags,
  normalizeSiteSettingsPayload,
  siteSettingsInjectionKey,
  type SiteSettingsPayload,
} from '@/lib/site-settings'
import { applyStoreTheme } from '@/lib/site-theme'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { resolveCategoryMenuIcon } from '@/lib/category-menu-icons'
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
const { isAuthenticated } = storeToRefs(authStore)
const route = useRoute()
const router = useRouter()

const authModalOpen = ref(false)
const isHeaderLoading = ref(true)
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
  icon_url?: string | null
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
/** Верхняя полоса: без «Главная» и без отдельного «Каталог» — он в кнопке */
const stripNavItems = computed(() =>
  filterNavItemsByFeatureFlags(
    headerMenuItems.value.filter(
      (item) =>
        item.label.trim() !== 'Главная'
        && item.path !== '/catalog'
        && item.path !== '/',
    ),
    siteSettings.value.feature_flags,
  ),
)
const footerCustomersMenuItems = computed(() =>
  filterNavItemsByFeatureFlags(navigationMenu.value.footer.customers, siteSettings.value.feature_flags),
)
const footerAccountMenuItems = computed(() =>
  filterNavItemsByFeatureFlags(navigationMenu.value.footer.account, siteSettings.value.feature_flags),
)

const siteSettings = ref<SiteSettingsPayload>(defaultSiteSettingsPayload())

provide(siteSettingsInjectionKey, siteSettings)

const footerCopyrightLine = computed(() => {
  const custom = siteSettings.value.footer_legal_line?.trim()
  if (custom) {
    return custom
  }
  return `© ${currentYear} ${siteSettings.value.logo_text}. Все права защищены.`
})

const footerToneClass = computed(() => {
  const tone = siteSettings.value.theme.footer.tone
  if (tone === 'dark') {
    return 'footer--tone-dark'
  }
  if (tone === 'light') {
    return 'footer--tone-light'
  }
  return 'footer--tone-muted'
})

const naiveThemeMerged = computed<GlobalThemeOverrides>(() => {
  const t = siteSettings.value.theme.general
  const r = t.button_radius_px
  const base = naiveThemeOverrides
  return {
    ...base,
    common: {
      ...base.common!,
      primaryColor: t.primary_hex,
      primaryColorHover: t.primary_hex,
      primaryColorPressed: t.primary_hex,
      borderRadius: `${r}px`,
      borderRadiusSmall: `${Math.max(4, r - 2)}px`,
      fontSize: `${t.base_font_size_px}px`,
      fontSizeMedium: `${t.base_font_size_px}px`,
    },
    Button: {
      ...base.Button!,
      borderRadiusMedium: `${r}px`,
      borderRadiusLarge: `${Math.min(24, r + 2)}px`,
    },
    Input: {
      ...base.Input!,
      borderRadius: `${r}px`,
    },
    InternalSelection: {
      ...base.InternalSelection!,
      borderRadius: `${r}px`,
    },
    Progress: {
      ...base.Progress!,
      fillColor: t.primary_hex,
    },
  }
})
const headerCategories = ref<HeaderCategory[]>([])
const megaActiveCategory = ref<HeaderCategory | null>(null)
const mobileDrawerOpen = ref(false)

const catalogTriggerClass = computed(() =>
  cn(
    buttonVariants({ variant: 'default', size: 'default' }),
    'site-header__catalog-btn',
  ),
)

function syncMegaActiveFromFirst() {
  megaActiveCategory.value = headerCategories.value[0] ?? null
}

function onCatalogDetailsToggle(event: Event) {
  const details = event.currentTarget as HTMLDetailsElement
  if (details.open && headerCategories.value.length) {
    syncMegaActiveFromFirst()
  }
}

function setMegaActiveCategory(category: HeaderCategory) {
  megaActiveCategory.value = category
}

function closeCatalogMenu() {
  if (categoryMenuRef.value) {
    categoryMenuRef.value.open = false
  }
}

function onDocumentPointerDownCloseCatalog(event: PointerEvent) {
  const menu = categoryMenuRef.value
  if (!menu?.open) {
    return
  }
  const t = event.target
  if (t instanceof Node && menu.contains(t)) {
    return
  }
  menu.open = false
}

function onDocumentKeydownCloseCatalog(event: KeyboardEvent) {
  if (event.key !== 'Escape') {
    return
  }
  if (!categoryMenuRef.value?.open) {
    return
  }
  closeCatalogMenu()
}

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
  document.addEventListener('pointerdown', onDocumentPointerDownCloseCatalog, true)
  document.addEventListener('keydown', onDocumentKeydownCloseCatalog)

  captureFirstTouchAttribution()
  wishlistStore.hydrate()
  compareStore.hydrate()

  try {
    await Promise.all([
      authStore.loadMe(),
      loadNavigationMenu(),
      loadHeaderCategories(),
      loadCitySelection(),
      loadSiteSettings(),
    ])
    await cartStore.loadCart()
  } finally {
    isHeaderLoading.value = false
  }
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
  toast.success('Вы вошли в аккаунт')
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

async function loadSiteSettings() {
  try {
    const raw = await fetchJson<unknown>('/api/site-settings')
    siteSettings.value = normalizeSiteSettingsPayload(raw)
  } catch (error) {
    console.warn('Site settings unavailable, using defaults', error)
    siteSettings.value = defaultSiteSettingsPayload()
  }
}

watch(
  siteSettings,
  () => {
    applyStoreTheme(siteSettings.value.theme)
  },
  { deep: true, immediate: true },
)

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
    mobileDrawerOpen.value = false
    closeProductQuickView()
    if (categoryMenuRef.value?.open) {
      categoryMenuRef.value.open = false
    }
  },
)

watch(
  headerCategories,
  (list) => {
    if (list.length && (!megaActiveCategory.value || !list.some((c) => c.id === megaActiveCategory.value?.id))) {
      syncMegaActiveFromFirst()
    }
  },
  { deep: true },
)

watch(headerSearchInput, (value) => {
  if (suggestDebounce !== null) {
    window.clearTimeout(suggestDebounce)
  }

  suggestDebounce = window.setTimeout(() => {
    void loadSuggestions(value)
  }, 220)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentPointerDownCloseCatalog, true)
  document.removeEventListener('keydown', onDocumentKeydownCloseCatalog)
  if (suggestDebounce !== null) {
    window.clearTimeout(suggestDebounce)
  }
})
</script>

<template>
  <NConfigProvider :theme-overrides="naiveThemeMerged" :locale="ruRU" :date-locale="dateRuRU">
  <div class="app-shell">
    <header
      class="site-header"
      :class="[
        { 'site-header--static': !siteSettings.theme.header.sticky },
        `site-header--variant-${siteSettings.theme.header.variant}`,
      ]"
    >
      <template v-if="!isHeaderLoading">
        <div class="site-header__strip">
          <div class="site-header__inner site-header__inner--strip">
            <button type="button" class="site-header__city" @click="openCityPicker">
              <span class="site-header__city-label">{{ cityLabel }}</span>
              <ChevronDown class="site-header__city-chevron" :size="16" aria-hidden="true" />
            </button>
            <nav class="site-header__strip-nav" aria-label="Сервисное меню">
              <template v-for="item in stripNavItems" :key="`strip-${item.id}`">
                <a
                  v-if="item.is_external"
                  :href="item.path"
                  :target="item.open_in_new_tab ? '_blank' : undefined"
                  :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
                  class="site-header__strip-link"
                >
                  {{ item.label }}
                </a>
                <RouterLink v-else :to="item.path" class="site-header__strip-link">
                  {{ item.label }}
                </RouterLink>
              </template>
            </nav>
            <p class="site-header__hours">{{ siteSettings.work_hours_short }}</p>
          </div>
        </div>

        <div class="site-header__main">
          <div class="site-header__inner site-header__inner--main">
            <div class="site-header__lead-group">
              <div class="site-header__start-cluster">
              <button
                type="button"
                class="site-header__icon-btn site-header__icon-btn--menu"
                aria-label="Открыть меню"
                @click="mobileDrawerOpen = true"
              >
                <Menu :size="22" />
              </button>

              <RouterLink
                v-if="siteSettings.theme.header.variant !== 'centered'"
                to="/"
                class="site-header__logo"
              >
                <img
                  v-if="siteSettings.logo_image_url"
                  :src="siteSettings.logo_image_url"
                  :alt="siteSettings.logo_text"
                  class="site-header__logo-img"
                  loading="eager"
                  decoding="async"
                />
                <span v-else>{{ siteSettings.logo_text }}</span>
              </RouterLink>

              <details
                v-if="headerCategories.length"
                ref="categoryMenuRef"
                class="site-header__catalog site-header__catalog--desktop"
                @toggle="onCatalogDetailsToggle"
              >
                <summary :class="catalogTriggerClass">
                  <span>Каталог</span>
                  <ChevronDown :size="18" class="site-header__catalog-chevron" aria-hidden="true" />
                </summary>
                <div class="site-header__mega" @click.stop>
                  <div class="site-header__mega-inner">
                    <div class="site-header__mega-layout">
                      <ul class="site-header__mega-parents">
                        <li v-for="category in headerCategories" :key="`mega-p-${category.id}`">
                          <RouterLink
                            :to="`/catalog/${category.slug}`"
                            class="site-header__mega-parent"
                            :class="{ 'site-header__mega-parent--active': megaActiveCategory?.id === category.id }"
                            @mouseenter="setMegaActiveCategory(category)"
                            @focus="setMegaActiveCategory(category)"
                            @click="closeCatalogMenu"
                         >
                            <img
                              v-if="category.icon_url"
                              :src="resolveImageSrc(category.icon_url)"
                              alt=""
                              class="category-menu-icon category-menu-icon--img"
                              width="18"
                              height="18"
                              loading="lazy"
                              decoding="async"
                              @error="applyImageFallback"
                            />
                            <component
                              :is="resolveCategoryMenuIcon(category.slug)"
                              v-else
                              class="category-menu-icon"
                              :size="18"
                              :stroke-width="1.75"
                              aria-hidden="true"
                            />
                            <span class="site-header__mega-parent-text">{{ category.name }}</span>
                          </RouterLink>
                        </li>
                      </ul>
                      <div class="site-header__mega-right">
                        <div v-if="megaActiveCategory?.subcategories?.length" class="site-header__mega-grid">
                          <RouterLink
                            v-for="sub in megaActiveCategory.subcategories"
                            :key="`mega-s-${sub.id}`"
                            :to="`/catalog/${megaActiveCategory!.slug}/${sub.slug}`"
                            class="site-header__mega-sub"
                            @click="closeCatalogMenu"
                          >
                            {{ sub.name }}
                          </RouterLink>
                        </div>
                        <div
                          v-else-if="megaActiveCategory"
                          class="site-header__mega-empty"
                          role="status"
                          aria-live="polite"
                        >
                          <div class="site-header__mega-empty-icon-wrap" aria-hidden="true">
                            <LayoutGrid :size="26" class="site-header__mega-empty-icon" stroke-width="1.75" />
                          </div>
                          <p class="site-header__mega-empty-title">Подразделы скоро появятся</p>
                          <p class="site-header__mega-empty-text">
                            А пока можно открыть весь каталог этого направления — там уже есть товары.
                          </p>
                          <RouterLink
                            :to="`/catalog/${megaActiveCategory.slug}`"
                            class="site-header__mega-empty-cta"
                            @click="closeCatalogMenu"
                          >
                            Перейти в «{{ megaActiveCategory.name }}»
                          </RouterLink>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </details>
              <RouterLink
                v-else
                to="/catalog"
                :class="catalogTriggerClass"
                class="site-header__catalog-fallback site-header__catalog--desktop"
              >
                Каталог
              </RouterLink>
              </div>

              <RouterLink
                v-if="siteSettings.theme.header.variant === 'centered'"
                to="/"
                class="site-header__logo"
              >
                <img
                  v-if="siteSettings.logo_image_url"
                  :src="siteSettings.logo_image_url"
                  :alt="siteSettings.logo_text"
                  class="site-header__logo-img"
                  loading="eager"
                  decoding="async"
                />
                <span v-else>{{ siteSettings.logo_text }}</span>
              </RouterLink>
            </div>

            <form class="site-header__search" @submit.prevent="submitHeaderSearch">
              <input
                id="site-header-search"
                v-model="headerSearchInput"
                type="search"
                name="q"
                class="site-header__search-input"
                placeholder="Поиск по каталогу"
                autocomplete="off"
                autocorrect="off"
                spellcheck="false"
                enterkeyhint="search"
                @focus="onSearchFocus"
                @blur="onSearchBlur"
              />
              <button type="submit" class="site-header__search-submit" aria-label="Искать">
                <SearchIcon :size="18" />
              </button>
              <div v-if="isSearchFocused && searchSuggestions.length" class="site-header__suggestions">
                <button
                  v-for="item in searchSuggestions"
                  :key="item.id"
                  type="button"
                  class="site-header__suggestion"
                  @mousedown.prevent="openSuggestion(item)"
                >
                  <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
                  <span class="site-header__suggestion-body">
                    <strong>{{ item.name }}</strong>
                    <small>{{ item.category?.name ?? 'Каталог' }}</small>
                  </span>
                </button>
              </div>
            </form>

            <div class="site-header__aside">
              <a :href="`tel:${siteSettings.phone_tel}`" class="site-header__phone-block">
                <Phone class="site-header__phone-icon" :size="18" aria-hidden="true" />
                <span class="site-header__phone-text">{{ siteSettings.phone_display }}</span>
              </a>
              <div class="site-header__actions">
                <RouterLink
                  v-if="siteSettings.feature_flags.wishlist"
                  class="site-header__tool"
                  to="/wishlist"
                  aria-label="Избранное"
                >
                  <Heart :size="20" />
                  <span class="site-header__tool-count">{{ wishlistTotalItems }}</span>
                </RouterLink>
                <RouterLink
                  v-if="siteSettings.feature_flags.product_compare"
                  class="site-header__tool"
                  to="/compare"
                  aria-label="Сравнение"
                >
                  <GitCompare :size="20" />
                  <span class="site-header__tool-count">{{ compareTotalItems }}</span>
                </RouterLink>
                <RouterLink class="site-header__tool site-header__tool--cart" to="/cart" aria-label="Корзина">
                  <ShoppingCart :size="20" />
                  <span class="site-header__tool-count">{{ totalItems }}</span>
                </RouterLink>
              </div>
              <button
                v-if="!isAuthenticated"
                type="button"
                class="site-header__auth"
                @click="authModalOpen = true"
              >
                Войти
              </button>
              <RouterLink v-else to="/account" class="site-header__account">
                Профиль
              </RouterLink>
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="site-header__strip site-header__strip--loading">
          <div class="site-header__inner site-header__inner--strip">
            <AppSkeleton inline width="120px" height="20px" radius="8px" />
            <AppSkeleton inline width="60%" height="20px" radius="8px" />
            <AppSkeleton inline width="140px" height="20px" radius="8px" />
          </div>
        </div>
        <div class="site-header__main">
          <div class="site-header__inner site-header__inner--main">
            <div class="site-header__lead-group">
              <AppSkeleton inline width="120px" height="44px" radius="10px" />
              <AppSkeleton inline width="96px" height="44px" radius="10px" />
            </div>
            <AppSkeleton
              class="site-header__search-skeleton"
              inline
              width="100%"
              height="44px"
              radius="12px"
            />
            <div class="site-header__aside site-header__aside--loading">
              <AppSkeleton inline width="140px" height="42px" radius="12px" />
              <AppSkeleton inline width="132px" height="42px" radius="12px" />
            </div>
          </div>
        </div>
      </template>
    </header>

    <Teleport to="body">
      <div v-if="mobileDrawerOpen" class="mobile-drawer-overlay" @click.self="mobileDrawerOpen = false">
        <aside class="mobile-drawer" aria-label="Меню сайта">
          <div class="mobile-drawer__head">
            <span class="mobile-drawer__title">Меню</span>
            <button type="button" class="mobile-drawer__close" aria-label="Закрыть" @click="mobileDrawerOpen = false">
              <X :size="22" />
            </button>
          </div>
          <button type="button" class="mobile-drawer__city" @click="openCityPicker(); mobileDrawerOpen = false">
            {{ cityLabel }}
            <ChevronDown :size="16" />
          </button>
          <nav class="mobile-drawer__nav">
            <RouterLink to="/" class="mobile-drawer__link" @click="mobileDrawerOpen = false">Главная</RouterLink>
            <RouterLink to="/catalog" class="mobile-drawer__link" @click="mobileDrawerOpen = false">Каталог</RouterLink>
            <template v-for="item in stripNavItems" :key="`m-${item.id}`">
              <a
                v-if="item.is_external"
                :href="item.path"
                :target="item.open_in_new_tab ? '_blank' : undefined"
                :rel="item.open_in_new_tab ? 'noopener noreferrer' : undefined"
                class="mobile-drawer__link"
                @click="mobileDrawerOpen = false"
              >
                {{ item.label }}
              </a>
              <RouterLink v-else :to="item.path" class="mobile-drawer__link" @click="mobileDrawerOpen = false">
                {{ item.label }}
              </RouterLink>
            </template>
          </nav>
          <div v-if="headerCategories.length" class="mobile-drawer__acc">
            <p class="mobile-drawer__acc-title">Категории</p>
            <div v-for="category in headerCategories" :key="`mc-${category.id}`" class="mobile-drawer__group">
              <RouterLink
                :to="`/catalog/${category.slug}`"
                class="mobile-drawer__parent"
                @click="mobileDrawerOpen = false"
              >
                <img
                  v-if="category.icon_url"
                  :src="resolveImageSrc(category.icon_url)"
                  alt=""
                  class="category-menu-icon category-menu-icon--img"
                  width="18"
                  height="18"
                  loading="lazy"
                  decoding="async"
                  @error="applyImageFallback"
                />
                <component
                  :is="resolveCategoryMenuIcon(category.slug)"
                  v-else
                  class="category-menu-icon"
                  :size="18"
                  :stroke-width="1.75"
                  aria-hidden="true"
                />
                <span>{{ category.name }}</span>
              </RouterLink>
              <div v-if="category.subcategories?.length" class="mobile-drawer__subs">
                <RouterLink
                  v-for="sub in category.subcategories"
                  :key="`mcs-${sub.id}`"
                  :to="`/catalog/${category.slug}/${sub.slug}`"
                  class="mobile-drawer__sub"
                  @click="mobileDrawerOpen = false"
                >
                  {{ sub.name }}
                </RouterLink>
              </div>
            </div>
          </div>
          <div class="mobile-drawer__auth-block">
            <button
              v-if="!isAuthenticated"
              type="button"
              class="mobile-drawer__link mobile-drawer__link--accent"
              @click="authModalOpen = true; mobileDrawerOpen = false"
            >
              Войти
            </button>
            <RouterLink
              v-else
              to="/account"
              class="mobile-drawer__link mobile-drawer__link--accent"
              @click="mobileDrawerOpen = false"
            >
              Личный кабинет
            </RouterLink>
          </div>
        </aside>
      </div>
    </Teleport>
      <Teleport to="body">
        <div v-if="cityPickerOpen" class="city-modal" @click.self="cityPickerOpen = false">
          <section class="city-modal__card">
          <header class="city-modal__header">
            <h2>Выберите город</h2>
            <button type="button" class="city-modal__close" @click="cityPickerOpen = false">×</button>
          </header>

          <Input
            :model-value="citySearch"
            type="search"
            placeholder="Найти город"
            class="city-modal__search"
            @update:model-value="(v) => (citySearch = typeof v === 'string' ? v.trim() : String(v ?? ''))"
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
      <footer class="footer" :class="footerToneClass">
      <div class="footer__grid">
        <div>
          <p class="footer__brand">{{ siteSettings.logo_text }}</p>
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
          <p v-if="siteSettings.support_email" class="footer__text">
            Email:
            <a :href="`mailto:${siteSettings.support_email}`">{{ siteSettings.support_email }}</a>
          </p>
          <p class="footer__text">
            Телефон:
            <a :href="`tel:${siteSettings.phone_tel}`">{{ siteSettings.phone_display }}</a>
          </p>
        </div>
      </div>
      <p class="footer__copy">{{ footerCopyrightLine }}</p>
      </footer>
    <QuickReviewPrompt />
    <AuthModal :open="authModalOpen" @close="closeAuthModal" @authenticated="onAuthenticated" />
    <OneClickCheckoutModal />
    <ProductQuickViewModal />
    <Toaster position="bottom-right" :rich-colors="true" close-button />
  </div>
  </NConfigProvider>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
}

.site-header {
  position: sticky;
  top: 0;
  z-index: 50;
  background: var(--background);
  border-bottom: 1px solid var(--header-border, #e5e7eb);
}

.site-header--static {
  position: relative;
  top: auto;
  z-index: auto;
}

.site-header__strip {
  background: var(--header-strip-bg, #f3f4f6);
  border-bottom: 1px solid var(--header-border, #e5e7eb);
}

.site-header__strip--loading .site-header__inner--strip {
  justify-content: space-between;
  gap: 12px;
}

.site-header__inner {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
}

.site-header__inner--strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px 16px;
  padding: 8px 0;
  font-size: 13px;
}

.site-header__city {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 0;
  padding: 4px 6px;
  margin: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--foreground);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.site-header__city:hover {
  background: rgb(0 0 0 / 5%);
}

.site-header__city-chevron {
  opacity: 0.55;
}

.site-header__strip-nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 4px 8px;
  flex: 1;
  min-width: 0;
}

.site-header__strip-link {
  padding: 4px 8px;
  border-radius: 8px;
  color: var(--muted-foreground);
  text-decoration: none;
  font-weight: 500;
}

.site-header__strip-link:hover {
  color: var(--primary);
  background: rgb(37 99 235 / 8%);
}

.site-header__hours {
  margin: 0;
  color: var(--muted-foreground);
  white-space: nowrap;
}

.site-header__main {
  background: var(--background);
}

.site-header__inner--main {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px 20px;
  padding: 12px 0 14px;
}

.site-header__lead-group {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.site-header__start-cluster {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

@media (min-width: 1024px) {
  .site-header--variant-wide_search .site-header__inner--main {
    grid-template-columns: auto minmax(320px, 2.35fr) auto;
  }

  .site-header--variant-centered .site-header__lead-group {
    display: contents;
  }

  .site-header--variant-centered .site-header__inner--main {
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    grid-template-rows: auto auto;
    grid-template-areas:
      'cluster logo aside'
      'search search search';
    row-gap: 12px;
  }

  .site-header--variant-centered .site-header__start-cluster {
    grid-area: cluster;
    justify-self: start;
  }

  .site-header--variant-centered .site-header__logo {
    grid-area: logo;
    justify-self: center;
  }

  .site-header--variant-centered .site-header__aside {
    grid-area: aside;
    justify-self: end;
  }

  .site-header--variant-centered .site-header__search {
    grid-area: search;
    max-width: 720px;
    width: 100%;
    justify-self: center;
  }
}

.site-header__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--background);
  color: var(--foreground);
  cursor: pointer;
}

.site-header__icon-btn:hover {
  background: var(--muted);
}

.site-header__icon-btn--menu {
  display: none;
}

.site-header__logo {
  font-family: var(--font-display);
  font-size: clamp(32px, 5vw, 40px);
  line-height: 1;
  color: var(--foreground);
  text-decoration: none;
  letter-spacing: 0.02em;
}

.site-header__logo-img {
  display: block;
  max-height: 40px;
  width: auto;
}

.site-header__catalog {
  position: relative;
}

.site-header__catalog summary {
  list-style: none;
}

.site-header__catalog summary::-webkit-details-marker {
  display: none;
}

.site-header__catalog-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
  -webkit-user-select: none;
  -webkit-tap-highlight-color: transparent;
}

/* Вся площадь кнопки реагирует на hover (события идут на <summary>) */
.site-header__catalog summary.site-header__catalog-btn > * {
  pointer-events: none;
}

/* Явный hover на весь прямоугольник (дублируем токен default-кнопки) */
.site-header__catalog summary.site-header__catalog-btn:hover {
  background-color: color-mix(in srgb, var(--primary), #000 10%);
}

.site-header__catalog-fallback.site-header__catalog-btn:hover {
  background-color: color-mix(in srgb, var(--primary), #000 10%);
}

.site-header__catalog[open] .site-header__catalog-chevron {
  transform: rotate(180deg);
}

.site-header__catalog-chevron {
  transition: transform 0.2s ease;
  opacity: 0.9;
}

.site-header__mega {
  position: absolute;
  left: 0;
  top: calc(100% + 6px);
  z-index: 45;
  width: min(920px, calc(100vw - 32px));
}

.site-header__mega-inner {
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--popover);
  box-shadow: 0 20px 50px rgb(15 23 42 / 14%);
  overflow: hidden;
}

.site-header__mega-layout {
  display: grid;
  grid-template-columns: minmax(180px, 240px) 1fr;
  grid-template-rows: minmax(0, 1fr);
  min-height: 220px;
  max-height: min(70vh, min(480px, calc(100dvh - 120px)));
  overflow: hidden;
}

.site-header__mega-parents {
  margin: 0;
  padding: 10px 0;
  list-style: none;
  border-right: 1px solid var(--border);
  background: var(--muted);
  align-self: stretch;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: stable;
}

.site-header__mega-parent {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px 10px 16px;
  border: 0;
  background: transparent;
  text-align: left;
  font: inherit;
  font-weight: 600;
  font-size: 14px;
  color: var(--foreground);
  text-decoration: none;
  cursor: pointer;
  outline: none;
  transition:
    background-color 0.2s ease,
    color 0.2s ease,
    box-shadow 0.2s ease;
}

.site-header__mega-parent:hover,
.site-header__mega-parent--active {
  background: color-mix(in srgb, var(--primary), transparent 92%);
  color: var(--foreground);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary), transparent 78%);
}

.site-header__mega-parent-text {
  min-width: 0;
  flex: 1;
}

.category-menu-icon {
  flex-shrink: 0;
  color: var(--muted-foreground);
  opacity: 0.9;
}

.site-header__mega-parent:hover .category-menu-icon,
.site-header__mega-parent--active .category-menu-icon {
  color: var(--foreground);
  opacity: 1;
}

.category-menu-icon--img {
  display: block;
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  object-fit: contain;
}

.site-header__mega-parent:hover .category-menu-icon--img,
.site-header__mega-parent--active .category-menu-icon--img {
  opacity: 1;
}

.site-header__mega-parent:focus-visible {
  background: color-mix(in srgb, var(--primary), transparent 92%);
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--primary), transparent 78%),
    0 0 0 2px var(--background),
    0 0 0 4px color-mix(in srgb, var(--primary), transparent 45%);
}

.site-header__mega-right {
  display: flex;
  flex-direction: column;
  padding: 14px 16px;
  align-self: stretch;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: stable;
}

.site-header__mega-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 6px 10px;
}

.site-header__mega-sub {
  padding: 8px 10px;
  font-size: 14px;
  color: var(--muted-foreground);
  text-decoration: none;
}

.site-header__mega-sub:hover {
  background: var(--muted);
  color: var(--foreground);
}

.site-header__mega-empty {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: min(200px, 42vh);
  margin: 0;
  padding: 12px 8px 8px;
  text-align: center;
}

.site-header__mega-empty-icon-wrap {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-bottom: 4px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--primary), transparent 92%);
  color: var(--primary);
}

.site-header__mega-empty-icon {
  opacity: 0.92;
}

.site-header__mega-empty-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--foreground);
  line-height: 1.3;
}

.site-header__mega-empty-text {
  margin: 0;
  max-width: 28ch;
  font-size: 13px;
  line-height: 1.45;
  color: var(--muted-foreground);
}

.site-header__mega-empty-cta {
  margin-top: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 16px;
  border-radius: 10px;
  background: var(--primary);
  color: var(--primary-foreground);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: filter 0.18s ease;
}

.site-header__mega-empty-cta:hover {
  filter: brightness(1.05);
}

.site-header__mega-empty-cta:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

.site-header__search {
  position: relative;
  z-index: 1;
  min-width: 0;
}

/* Нативный input: без Naive NInput, чтобы клики и ввод всегда доходили до поля */
.site-header__search-input {
  box-sizing: border-box;
  display: block;
  width: 100%;
  min-height: 44px;
  margin: 0;
  padding: 0 52px 0 14px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--background);
  color: var(--foreground);
  font: inherit;
  font-size: 15px;
  line-height: 1.25;
  outline: none;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
  -webkit-appearance: none;
  appearance: none;
}

.site-header__search-input::placeholder {
  color: var(--muted-foreground);
  opacity: 1;
}

.site-header__search-input:focus,
.site-header__search-input:focus-visible {
  border-color: #000;
  box-shadow: none;
  outline: none;
}

.site-header__search-input::-webkit-search-cancel-button {
  -webkit-appearance: none;
  appearance: none;
}

.site-header__search-submit {
  position: absolute;
  top: 50%;
  right: 8px;
  z-index: 2;
  transform: translateY(-50%);
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 10px;
  background: var(--primary);
  color: var(--primary-foreground);
  cursor: pointer;
}

.site-header__search-submit:hover {
  opacity: 0.92;
}

.site-header__suggestions {
  position: absolute;
  z-index: 48;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  padding: 8px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--popover);
  box-shadow: 0 16px 40px rgb(15 23 42 / 12%);
}

.site-header__suggestion {
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
}

.site-header__suggestion:hover {
  background: var(--accent);
}

.site-header__suggestion img {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  object-fit: cover;
}

.site-header__suggestion-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.site-header__suggestion-body strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.site-header__suggestion-body small {
  color: var(--muted-foreground);
  font-size: 12px;
}

.site-header__aside {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.site-header__phone-block {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 12px;
  color: var(--foreground);
  text-decoration: none;
  font-weight: 700;
  font-size: 15px;
}

.site-header__phone-block:hover {
  background: var(--muted);
}

@media (min-width: 1101px) {
  .site-header__phone-block {
    display: inline-flex;
  }
}

.site-header__actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.site-header__tool {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 11px;
  border: 1px solid var(--border);
  background: var(--background);
  color: var(--foreground);
  text-decoration: none;
}

.site-header__tool:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.site-header__tool-count {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--primary);
  color: var(--primary-foreground);
  font-size: 11px;
  font-weight: 700;
  display: grid;
  place-items: center;
}

.site-header__auth,
.site-header__account {
  padding: 8px 14px;
  border-radius: 11px;
  border: 1px solid var(--border);
  background: var(--background);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  color: var(--foreground);
}

.site-header__auth:hover,
.site-header__account:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.site-header__aside--loading {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

/* Как у .site-header__search: не раздуваем колонку грида */
.site-header__search-skeleton {
  min-width: 0;
  width: 100%;
}

.mobile-drawer-overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgb(15 23 42 / 45%);
}

.mobile-drawer {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 71;
  width: min(92vw, 360px);
  background: var(--background);
  border-right: 1px solid var(--border);
  box-shadow: 8px 0 32px rgb(15 23 42 / 12%);
  padding: 16px;
  overflow-y: auto;
}

.mobile-drawer__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.mobile-drawer__title {
  font-weight: 800;
  font-size: 18px;
}

.mobile-drawer__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: var(--muted);
  color: var(--foreground);
  cursor: pointer;
}

.mobile-drawer__city {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 10px 12px;
  margin-bottom: 12px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--muted);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.mobile-drawer__nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mobile-drawer__link {
  padding: 10px 12px;
  border-radius: 10px;
  color: var(--foreground);
  text-decoration: none;
  font-weight: 600;
}

.mobile-drawer__link:hover {
  background: var(--muted);
}

.mobile-drawer__acc {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

.mobile-drawer__acc-title {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted-foreground);
}

.mobile-drawer__group {
  margin-bottom: 12px;
}

.mobile-drawer__parent {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  font-weight: 700;
  color: var(--foreground);
  text-decoration: none;
}

.mobile-drawer__parent .category-menu-icon {
  color: var(--muted-foreground);
}

.mobile-drawer__parent:hover .category-menu-icon {
  color: var(--foreground);
}

.mobile-drawer__parent:hover .category-menu-icon--img {
  opacity: 1;
}

.mobile-drawer__subs {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-left: 8px;
}

.mobile-drawer__sub {
  padding: 6px 8px;
  border-radius: 8px;
  font-size: 14px;
  color: var(--muted-foreground);
  text-decoration: none;
}

.mobile-drawer__sub:hover {
  background: var(--muted);
  color: var(--foreground);
}

.mobile-drawer__auth-block {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

.mobile-drawer__link--accent {
  display: block;
  text-align: center;
  background: var(--primary);
  color: var(--primary-foreground) !important;
}

.mobile-drawer__link--accent:hover {
  opacity: 0.92;
}

.footer {
  width: min(var(--layout-max-width), 92vw);
  margin: 30px auto 24px;
  padding: 22px 20px 16px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--muted);
}

.footer--tone-light {
  background: var(--background);
}

.footer--tone-muted {
  background: var(--muted);
}

.footer--tone-dark {
  background: color-mix(in srgb, #09090b 92%, var(--muted));
  border-color: color-mix(in srgb, var(--border) 55%, #09090b);
  color: var(--background);
}

.footer--tone-dark .footer__brand,
.footer--tone-dark .footer__title,
.footer--tone-dark .footer__links a,
.footer--tone-dark .footer__link-btn,
.footer--tone-dark .footer__text {
  color: var(--background);
}

.footer--tone-dark .footer__links a:hover,
.footer--tone-dark .footer__link-btn:hover {
  color: color-mix(in srgb, var(--background) 88%, var(--muted-foreground));
}

.footer--tone-dark .footer__copy {
  border-top-color: color-mix(in srgb, var(--background) 25%, transparent);
  color: color-mix(in srgb, var(--background) 72%, transparent);
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
  color: var(--foreground);
  text-decoration: none;
}

.footer__links a:hover {
  color: var(--primary);
}

.footer__link-btn {
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  color: var(--foreground);
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
  border-top: 1px solid var(--border);
  color: var(--muted-foreground);
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
  min-height: 50px;
  border-radius: 14px;
  font-size: 16px;
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
  background: var(--accent);
}

.city-modal__item--active {
  background: var(--muted);
  font-weight: 700;
}

@media (max-width: 1100px) {
  .site-header__phone-block {
    display: none;
  }
}

@media (max-width: 1023px) {
  .site-header__icon-btn--menu {
    display: inline-flex;
  }

  .site-header__catalog--desktop,
  .site-header__catalog-fallback.site-header__catalog--desktop {
    display: none;
  }

  .site-header__inner--main {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
      'lead aside'
      'search search';
    gap: 10px 12px;
  }

  .site-header__lead-group {
    grid-area: lead;
    min-width: 0;
  }

  .site-header__aside {
    grid-area: aside;
    flex-wrap: nowrap;
  }

  .site-header__search {
    grid-area: search;
  }

  .site-header__account,
  .site-header__auth {
    display: none;
  }
}

@media (max-width: 640px) {
  .site-header__strip-nav {
    order: 3;
    width: 100%;
    justify-content: flex-start;
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 4px;
    -webkit-overflow-scrolling: touch;
  }

  .site-header__inner--strip {
    flex-direction: column;
    align-items: flex-start;
  }

  .site-header__hours {
    margin-left: auto;
  }
}

@media (max-width: 760px) {
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
