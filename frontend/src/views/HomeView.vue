<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch, type Ref } from 'vue'
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'
import {
  BadgeCheck,
  ChevronLeft,
  ChevronRight,
  Clock,
  CreditCard,
  Headphones,
  MessageCircle,
  RotateCcw,
  ShieldCheck,
  Truck,
} from 'lucide-vue-next'
import { trackEvent } from '@/lib/analytics'
import { captureFirstTouchAttribution } from '@/lib/attribution'
import { fetchJson, requestJson } from '@/lib/api'
import { applyImageFallback, resolveBackgroundImage, resolveImageSrc } from '@/lib/image-fallback'
import { readRecentlyViewed, type RecentlyViewedItem } from '@/lib/recently-viewed'
import { toast } from '@/lib/toast'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import {
  siteSettingsInjectionKey,
  type SiteSettingsPayload,
} from '@/lib/site-settings'
import { isHomeSectionEnabled } from '@/lib/site-theme'

type Category = {
  id: number
  name: string
  slug: string
  image_url: string | null
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
  tags?: {
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

type Brand = {
  id: number
  name: string
  slug: string
  image_url: string | null
  products_count: number
}

type NewsPost = {
  id: number
  title: string
  slug: string
  excerpt: string | null
  cover_url: string | null
  published_at: string
}

type Banner = {
  title: string
  subtitle: string | null
  cta_label: string | null
  cta_url: string | null
  image_url: string | null
  bg_color: string | null
}

type MarketingCard = {
  id: number
  label: string | null
  title: string
  image_url: string | null
  link_url: string
  lines?: string[]
}

type HomePayload = {
  banner: Banner | null
  banners?: Banner[]
  categories: Category[]
  featured_products: Product[]
  news: NewsPost[]
  marketing_cards?: MarketingCard[]
}

type NewsletterSubscribeResponse = {
  ok: boolean
  status: 'subscribed' | 'already_subscribed'
  message: string
}

type StoreStats = {
  products_count: number
  brands_count: number
  orders_count: number
  rating_avg: number | null
}

const fallbackBanner: Banner = {
  title: 'Shoria Spring Drop',
  subtitle: 'Текущая версия витрины загружена в демо-режиме, API подключается автоматически.',
  cta_label: 'Открыть каталог',
  cta_url: '/catalog',
  image_url: 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1600&q=80',
  bg_color: '#1d4ed8',
}

const fallback: HomePayload = {
  banner: fallbackBanner,
  categories: [],
  featured_products: [],
  news: [],
  marketing_cards: [],
}

const state = ref<HomePayload>(fallback)
const isLoading = ref(true)
const hasError = ref(false)
const newsletterEmail = ref('')
const newsletterState = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
const newsletterMessage = ref('')
const recentlyViewed = ref<RecentlyViewedItem[]>([])
const heroSlider = ref<HTMLElement | null>(null)
const featuredSlider = ref<HTMLElement | null>(null)
const recentSlider = ref<HTMLElement | null>(null)
const brandsSlider = ref<HTMLElement | null>(null)
const heroActiveIndex = ref(0)
const brands = ref<Brand[]>([])
const storeStats = ref<StoreStats | null>(null)
const selectedFeaturedTagFilter = ref<'untagged' | string>('untagged')

const siteSettingsRef = inject(siteSettingsInjectionKey) as Ref<SiteSettingsPayload> | undefined

function homeSectionOn(key: string): boolean {
  return isHomeSectionEnabled(siteSettingsRef?.value?.theme, key)
}

const heroVariant = computed(() => siteSettingsRef?.value?.theme?.hero?.variant ?? 'overlay')
const marketingVariant = computed(() => siteSettingsRef?.value?.theme?.marketing?.variant ?? 'grid')

// Ровно 2 ряда: 10 на десктопе (5×2), не больше
const homeCategories = computed(() => state.value.categories.slice(0, 10))

const FEATURED_TAG_ORDER = ['hit', 'new', 'customer_choice'] as const

const tagsPresentInFeatured = computed(() => {
  const labels = new Map<string, string>()
  for (const p of state.value.featured_products) {
    for (const t of p.tags ?? []) {
      labels.set(t.code, t.label)
    }
  }

  return FEATURED_TAG_ORDER
    .filter((code) => labels.has(code))
    .map((code) => ({ code, label: labels.get(code)! }))
})

const filteredFeaturedProducts = computed(() => {
  const list = state.value.featured_products

  if (selectedFeaturedTagFilter.value === 'untagged') {
    return list.filter((p) => !(p.tags?.length))
  }

  const code = selectedFeaturedTagFilter.value

  return list.filter((p) => p.tags?.some((t) => t.code === code))
})

function selectFeaturedTagFilter(value: 'untagged' | string) {
  selectedFeaturedTagFilter.value = value
}

watch(selectedFeaturedTagFilter, () => {
  requestAnimationFrame(() => {
    featuredSlider.value?.scrollTo({ left: 0, behavior: 'auto' })
  })
})
const heroBanners = computed<Banner[]>(() => {
  if (state.value.banners?.length) {
    return state.value.banners
  }

  if (state.value.banner) {
    return [state.value.banner]
  }

  return [fallbackBanner]
})
type TrustHighlight = {
  icon: Component
  title: string
  text: string
}

const trustHighlights: TrustHighlight[] = [
  {
    icon: Truck,
    title: 'Доставка по РФ',
    text: 'Прозрачные сроки и стоимость доставки до оплаты.',
  },
  {
    icon: RotateCcw,
    title: 'Легкий возврат',
    text: 'Понятные условия возврата и обмена без лишних шагов.',
  },
  {
    icon: BadgeCheck,
    title: 'Оригинальные товары',
    text: 'Контроль качества, размеры и наличие обновляются из API.',
  },
  {
    icon: Headphones,
    title: 'Поддержка 7 дней',
    text: 'Оперативные ответы по заказам, размерам и оплате.',
  },
]

type WhyBlock = {
  icon: Component
  title: string
  metric: string
  text: string
}

const whyShoria: WhyBlock[] = [
  {
    icon: Clock,
    title: 'Доставка без сюрпризов',
    metric: '1-5 дней по РФ',
    text: 'Показываем срок и стоимость заранее, без скрытых доплат на последнем шаге.',
  },
  {
    icon: ShieldCheck,
    title: 'Возврат и обмен',
    metric: '14 дней',
    text: 'Понятная процедура возврата: заявка, подтверждение, быстрый статус.',
  },
  {
    icon: MessageCircle,
    title: 'Поддержка до и после покупки',
    metric: 'SLA до 30 минут',
    text: 'Помогаем с подбором размера, оплатой и вопросами по текущему заказу.',
  },
  {
    icon: CreditCard,
    title: 'Прозрачная оплата',
    metric: 'SSL + чек',
    text: 'Безопасный checkout, корректные суммы и документы по каждой покупке.',
  },
]
function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
  }).format(new Date(value))
}

function scrollSlider(target: HTMLElement | null, direction: 'prev' | 'next') {
  if (!target) {
    return
  }

  const shift = Math.max(target.clientWidth * 0.85, 280)
  target.scrollBy({
    left: direction === 'next' ? shift : -shift,
    behavior: 'smooth',
  })
}

function updateHeroIndexFromScroll() {
  const el = heroSlider.value
  const n = heroBanners.value.length
  if (!el || n < 2) {
    return
  }
  const page = el.clientWidth || 1
  const idx = Math.round(el.scrollLeft / page)
  heroActiveIndex.value = Math.min(Math.max(0, idx), n - 1)
}

function goHeroSlide(index: number) {
  const el = heroSlider.value
  if (!el) {
    return
  }
  const page = el.clientWidth
  el.scrollTo({ left: index * page, behavior: 'smooth' })
}

function stepHero(direction: 'prev' | 'next') {
  const n = heroBanners.value.length
  if (n < 2) {
    return
  }
  const next = direction === 'next'
    ? Math.min(heroActiveIndex.value + 1, n - 1)
    : Math.max(heroActiveIndex.value - 1, 0)
  goHeroSlide(next)
}

function resolveBannerHref(banner: Banner) {
  const value = banner.cta_url?.trim()

  if (!value) {
    return '/catalog'
  }

  return value
}

function isInternalBannerHref(value: string) {
  return value.startsWith('/')
}

function bannerLinkTag(banner: Banner) {
  return isInternalBannerHref(resolveBannerHref(banner)) ? RouterLink : 'a'
}

function bannerLinkProps(banner: Banner) {
  const href = resolveBannerHref(banner)

  if (isInternalBannerHref(href)) {
    return { to: href }
  }

  return {
    href,
    target: '_blank',
    rel: 'noopener noreferrer',
  }
}

function marketingCardLinkTag(url: string) {
  return isInternalBannerHref(url.trim()) ? RouterLink : 'a'
}

function marketingCardLinkProps(url: string) {
  const href = url.trim()

  if (isInternalBannerHref(href)) {
    return { to: href }
  }

  return {
    href,
    target: '_blank',
    rel: 'noopener noreferrer',
  }
}

async function loadHome(retry = true) {
  try {
    const data = await fetchJson<HomePayload>('/api/home')
    const isDegraded =
      data.featured_products.length === 0 &&
      data.categories.length === 0 &&
      !data.banner &&
      !(data.banners?.length)

    if (isDegraded && retry) {
      // Сервер вернул пустой ответ (холодный старт / незапущенный сидер).
      // Ждём и делаем один повторный запрос без кэша.
      await new Promise((resolve) => setTimeout(resolve, 1200))
      return loadHome(false)
    }

    state.value = data
    hasError.value = false
    void trackEvent('view_home', {
      featured_count: state.value.featured_products.length,
      categories_count: state.value.categories.length,
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
    state.value = fallback
  }
}

async function loadBrands() {
  try {
    const payload = await fetchJson<Brand[]>('/api/brands')
    brands.value = payload.slice(0, 10)
  } catch (error) {
    console.error(error)
    brands.value = []
  }
}

async function loadStats() {
  try {
    storeStats.value = await fetchJson<StoreStats>('/api/stats')
  } catch {
    storeStats.value = null
  }
}

onMounted(async () => {
  recentlyViewed.value = readRecentlyViewed()
  isLoading.value = true
  await Promise.all([
    loadHome(),
    loadBrands(),
    loadStats(),
  ])
  isLoading.value = false
})

async function subscribeToNewsletter() {
  const email = newsletterEmail.value.trim().toLowerCase()

  if (!email) {
    newsletterState.value = 'error'
    newsletterMessage.value = 'Укажи email для подписки.'
    toast.error('Укажите email для подписки.')
    return
  }

  newsletterState.value = 'loading'
  newsletterMessage.value = ''

  try {
    const response = await requestJson<NewsletterSubscribeResponse>('/api/newsletter/subscribe', {
      method: 'POST',
      body: JSON.stringify({
        email,
        source: 'home',
        attribution: captureFirstTouchAttribution(),
      }),
    })

    newsletterState.value = 'success'
    newsletterMessage.value = response.message
    newsletterEmail.value = ''
    toast.success(response.message)

    void trackEvent('subscribe_newsletter', {
      source: 'home',
      status: response.status,
    })
  } catch (error) {
    console.error(error)
    newsletterState.value = 'error'
    newsletterMessage.value = 'Не удалось оформить подписку. Проверь email и попробуй снова.'
    toast.error('Не удалось оформить подписку.')
  }
}
</script>

<template>
  <main class="home">
    <Transition name="home-fade">
    <div v-if="isLoading" key="skeleton">
      <section v-if="homeSectionOn('hero')" class="home-hero home-hero--skeleton" aria-hidden="true">
        <AppSkeleton class="home-hero__skeleton-plate" width="100%" height="calc(100svh - var(--header-h, 118px))" radius="0" />
      </section>

      <div class="home__container">
      <section v-if="homeSectionOn('trust')" class="stats stats--skeleton">
        <article v-for="index in 3" :key="`stats-skeleton-${index}`" class="stats-card">
          <AppSkeleton width="70%" height="clamp(26px, 4vw, 46px)" />
          <AppSkeleton width="55%" height="18px" />
        </article>
      </section>

      <section v-if="homeSectionOn('categories')" class="section">
        <header class="section__header">
          <AppSkeleton width="180px" height="clamp(26px, 3.6vw, 40px)" />
        </header>
        <div class="category-grid">
          <article v-for="index in 4" :key="`category-skeleton-${index}`" class="card category-card category-card--skeleton">
            <div class="skeleton-aspect skeleton-aspect--4-3">
              <AppSkeleton width="100%" height="100%" radius="16px" />
            </div>
          </article>
        </div>
      </section>

      <section v-if="homeSectionOn('featured')" class="section">
        <div class="section__toolbar">
          <header class="section__header">
            <AppSkeleton width="140px" height="clamp(26px, 3.6vw, 40px)" />
          </header>
        </div>
        <div class="home-tag-tabs home-tag-tabs--skeleton" aria-hidden="true">
          <AppSkeleton width="100px" height="32px" radius="8px" />
          <AppSkeleton width="72px" height="32px" radius="8px" />
          <AppSkeleton width="96px" height="32px" radius="8px" />
        </div>
        <div class="slider">
          <article v-for="index in 4" :key="`featured-skeleton-${index}`" class="slider-card slider-card--skeleton">
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

      <section v-if="homeSectionOn('marketing')" class="section marketing marketing--skeleton" aria-hidden="true">
        <div class="marketing__grid">
          <article v-for="index in 3" :key="`mkt-skeleton-${index}`" class="marketing-card marketing-card--skeleton">
            <AppSkeleton width="100%" height="100%" radius="20px" />
          </article>
        </div>
      </section>

      <section v-if="homeSectionOn('news')" class="section">
        <div class="section__toolbar section__toolbar--news">
          <header class="section__header">
            <AppSkeleton width="220px" height="clamp(26px, 3.6vw, 40px)" />
          </header>
        </div>
        <div class="news-grid">
          <article v-for="index in 3" :key="`news-skeleton-${index}`" class="card news-card">
            <div class="skeleton-aspect skeleton-aspect--16-10">
              <AppSkeleton width="100%" height="100%" radius="0" />
            </div>
            <div class="card__content">
              <AppSkeleton width="24%" height="14px" />
              <AppSkeleton width="72%" height="24px" />
              <AppSkeleton width="100%" height="14px" />
              <AppSkeleton width="84%" height="14px" />
            </div>
          </article>
        </div>
      </section>
      </div>
    </div>

    <div v-else key="content">
    <section
      v-if="homeSectionOn('hero')"
      class="home-hero"
      :class="`home-hero--${heroVariant}`"
      aria-label="Главный баннер"
    >
      <div
        ref="heroSlider"
        class="home-hero__track"
        @scroll.passive="updateHeroIndexFromScroll"
      >
        <!-- Вариант: overlay (текст поверх картинки) -->
        <template v-if="heroVariant === 'overlay'">
          <article
            v-for="(banner, index) in heroBanners"
            :key="`${banner.title}-${index}`"
            class="home-hero__slide"
            :style="{
              '--hero-bg': banner.bg_color ?? '#1d4ed8',
              '--hero-image': resolveBackgroundImage(banner.image_url),
            }"
          >
            <component :is="bannerLinkTag(banner)" class="home-hero__link" v-bind="bannerLinkProps(banner)">
              <div class="home-hero__overlay" />
              <div class="home-hero__inner">
                <p class="home-hero__eyebrow">Интернет-магазин Shoria</p>
                <h1>{{ banner.title ?? 'Shoria' }}</h1>
                <p class="home-hero__subtitle">
                  {{ banner.subtitle ?? 'Магазин кроссовок с живым API и админкой на Filament.' }}
                </p>
                <span class="home-hero__cta">
                  {{ banner.cta_label ?? 'Смотреть каталог' }}
                </span>
              </div>
            </component>
          </article>
        </template>

        <!-- Вариант: split (текст слева, картинка справа) -->
        <template v-else-if="heroVariant === 'split'">
          <article
            v-for="(banner, index) in heroBanners"
            :key="`${banner.title}-${index}`"
            class="home-hero__slide home-hero__slide--split"
            :style="{ '--hero-bg': banner.bg_color ?? '#1d4ed8' }"
          >
            <component :is="bannerLinkTag(banner)" class="home-hero__link" v-bind="bannerLinkProps(banner)">
              <div class="home-hero__split-text">
                <p class="home-hero__eyebrow">Интернет-магазин Shoria</p>
                <h1>{{ banner.title ?? 'Shoria' }}</h1>
                <p class="home-hero__subtitle">
                  {{ banner.subtitle ?? 'Магазин кроссовок с живым API и админкой на Filament.' }}
                </p>
                <span class="home-hero__cta home-hero__cta--dark">
                  {{ banner.cta_label ?? 'Смотреть каталог' }}
                </span>
              </div>
              <div
                class="home-hero__split-image"
                :style="{ '--hero-image': resolveBackgroundImage(banner.image_url) }"
              />
            </component>
          </article>
        </template>
      </div>

      <div v-if="heroBanners.length > 1" class="home-hero__dots" role="tablist" aria-label="Слайды баннера">
        <button
          v-for="(_, i) in heroBanners"
          :key="`hero-dot-${i}`"
          type="button"
          class="home-hero__dot"
          :class="{ 'home-hero__dot--active': heroActiveIndex === i }"
          :aria-selected="heroActiveIndex === i"
          :aria-label="`Слайд ${i + 1}`"
          @click="goHeroSlide(i)"
        />
      </div>
      <div v-if="heroBanners.length > 1" class="home-hero__arrows">
        <button type="button" class="home-hero__arrow" aria-label="Предыдущий слайд" @click="stepHero('prev')">
          <ChevronLeft :size="22" :stroke-width="2" aria-hidden="true" />
        </button>
        <button type="button" class="home-hero__arrow" aria-label="Следующий слайд" @click="stepHero('next')">
          <ChevronRight :size="22" :stroke-width="2" aria-hidden="true" />
        </button>
      </div>
    </section>

    <div class="home__container">
    <section v-if="homeSectionOn('trust') && storeStats" class="stats">
      <article class="stats-card">
        <span class="stats-card__value">{{ storeStats.products_count.toLocaleString('ru-RU') }}</span>
        <span class="stats-card__label">товаров в каталоге</span>
      </article>
      <article class="stats-card">
        <span class="stats-card__value">{{ storeStats.brands_count }}</span>
        <span class="stats-card__label">брендов</span>
      </article>
      <article class="stats-card">
        <span class="stats-card__value">{{ storeStats.orders_count.toLocaleString('ru-RU') }}</span>
        <span class="stats-card__label">выполненных заказов</span>
      </article>
      <article v-if="storeStats.rating_avg" class="stats-card">
        <span class="stats-card__value">{{ storeStats.rating_avg }} <span class="stats-card__star">★</span></span>
        <span class="stats-card__label">средний рейтинг</span>
      </article>
    </section>

    <section v-if="homeSectionOn('categories')" class="section">
      <header class="section__header">
        <RouterLink to="/catalog" class="section__title-link"><h2>Категории</h2></RouterLink>
      </header>
      <div class="category-grid">
        <template v-if="homeCategories.length">
          <article v-for="item in homeCategories" :key="item.id" class="card category-card">
            <RouterLink class="category-link category-link--tile" :to="`/catalog/${item.slug}`">
              <div class="category-card__media">
                <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
                <div class="category-card__fade" aria-hidden="true" />
                <h3 class="category-card__title">{{ item.name }}</h3>
              </div>
            </RouterLink>
          </article>
        </template>
        <article v-else class="card empty-card">
          <div class="card__content">
            <h3>Категории появятся после наполнения</h3>
            <p>Добавь категории в админке, и здесь сразу появятся карточки.</p>
            <a href="http://localhost:8080/admin/categories" target="_blank" rel="noopener noreferrer">
              Открыть админку категорий
            </a>
          </div>
        </article>
      </div>
    </section>

    <section v-if="homeSectionOn('brands') && brands.length" class="section">
      <div class="section__toolbar">
        <header class="section__header">
          <RouterLink to="/brands" class="section__title-link"><h2>Бренды</h2></RouterLink>
        </header>
      </div>
      <div class="brands-grid">
        <RouterLink
          v-for="brand in brands.slice(0, 10)"
          :key="`home-brand-${brand.id}`"
          :to="{ path: '/catalog', query: { brands: brand.name } }"
          class="brand-card"
        >
          <img :src="resolveImageSrc(brand.image_url)" :alt="brand.name" loading="lazy" @error="applyImageFallback" />
          <div class="brand-card__body">
            <h3>{{ brand.name }}</h3>
            <p>{{ brand.products_count }} товаров</p>
          </div>
        </RouterLink>
      </div>
    </section>

    <section v-if="homeSectionOn('featured')" class="section">
      <div class="section__toolbar">
        <header class="section__header">
          <h2>Рекомендуем</h2>
        </header>
        <div class="section__head-actions">
          <button type="button" class="slider-nav" aria-label="Предыдущие товары" @click="scrollSlider(featuredSlider, 'prev')">
            <ChevronLeft :size="18" :stroke-width="2" aria-hidden="true" />
          </button>
          <button type="button" class="slider-nav" aria-label="Следующие товары" @click="scrollSlider(featuredSlider, 'next')">
            <ChevronRight :size="18" :stroke-width="2" aria-hidden="true" />
          </button>
        </div>
      </div>
      <div
        v-if="state.featured_products.length"
        class="home-tag-tabs"
        role="tablist"
        aria-label="Фильтр подборки по меткам"
      >
        <button
          type="button"
          role="tab"
          class="home-tag-tab"
          :class="{ 'home-tag-tab--active': selectedFeaturedTagFilter === 'untagged' }"
          :aria-selected="selectedFeaturedTagFilter === 'untagged'"
          @click="selectFeaturedTagFilter('untagged')"
        >
          Без тегов
        </button>
        <button
          v-for="tag in tagsPresentInFeatured"
          :key="`featured-tag-${tag.code}`"
          type="button"
          role="tab"
          class="home-tag-tab"
          :class="{ 'home-tag-tab--active': selectedFeaturedTagFilter === tag.code }"
          :aria-selected="selectedFeaturedTagFilter === tag.code"
          @click="selectFeaturedTagFilter(tag.code)"
        >
          {{ tag.label }}
        </button>
      </div>
      <div ref="featuredSlider" class="slider">
        <template v-if="state.featured_products.length">
          <template v-if="filteredFeaturedProducts.length">
            <UnifiedProductCard
              v-for="product in filteredFeaturedProducts"
              :key="`featured-${product.id}`"
              :product="product"
              source="home_featured"
              :show-image-skeleton="false"
              class="slider-card"
            />
          </template>
          <article v-else class="card empty-card slider-card">
            <div class="card__content">
              <h3>Нет товаров с выбранной меткой</h3>
              <p>В этой подборке пока нет позиций с такой меткой. Выберите другой таб или откройте каталог.</p>
              <RouterLink to="/catalog" class="section__more-link">Перейти в каталог</RouterLink>
            </div>
          </article>
        </template>
        <article v-else class="card empty-card slider-card">
          <div class="card__content">
            <h3>Пока нет приоритетных товаров</h3>
            <p>Назначь товары для главной в админке, чтобы запустить витрину.</p>
            <a href="http://localhost:8080/admin/products" target="_blank" rel="noopener noreferrer">
              Открыть товары в админке
            </a>
          </div>
        </article>
      </div>
    </section>

    <section
      v-if="homeSectionOn('marketing') && (state.marketing_cards?.length ?? 0) > 0"
      :class="`section marketing marketing--${marketingVariant}`"
      aria-label="Подборки и акции"
    >
      <div class="marketing__grid">
        <component
          :is="marketingCardLinkTag(card.link_url)"
          v-for="(card, cardIndex) in state.marketing_cards"
          :key="`marketing-${card.id}`"
          :class="[
            'marketing-card marketing-card--link',
            marketingVariant === 'mosaic' && cardIndex === 0 ? 'marketing-card--mosaic-hero' : '',
            marketingVariant === 'list' ? 'marketing-card--list' : '',
          ]"
          :aria-label="`Открыть: ${card.title}`"
          v-bind="marketingCardLinkProps(card.link_url)"
          :style="{
            '--mkt-bg': resolveBackgroundImage(card.image_url),
          }"
        >
          <div class="marketing-card__scrim" aria-hidden="true" />
          <div class="marketing-card__inner">
            <p v-if="card.label" class="marketing-card__label">{{ card.label }}</p>
            <h3 class="marketing-card__title">{{ card.title }}</h3>
            <div v-if="(card.lines?.length ?? 0) > 0" class="marketing-card__lines">
              <p
                v-for="(line, lineIndex) in card.lines"
                :key="`marketing-${card.id}-line-${lineIndex}`"
                class="marketing-card__line"
              >
                {{ line }}
              </p>
            </div>
          </div>
        </component>
      </div>
    </section>

    <section v-if="homeSectionOn('recent') && recentlyViewed.length" class="section">
      <div class="section__toolbar">
        <header class="section__header">
          <h2>Недавно просмотренные</h2>
        </header>
        <div class="section__head-actions">
          <button type="button" class="slider-nav" aria-label="Предыдущие из просмотренных" @click="scrollSlider(recentSlider, 'prev')">
            <ChevronLeft :size="18" :stroke-width="2" aria-hidden="true" />
          </button>
          <button type="button" class="slider-nav" aria-label="Следующие из просмотренных" @click="scrollSlider(recentSlider, 'next')">
            <ChevronRight :size="18" :stroke-width="2" aria-hidden="true" />
          </button>
        </div>
      </div>
      <div ref="recentSlider" class="slider">
        <UnifiedProductCard
          v-for="product in recentlyViewed"
          :key="`recent-${product.id}`"
          :product="product"
          source="home_recent"
          :show-image-skeleton="false"
          class="slider-card"
        />
      </div>
    </section>

    <section v-if="homeSectionOn('why')" class="section why">
      <header class="section__header">
        <h2>Почему Shoria</h2>
      </header>
      <div class="why-grid">
        <article v-for="item in whyShoria" :key="item.title" class="why-card">
          <component :is="item.icon" class="why-card__icon" :size="20" :stroke-width="1.75" aria-hidden="true" />
          <p class="why-card__metric">{{ item.metric }}</p>
          <h3>{{ item.title }}</h3>
          <p>{{ item.text }}</p>
        </article>
      </div>
      <RouterLink class="why__cta" to="/catalog">Перейти к выбору моделей</RouterLink>
    </section>

    <section v-if="homeSectionOn('newsletter')" class="section capture">
      <header class="section__header">
        <h2>Подписка на дропы и скидки</h2>
      </header>
      <form class="capture__form" @submit.prevent="subscribeToNewsletter">
        <label class="sr-only" for="newsletter-email">Email</label>
        <input
          id="newsletter-email"
          v-model="newsletterEmail"
          type="email"
          autocomplete="email"
          placeholder="name@example.com"
          :disabled="newsletterState === 'loading'"
          required
        />
        <button type="submit" :disabled="newsletterState === 'loading'">
          {{ newsletterState === 'loading' ? 'Отправляем...' : 'Подписаться' }}
        </button>
      </form>
      <p v-if="newsletterMessage" :class="['capture__message', newsletterState === 'error' ? 'capture__message--error' : '']">
        {{ newsletterMessage }}
      </p>
    </section>

    <section v-if="homeSectionOn('news')" class="section">
      <div class="section__toolbar section__toolbar--news">
        <header class="section__header">
          <h2>Новости и подборки</h2>
        </header>
        <RouterLink class="section__more-link section__more-link--aside" to="/news">Все новости</RouterLink>
      </div>
      <div class="news-grid">
        <template v-if="state.news.length">
          <article v-for="post in state.news" :key="post.id" class="card news-card">
            <RouterLink class="news-link" :to="{ name: 'news-post', params: { slug: post.slug } }">
              <img :src="resolveImageSrc(post.cover_url)" :alt="post.title" loading="lazy" @error="applyImageFallback" />
              <div class="card__content">
                <p class="news-date">{{ formatDate(post.published_at) }}</p>
                <h3>{{ post.title }}</h3>
                <p>{{ post.excerpt }}</p>
              </div>
            </RouterLink>
          </article>
        </template>
        <article v-else class="card empty-card">
          <div class="card__content">
            <h3>Контент-блок готов к SEO-материалам</h3>
            <p>Добавь новости и гайды в админке, чтобы усиливать органический трафик.</p>
            <a href="http://localhost:8080/admin/news-posts" target="_blank" rel="noopener noreferrer">
              Открыть новости в админке
            </a>
          </div>
        </article>
      </div>
    </section>
    </div>
    </div>
    </Transition>

    <p v-if="hasError" class="status status--warn">
      API пока недоступно, показаны демо-данные. Проверь `VITE_API_URL` и backend контейнер.
    </p>
  </main>
</template>

<style scoped>
.home-fade-enter-active {
  transition: opacity 0.25s ease;
}

.home-fade-enter-from {
  opacity: 0;
}

.home {
  width: 100%;
  padding: 0 0 48px;
  overflow-anchor: auto;
}

.home__container {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 40px 0 0;
}

/* Full-bleed hero */
.home-hero {
  position: relative;
  width: 100%;
  overflow: hidden;
}

.home-hero--skeleton {
  background: var(--muted);
}

.home-hero__skeleton-plate {
  display: block;
  width: 100%;
}

.home-hero__track {
  display: flex;
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  scrollbar-width: none;
}

.home-hero__track::-webkit-scrollbar {
  display: none;
}

.home-hero__slide {
  position: relative;
  flex: 0 0 100%;
  scroll-snap-align: start;
  min-height: calc(100svh - var(--header-h, 118px));
  overflow: hidden;
  isolation: isolate;
  background: linear-gradient(
    125deg,
    var(--hero-bg) 0%,
    color-mix(in srgb, var(--hero-bg), #0f172a 42%) 100%
  );
  color: #f9fafb;
}

.home-hero__slide::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: var(--hero-image);
  background-position: center;
  background-size: cover;
  opacity: 0.34;
  z-index: 0;
}

.home-hero__link {
  position: relative;
  display: block;
  min-height: inherit;
  color: inherit;
  text-decoration: none;
  z-index: 1;
}

.home-hero__overlay {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(to bottom, rgb(0 0 0 / 0.55) 0%, transparent 22%),
    linear-gradient(110deg, rgb(15 23 42 / 58%) 0%, transparent 58%);
  pointer-events: none;
  z-index: 0;
}

.home-hero__inner {
  position: relative;
  z-index: 1;
  box-sizing: border-box;
  max-width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: clamp(28px, 5vw, 56px) clamp(16px, 4vw, 24px);
  min-height: calc(100svh - var(--header-h, 118px));
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.home-hero__eyebrow {
  display: inline-block;
  align-self: flex-start;
  margin-bottom: 14px;
  padding: 6px 12px;
  border: 1px solid rgb(255 255 255 / 38%);
  border-radius: 999px;
  letter-spacing: 0.06em;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.home-hero h1 {
  margin-bottom: 12px;
  font-size: clamp(38px, 6.6vw, 78px);
  font-family: var(--font-display);
  line-height: 0.95;
  letter-spacing: 0.02em;
}

.home-hero__subtitle {
  max-width: 52ch;
  margin-bottom: 22px;
  font-size: clamp(15px, 1.9vw, 18px);
  color: rgb(249 250 251 / 90%);
  line-height: 1.45;
}

.home-hero__cta {
  display: inline-block;
  align-self: flex-start;
  padding: 12px 22px;
  border-radius: 999px;
  background: #fff;
  color: var(--primary);
  font-weight: 700;
  box-shadow: 0 1px 2px rgb(0 0 0 / 14%);
}

.home-hero__dots {
  position: absolute;
  left: 50%;
  bottom: 18px;
  transform: translateX(-50%);
  display: flex;
  gap: 8px;
  z-index: 3;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgb(15 23 42 / 28%);
  backdrop-filter: blur(8px);
}

.home-hero__dot {
  width: 8px;
  height: 8px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgb(255 255 255 / 42%);
  cursor: pointer;
  transition: transform 0.15s ease, background 0.15s ease;
}

.home-hero__dot--active {
  background: #fff;
  transform: scale(1.15);
}

.home-hero__arrows {
  position: absolute;
  right: clamp(12px, 3vw, 28px);
  bottom: clamp(58px, 11vh, 96px);
  z-index: 3;
  display: flex;
  gap: 8px;
}

.home-hero__arrow {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgb(255 255 255 / 38%);
  border-radius: 50%;
  background: rgb(255 255 255 / 14%);
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(10px);
  font-size: 18px;
  line-height: 1;
}

.home-hero__arrow:hover {
  background: rgb(255 255 255 / 24%);
}

.home-hero__arrow:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 2px;
}

/* ── Вариант: split ──────────────────────────────────── */
.home-hero--split .home-hero__slide--split {
  background: #fff;
  color: var(--foreground);
  min-height: clamp(360px, 52vh, 520px);
}

.home-hero--split .home-hero__link {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: inherit;
}

.home-hero__split-text {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: clamp(28px, 5vw, 64px) clamp(20px, 4vw, 56px);
}

.home-hero--split .home-hero__eyebrow {
  border-color: color-mix(in srgb, var(--primary), transparent 70%);
  color: var(--primary);
}

.home-hero--split h1 {
  color: var(--foreground);
}

.home-hero--split .home-hero__subtitle {
  color: var(--muted-foreground);
}

.home-hero__cta--dark {
  background: var(--primary);
  color: var(--primary-foreground);
}

.home-hero__split-image {
  background-image: var(--hero-image);
  background-size: cover;
  background-position: center;
  min-height: inherit;
}

/* split dots/arrows — тёмные на светлом фоне */
.home-hero--split .home-hero__dot {
  background: color-mix(in srgb, var(--primary), transparent 70%);
}

.home-hero--split .home-hero__dot--active {
  background: var(--primary);
}

.home-hero--split .home-hero__arrow {
  background: rgb(0 0 0 / 8%);
  color: var(--foreground);
}

.home-hero--split .home-hero__arrow:hover {
  background: rgb(0 0 0 / 15%);
}

@media (max-width: 768px) {
  .home-hero--split .home-hero__link {
    grid-template-columns: 1fr;
  }

  .home-hero__split-image {
    min-height: 220px;
  }
}

/* Фильтр подборки по меткам */
.home-tag-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: -2px 0 14px;
}

.home-tag-tab {
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--muted);
  color: var(--foreground);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    background 0.15s ease,
    color 0.15s ease;
}

.home-tag-tab:hover {
  border-color: color-mix(in srgb, var(--primary), transparent 55%);
  color: var(--primary);
}

.home-tag-tab--active {
  border-color: color-mix(in srgb, var(--primary), transparent 35%);
  background: var(--accent);
  color: var(--accent-foreground);
}

.home-tag-tab:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

.home-tag-tabs--skeleton {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: -2px 0 14px;
}

.section__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.section__toolbar .section__header {
  flex: 1 1 240px;
  margin-bottom: 0;
}

.section__toolbar--news {
  align-items: flex-start;
}

.section__more-link--aside {
  flex-shrink: 0;
  margin-top: 4px;
}

.section__head-actions {
  display: flex;
  flex-shrink: 0;
  gap: 8px;
}

.slider-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 50%;
  background: var(--card);
  color: var(--foreground);
  cursor: pointer;
  box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
}

.slider-nav:hover {
  border-color: color-mix(in srgb, var(--primary), transparent 60%);
  color: var(--primary);
}

.slider-nav:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}

.slider {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scroll-padding-inline: 12px;
  scrollbar-width: none;
  background: transparent;
}

.slider :deep(.unified-product-card:hover) {
  transform: none;
}

.slider > .card:hover {
  transform: none;
}

.slider::-webkit-scrollbar {
  display: none;
}

.slider > * {
  scroll-snap-align: start;
}

.slider-card--skeleton {
  display: flex;
  flex: 0 0 clamp(220px, 20vw, 272px);
  flex-direction: column;
  padding: 10px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
  overflow: hidden;
}

.slider-card__skeleton-media {
  position: relative;
  aspect-ratio: 4 / 3;
  border: 1px solid color-mix(in srgb, var(--border), transparent 30%);
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

.stats--skeleton .stats-card {
  gap: 4px;
}

.category-card--skeleton {
  overflow: hidden;
}

.skeleton-aspect {
  width: 100%;
  overflow: hidden;
  display: flex;
}

.skeleton-aspect--4-3 {
  aspect-ratio: 4 / 3;
  border-radius: 22px 22px 0 0;
}

.skeleton-aspect--16-10 {
  aspect-ratio: 16 / 10;
  border-radius: 24px 24px 0 0;
}

.section {
  margin-top: 40px;
}

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-top: 0;
}

.stats-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border-radius: 14px;
  background: var(--muted);
  border: 1px solid var(--border);
  padding: 20px 16px;
  text-align: center;
}

.stats-card__value {
  font-family: var(--font-display);
  font-size: clamp(32px, 4vw, 46px);
  line-height: 1;
  font-weight: 700;
  color: var(--foreground);
  letter-spacing: -0.01em;
}

.stats-card__star {
  font-size: 0.65em;
  color: #f59e0b;
  vertical-align: middle;
}

.stats-card__label {
  font-size: 13px;
  color: var(--muted-foreground);
  font-weight: 500;
}

.marketing {
  margin-top: 36px;
}

.marketing--skeleton .marketing-card--skeleton {
  min-height: 260px;
}

.marketing__grid {
  display: grid;
  gap: 14px;
  grid-template-columns: 1fr;
}

@media (min-width: 640px) {
  .marketing__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .marketing__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.marketing-card--link {
  display: block;
  text-decoration: none;
  color: inherit;
  -webkit-tap-highlight-color: transparent;
}

.marketing-card--link:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 4px;
}

.marketing-card {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  border: 1px solid var(--border);
  min-height: 260px;
  background-color: #111827;
  background-image: var(--mkt-bg);
  background-size: cover;
  background-position: center;
}

.marketing-card__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgb(0 0 0 / 0.78) 0%,
    rgb(0 0 0 / 0.35) 42%,
    rgb(0 0 0 / 0.12) 100%
  );
  pointer-events: none;
}

.marketing-card__inner {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 260px;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  padding: 22px 18px 20px;
  text-align: center;
}

.marketing-card__label {
  margin: 0 0 10px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgb(255 255 255 / 0.88);
}

.marketing-card__title {
  margin: 0 0 16px;
  max-width: 22ch;
  font-size: clamp(18px, 2.1vw, 22px);
  font-weight: 700;
  line-height: 1.25;
  color: #fff;
}

.marketing-card__lines {
  display: grid;
  gap: 6px;
  width: 100%;
  max-width: 28ch;
}

.marketing-card__line {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.35;
  color: rgb(255 255 255 / 0.92);
}

/* ── marketing variant: mosaic ── */
@media (min-width: 1024px) {
  .marketing--mosaic .marketing__grid {
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto;
  }

  .marketing--mosaic .marketing-card--mosaic-hero {
    grid-row: 1 / 3;
    min-height: 540px;
  }
}

/* ── marketing variant: list ── */
.marketing--list .marketing__grid {
  grid-template-columns: 1fr;
}

.marketing--list .marketing-card--list {
  min-height: 120px;
  border-radius: 16px;
  display: grid;
  grid-template-columns: 200px 1fr;
  overflow: hidden;
}

.marketing--list .marketing-card--list .marketing-card__scrim {
  background: linear-gradient(
    to right,
    rgb(0 0 0 / 0.0) 0%,
    rgb(0 0 0 / 0.35) 40%,
    rgb(0 0 0 / 0.75) 100%
  );
}

.marketing--list .marketing-card--list .marketing-card__inner {
  grid-column: 2;
  min-height: 120px;
  align-items: flex-start;
  justify-content: center;
  padding: 20px 24px;
  text-align: left;
}

.marketing--list .marketing-card--list .marketing-card__title {
  max-width: none;
  font-size: clamp(16px, 1.8vw, 20px);
  margin-bottom: 8px;
}

@media (max-width: 639px) {
  .marketing--list .marketing-card--list {
    grid-template-columns: 120px 1fr;
    min-height: 96px;
  }
}

/* ── skeleton ── */
.section__title-link {
  text-decoration: none;
  color: inherit;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.section__title-link h2 {
  margin: 0;
}

.section__title-link:hover h2 {
  text-decoration: underline;
  text-underline-offset: 4px;
  text-decoration-thickness: 2px;
}

.section__header {
  margin-bottom: 14px;
}

.section__header h2 {
  font-family: var(--font-display);
  font-size: clamp(26px, 3.6vw, 40px);
  line-height: 1.02;
  letter-spacing: 0.02em;
}

.section__header p {
  margin-top: 8px;
  color: var(--color-text-soft);
  font-size: 15px;
}

.section__more-link {
  display: inline-block;
  margin-top: 8px;
  color: var(--primary);
  font-weight: 700;
  font-size: 14px;
  text-decoration: none;
}

.section__more-link:hover {
  text-decoration: underline;
}

.category-grid,
.news-grid {
  display: grid;
  gap: 16px;
}

.category-grid__footer {
  margin-top: 14px;
  display: flex;
  justify-content: center;
}

.category-all-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 28px;
  border-radius: 999px;
  border: 1.5px solid var(--border);
  background: transparent;
  color: var(--foreground);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.15s, border-color 0.15s;
}

.category-all-btn:hover {
  background: var(--muted);
  border-color: var(--foreground);
}

.category-grid {
  grid-template-columns: repeat(3, 1fr);
}

@media (min-width: 768px) {
  .category-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (min-width: 1100px) {
  .category-grid {
    grid-template-columns: repeat(5, 1fr);
  }
}

.slider-card {
  flex: 0 0 clamp(220px, 20vw, 272px);
}

.brands-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(3, 1fr);
}

@media (min-width: 768px) {
  .brands-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (min-width: 1100px) {
  .brands-grid {
    grid-template-columns: repeat(5, 1fr);
  }
}

.brand-card {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  overflow: hidden;
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.brand-card:hover {
  border-color: color-mix(in srgb, var(--primary), transparent 55%);
  box-shadow: 0 4px 16px rgb(15 23 42 / 8%);
}

.brand-card img {
  width: 100%;
  height: 140px;
  object-fit: cover;
}

.brand-card__body {
  padding: 12px 14px 14px;
}

.brand-card__body h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.brand-card__body p {
  margin-top: 4px;
  color: var(--color-text-soft);
  font-size: 14px;
}

.news-grid {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.why {
  padding: 22px;
  border-radius: 16px;
  background: var(--muted);
  border: 1px solid var(--border);
}

.why-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.why-card {
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--card);
}

.why-card__icon {
  display: block;
  color: var(--primary);
  margin-bottom: 10px;
}

.why-card__metric {
  color: var(--primary);
  font-weight: 800;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.why__cta {
  display: inline-block;
  margin-top: 16px;
  color: var(--primary);
  font-weight: 800;
  text-decoration: none;
  font-size: 15px;
}

.why__cta:hover {
  text-decoration: underline;
}

.capture {
  padding: 22px;
  border-radius: 16px;
  background: var(--accent);
  border: 1px solid color-mix(in srgb, var(--primary), transparent 88%);
}

.capture__form {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.capture__form input {
  flex: 1 1 260px;
  min-height: 44px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--card);
  padding: 0 12px;
  color: var(--foreground);
}

.capture__form input::placeholder {
  color: var(--muted-foreground);
}

.capture__form button {
  min-height: 44px;
  border: 0;
  border-radius: 10px;
  background: var(--primary);
  color: var(--primary-foreground);
  padding: 0 18px;
  font-weight: 700;
  cursor: pointer;
}

.capture__form button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.capture__message {
  margin-top: 10px;
  color: #15803d;
  font-size: 14px;
}

.capture__message--error {
  color: var(--destructive);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.card {
  position: relative;
  overflow: hidden;
  border-radius: 16px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgb(15 23 42 / 9%);
}

.card img {
  width: 100%;
  height: 186px;
  object-fit: cover;
}

.news-card .news-link img {
  height: auto;
  aspect-ratio: 16 / 10;
}

.card__content {
  padding: 14px 16px 16px;
}

.card h3 {
  margin-bottom: 5px;
  font-size: 19px;
  line-height: 1.25;
  font-weight: 700;
}

.category-card {
  padding: 0;
  background: transparent;
  border: none;
  box-shadow: none;
}

.category-card:hover {
  transform: none;
  box-shadow: none;
}

.category-link {
  display: block;
  color: inherit;
  text-decoration: none;
}

.category-link--tile {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border);
  background: var(--card);
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
  transition:
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.category-link--tile:hover {
  border-color: color-mix(in srgb, var(--primary), transparent 62%);
  box-shadow: 0 8px 22px rgb(15 23 42 / 9%);
}

.category-card__media {
  position: relative;
  aspect-ratio: 4 / 3;
  overflow: hidden;
}

.category-card__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.category-card__fade {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgb(17 24 39 / 76%) 0%, rgb(17 24 39 / 12%) 45%, transparent 72%);
  pointer-events: none;
}

.category-card__title {
  position: absolute;
  left: 14px;
  right: 14px;
  bottom: 13px;
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.25;
  color: #fff;
  text-shadow: 0 1px 2px rgb(0 0 0 / 35%);
}

.news-link {
  display: block;
  color: inherit;
  text-decoration: none;
}

.card p {
  color: var(--color-text-soft);
}

.news-date {
  margin-bottom: 6px;
  color: var(--muted-foreground);
  font-size: 13px;
}

.empty-card {
  border: 1px dashed var(--border);
  background: var(--muted);
}

.empty-card a {
  display: inline-block;
  margin-top: 10px;
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
}

.empty-card a:hover {
  text-decoration: underline;
}

.status {
  width: min(var(--layout-max-width), 92vw);
  margin: 18px auto 0;
  padding: 0;
  font-size: 14px;
  color: var(--muted-foreground);
}

.status--warn {
  color: #b45309;
}

@media (max-width: 720px) {
  .home-hero__arrows {
    bottom: 52px;
  }

  .brand-slide {
    flex: 0 0 calc((100% - 14px) / 2);
  }
}
</style>
