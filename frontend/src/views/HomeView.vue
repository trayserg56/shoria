<script setup lang="ts">
import { computed, inject, nextTick, onMounted, ref, watch, type Ref } from 'vue'
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  BadgeCheck,
  ChevronLeft,
  ChevronRight,
  Clock,
  CreditCard,
  Dumbbell,
  Flame,
  Headphones,
  MessageCircle,
  Mountain,
  RotateCcw,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  Star,
  Trophy,
  Truck,
  Wind,
  Zap,
  Check,
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
  hover_image_url?: string | null
  tags?: { code: string; label: string }[]
  reviews_summary?: { count: number; average: number | null }
  category: { name: string; slug: string } | null
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

// --------------- Состояние ---------------
const fallbackBanner: Banner = {
  title: 'Новая коллекция Spring 2026',
  subtitle: 'Свежие релизы Nike, Adidas и New Balance — уже в каталоге.',
  cta_label: 'Смотреть каталог',
  cta_url: '/catalog',
  image_url: 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1600&q=80',
  bg_color: '#EAF6FF',
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
const selectedFeaturedTagFilter = ref<string>('all')
const selectedLookbookTab = ref<'ideas' | 'capsules'>('ideas')

const siteSettingsRef = inject(siteSettingsInjectionKey) as Ref<SiteSettingsPayload> | undefined

function homeSectionOn(key: string): boolean {
  return isHomeSectionEnabled(siteSettingsRef?.value?.theme, key)
}

// --------------- Статические данные ---------------
const staticReviews = [
  {
    id: 1,
    author: 'Алексей М.',
    date: '2026-05-12',
    rating: 5,
    text: 'Заказал Nike Air Max 270 — пришли быстро, упаковка отличная. Размер совпал с таблицей, очень доволен.',
    product: 'Nike Air Max 270',
  },
  {
    id: 2,
    author: 'Мария К.',
    date: '2026-04-28',
    rating: 5,
    text: 'Уже третий заказ в Shoria. Всегда оригинал, доставка строго в срок, менеджер помог с выбором размера.',
    product: 'Adidas Ultraboost 22',
  },
  {
    id: 3,
    author: 'Дмитрий В.',
    date: '2026-05-20',
    rating: 4,
    text: 'Хорошие кроссовки, доставили за 2 дня. Качество на уровне — буду брать ещё.',
    product: 'New Balance 990v5',
  },
]

const staticLookbookIdeas = [
  {
    id: 1,
    title: 'Городской стиль',
    category: 'Lifestyle',
    image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
    link: '/catalog',
  },
  {
    id: 2,
    title: 'Спорт и движение',
    category: 'Running',
    image: 'https://images.unsplash.com/photo-1556906781-9a414e2a7735?auto=format&fit=crop&w=800&q=80',
    link: '/catalog',
  },
]

const staticLookbookCapsules = [
  {
    id: 3,
    title: 'Монохром и минимализм',
    category: 'Капсула',
    image: 'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?auto=format&fit=crop&w=800&q=80',
    link: '/catalog',
  },
  {
    id: 4,
    title: 'Яркий сезон',
    category: 'Капсула',
    image: 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=800&q=80',
    link: '/catalog',
  },
]

const currentLookbook = computed(() =>
  selectedLookbookTab.value === 'ideas' ? staticLookbookIdeas : staticLookbookCapsules,
)

const staticCollections = [
  {
    id: 1,
    title: 'Бег и тренировки',
    description: 'Кроссовки с поддержкой свода стопы для ежедневных тренировок.',
    link: '/catalog',
    images: [
      'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=240&q=70',
    ],
  },
  {
    id: 2,
    title: 'Городская жизнь',
    description: 'Стильные модели для прогулок, встреч и повседневной носки.',
    link: '/catalog',
    images: [
      'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?auto=format&fit=crop&w=240&q=70',
    ],
  },
  {
    id: 3,
    title: 'Новинки сезона',
    description: 'Свежие поступления: лимитированные коллаборации и актуальные релизы.',
    link: '/catalog',
    images: [
      'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=240&q=70',
      'https://images.unsplash.com/photo-1600185365926-3a2ce3cdb9eb?auto=format&fit=crop&w=240&q=70',
    ],
  },
]

// Иконки для категорий (по индексу)
const categoryIcons: Component[] = [Zap, Wind, Mountain, Trophy, ShieldCheck, Flame, Sparkles, Dumbbell]
const categoryColors = [
  '#EAF6FF', '#EAF8EE', '#FFF5E5', '#F0F0FF',
  '#FFF0F0', '#EAF6FF', '#EAF8EE', '#FFF5E5',
]
const categoryIconColors = [
  '#35A8F5', '#3DBE58', '#FFAA33', '#7C6FCD',
  '#FF5A5A', '#35A8F5', '#3DBE58', '#FFAA33',
]

// --------------- Теги / фильтры featured ---------------
const FEATURED_TAG_ORDER = ['hit', 'new', 'sale', 'customer_choice'] as const
const FEATURED_TAG_LABELS: Record<string, string> = {
  hit: 'Хиты',
  new: 'Новинки',
  sale: 'Акции',
  customer_choice: 'Выбор',
}

const tagsPresentInFeatured = computed(() => {
  const set = new Set<string>()
  for (const p of state.value.featured_products) {
    for (const t of p.tags ?? []) set.add(t.code)
  }
  return FEATURED_TAG_ORDER.filter((c) => set.has(c)).map((c) => ({
    code: c,
    label: FEATURED_TAG_LABELS[c] ?? c,
  }))
})

const filteredFeaturedProducts = computed(() => {
  const list = state.value.featured_products
  if (selectedFeaturedTagFilter.value === 'all') return list
  const code = selectedFeaturedTagFilter.value
  return list.filter((p) => p.tags?.some((t) => t.code === code))
})

watch(selectedFeaturedTagFilter, () => {
  requestAnimationFrame(() => {
    featuredSlider.value?.scrollTo({ left: 0, behavior: 'auto' })
  })
})

// --------------- Баннеры / Hero ---------------
const heroBanners = computed<Banner[]>(() => {
  if (state.value.banners?.length) return state.value.banners
  if (state.value.banner) return [state.value.banner]
  return [fallbackBanner]
})

// --------------- Spotlight ---------------
const spotlightProduct = computed<Product | null>(() => {
  const hits = state.value.featured_products.filter((p) => p.tags?.some((t) => t.code === 'hit'))
  return hits[0] ?? state.value.featured_products[0] ?? null
})

// --------------- Trust / Why ---------------
type TrustItem = { icon: Component; title: string; text: string }
const trustItems: TrustItem[] = [
  { icon: Truck, title: 'Доставка по РФ', text: 'Прозрачные сроки и стоимость до оплаты.' },
  { icon: RotateCcw, title: 'Лёгкий возврат', text: 'Понятные условия без лишних шагов.' },
  { icon: BadgeCheck, title: 'Оригинальные товары', text: 'Наличие и размеры обновляются из API.' },
  { icon: Headphones, title: 'Поддержка 7 дней', text: 'Оперативные ответы по заказам и размерам.' },
]

type WhyItem = { icon: Component; title: string; metric: string; text: string }
const whyItems: WhyItem[] = [
  { icon: Clock, title: 'Доставка без сюрпризов', metric: '1–5 дней по РФ', text: 'Показываем срок и стоимость заранее, без скрытых доплат на последнем шаге.' },
  { icon: ShieldCheck, title: 'Возврат и обмен', metric: '14 дней', text: 'Понятная процедура: заявка, подтверждение, быстрый статус.' },
  { icon: MessageCircle, title: 'Поддержка покупателя', metric: 'SLA до 30 мин', text: 'Помогаем с размером, оплатой и вопросами по текущему заказу.' },
  { icon: CreditCard, title: 'Прозрачная оплата', metric: 'SSL + чек', text: 'Безопасный checkout, корректные суммы и документы по каждой покупке.' },
]

// --------------- Hero навигация ---------------
function updateHeroIndexFromScroll() {
  const el = heroSlider.value
  const n = heroBanners.value.length
  if (!el || n < 2) return
  heroActiveIndex.value = Math.min(Math.max(0, Math.round(el.scrollLeft / (el.clientWidth || 1))), n - 1)
}

function goHeroSlide(index: number) {
  const el = heroSlider.value
  if (!el) return
  el.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' })
}

function stepHero(direction: 'prev' | 'next') {
  const n = heroBanners.value.length
  if (n < 2) return
  goHeroSlide(
    direction === 'next'
      ? Math.min(heroActiveIndex.value + 1, n - 1)
      : Math.max(heroActiveIndex.value - 1, 0),
  )
}

function scrollSlider(target: HTMLElement | null, direction: 'prev' | 'next') {
  if (!target) return
  const shift = Math.max(target.clientWidth * 0.85, 280)
  target.scrollBy({ left: direction === 'next' ? shift : -shift, behavior: 'smooth' })
}

// --------------- Banner link helpers ---------------
function resolveBannerHref(banner: Banner) {
  return banner.cta_url?.trim() || '/catalog'
}
function isInternal(href: string) {
  return href.startsWith('/')
}
function bannerLinkTag(banner: Banner) {
  return isInternal(resolveBannerHref(banner)) ? RouterLink : 'a'
}
function bannerLinkProps(banner: Banner) {
  const href = resolveBannerHref(banner)
  if (isInternal(href)) return { to: href }
  return { href, target: '_blank', rel: 'noopener noreferrer' }
}

// --------------- Загрузка данных ---------------
async function loadHome(retry = true) {
  try {
    const data = await fetchJson<HomePayload>('/api/home')
    const isDegraded =
      data.featured_products.length === 0 &&
      data.categories.length === 0 &&
      !data.banner &&
      !(data.banners?.length)
    if (isDegraded && retry) {
      await new Promise((resolve) => setTimeout(resolve, 1200))
      return loadHome(false)
    }
    state.value = data
    hasError.value = false
    void trackEvent('view_home', {
      featured_count: data.featured_products.length,
      categories_count: data.categories.length,
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
    state.value = fallback
  }
}

async function loadBrands() {
  try {
    brands.value = (await fetchJson<Brand[]>('/api/brands')).slice(0, 8)
  } catch {
    brands.value = []
  }
}

onMounted(async () => {
  recentlyViewed.value = readRecentlyViewed()
  isLoading.value = true
  await Promise.all([loadHome(), loadBrands()])
  isLoading.value = false
  await nextTick()
  window.scrollTo(0, 0)
  requestAnimationFrame(() => window.scrollTo(0, 0))
})

// --------------- Рассылка ---------------
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
      body: JSON.stringify({ email, source: 'home', attribution: captureFirstTouchAttribution() }),
    })
    newsletterState.value = 'success'
    newsletterMessage.value = response.message
    newsletterEmail.value = ''
    toast.success(response.message)
    void trackEvent('subscribe_newsletter', { source: 'home', status: response.status })
  } catch {
    newsletterState.value = 'error'
    newsletterMessage.value = 'Не удалось оформить подписку. Проверь email и попробуй снова.'
    toast.error('Не удалось оформить подписку.')
  }
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: 'long' }).format(new Date(value))
}

function formatPrice(value: number) {
  return new Intl.NumberFormat('ru-RU').format(value)
}
</script>

<template>
  <main class="home home-premier">

    <!-- ======================== СКЕЛЕТОН ======================== -->
    <template v-if="isLoading">
      <div class="hp-hero hp-hero--skeleton" aria-hidden="true">
        <AppSkeleton width="100%" height="clamp(400px, 56vh, 560px)" radius="0" />
      </div>
      <div class="hp-container">
        <div class="hp-trust hp-trust--skeleton">
          <AppSkeleton v-for="i in 4" :key="i" width="100%" height="80px" radius="16px" />
        </div>
        <div class="hp-section-skeleton">
          <AppSkeleton width="220px" height="36px" radius="8px" />
          <div class="hp-grid-skeleton">
            <AppSkeleton v-for="i in 10" :key="i" width="100%" height="260px" radius="16px" />
          </div>
        </div>
      </div>
    </template>

    <template v-else>

      <!-- ======================== 3. HERO SLIDER ======================== -->
      <section v-if="homeSectionOn('hero')" class="hp-hero" aria-label="Главный баннер">
        <div
          ref="heroSlider"
          class="hp-hero__track"
          @scroll.passive="updateHeroIndexFromScroll"
        >
          <article
            v-for="(banner, index) in heroBanners"
            :key="`banner-${index}`"
            class="hp-hero__slide"
            :style="{
              '--hero-bg': banner.bg_color ?? '#EAF6FF',
              '--hero-img': resolveBackgroundImage(banner.image_url),
            }"
          >
            <component
              :is="bannerLinkTag(banner)"
              class="hp-hero__link"
              v-bind="bannerLinkProps(banner)"
            >
              <div class="hp-hero__content">
                <span class="hp-hero__eyebrow">Интернет-магазин Shoria</span>
                <h1 class="hp-hero__title">{{ banner.title ?? 'Shoria' }}</h1>
                <p class="hp-hero__subtitle">{{ banner.subtitle ?? 'Магазин кроссовок с живым API.' }}</p>
                <div class="hp-hero__actions">
                  <span class="hp-btn hp-btn--primary">{{ banner.cta_label ?? 'Смотреть каталог' }}</span>
                  <span class="hp-btn hp-btn--secondary">Новинки</span>
                </div>
              </div>
              <div v-if="banner.image_url" class="hp-hero__image-wrap" aria-hidden="true">
                <img
                  :src="resolveImageSrc(banner.image_url)"
                  :alt="banner.title ?? ''"
                  class="hp-hero__img"
                  @error="applyImageFallback"
                />
              </div>
            </component>
          </article>
        </div>

        <!-- Точки -->
        <div v-if="heroBanners.length > 1" class="hp-hero__dots" role="tablist">
          <button
            v-for="(_, i) in heroBanners"
            :key="`dot-${i}`"
            type="button"
            class="hp-hero__dot"
            :class="{ 'hp-hero__dot--active': heroActiveIndex === i }"
            :aria-label="`Слайд ${i + 1}`"
            @click="goHeroSlide(i)"
          />
        </div>

        <!-- Стрелки -->
        <div v-if="heroBanners.length > 1" class="hp-hero__arrows">
          <button type="button" class="hp-hero__arrow" aria-label="Назад" @click="stepHero('prev')">
            <ChevronLeft :size="20" :stroke-width="2" />
          </button>
          <button type="button" class="hp-hero__arrow" aria-label="Вперёд" @click="stepHero('next')">
            <ChevronRight :size="20" :stroke-width="2" />
          </button>
        </div>
      </section>

      <div class="hp-container">

        <!-- ======================== 4. КАТЕГОРИИ (круговые иконки) ======================== -->
        <section v-if="homeSectionOn('categories') && state.categories.length" class="hp-cat-nav" aria-label="Категории">
          <RouterLink
            v-for="(cat, idx) in state.categories.slice(0, 10)"
            :key="`catnav-${cat.id}`"
            :to="`/catalog/${cat.slug}`"
            class="hp-cat-nav__item"
          >
            <span
              class="hp-cat-nav__circle"
              :style="{ background: categoryColors[idx % categoryColors.length] }"
            >
              <component
                :is="categoryIcons[idx % categoryIcons.length]"
                :size="22"
                :stroke-width="1.75"
                :style="{ color: categoryIconColors[idx % categoryIconColors.length] }"
                aria-hidden="true"
              />
            </span>
            <span class="hp-cat-nav__label">{{ cat.name }}</span>
          </RouterLink>
        </section>

        <!-- ======================== 5. ПРЕИМУЩЕСТВА ======================== -->
        <section v-if="homeSectionOn('trust')" class="hp-trust" aria-label="Преимущества">
          <article v-for="item in trustItems" :key="item.title" class="hp-trust__card">
            <span class="hp-trust__icon-wrap" aria-hidden="true">
              <component :is="item.icon" :size="20" :stroke-width="1.75" />
            </span>
            <div>
              <p class="hp-trust__title">{{ item.title }}</p>
              <p class="hp-trust__text">{{ item.text }}</p>
            </div>
          </article>
        </section>

        <!-- ======================== 6. ЛУЧШИЕ ПРЕДЛОЖЕНИЯ ======================== -->
        <section v-if="homeSectionOn('featured')" class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Лучшие предложения</h2>
              <p class="hp-section__sub">Подборка актуальных моделей по лучшим ценам.</p>
            </div>
            <RouterLink to="/catalog" class="hp-section__more">
              Смотреть все <ArrowRight :size="14" :stroke-width="2" aria-hidden="true" />
            </RouterLink>
          </div>

          <!-- Таб-фильтры -->
          <div v-if="state.featured_products.length" class="hp-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="hp-tab"
              :class="{ 'hp-tab--active': selectedFeaturedTagFilter === 'all' }"
              @click="selectedFeaturedTagFilter = 'all'"
            >
              Все
            </button>
            <button
              v-for="tag in tagsPresentInFeatured"
              :key="`tag-${tag.code}`"
              type="button"
              role="tab"
              class="hp-tab"
              :class="{ 'hp-tab--active': selectedFeaturedTagFilter === tag.code }"
              @click="selectedFeaturedTagFilter = tag.code"
            >
              {{ tag.label }}
            </button>
          </div>

          <!-- Сетка товаров -->
          <div v-if="filteredFeaturedProducts.length" ref="featuredSlider" class="hp-products-grid">
            <UnifiedProductCard
              v-for="product in filteredFeaturedProducts.slice(0, 10)"
              :key="`feat-${product.id}`"
              :product="product"
              source="home_featured"
              :show-image-skeleton="false"
            />
          </div>
          <div v-else-if="state.featured_products.length" class="hp-empty">
            <p>Нет товаров с выбранной меткой.</p>
            <RouterLink to="/catalog" class="hp-btn hp-btn--secondary">Перейти в каталог</RouterLink>
          </div>
          <div v-else class="hp-empty">
            <p>Пока нет товаров для главной. Назначь их в админке.</p>
          </div>
        </section>

        <!-- ======================== 7. МОЗАИКА ПОДБОРОК ======================== -->
        <section
          v-if="homeSectionOn('marketing') && (state.marketing_cards?.length ?? 0) > 0"
          class="hp-section"
          aria-label="Подборки"
        >
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Подборки по интересам</h2>
              <p class="hp-section__sub">Тематические коллекции для быстрого выбора.</p>
            </div>
          </div>
          <div class="hp-mosaic">
            <component
              :is="card.link_url.startsWith('/') ? RouterLink : 'a'"
              v-for="(card, idx) in state.marketing_cards"
              :key="`mkt-${card.id}`"
              class="hp-mosaic__tile"
              :class="{ 'hp-mosaic__tile--tall': idx === 0 }"
              v-bind="card.link_url.startsWith('/') ? { to: card.link_url } : { href: card.link_url, target: '_blank', rel: 'noopener noreferrer' }"
              :style="{ '--mkt-bg': resolveBackgroundImage(card.image_url) }"
            >
              <div class="hp-mosaic__scrim" aria-hidden="true" />
              <div class="hp-mosaic__body">
                <span v-if="card.label" class="hp-badge hp-badge--white">{{ card.label }}</span>
                <p class="hp-mosaic__title">{{ card.title }}</p>
              </div>
            </component>
          </div>
        </section>

        <!-- ======================== 8. FEATURED SPOTLIGHT ======================== -->
        <section v-if="spotlightProduct" class="hp-section hp-spotlight" aria-label="Спецпредложение">
          <div class="hp-spotlight__image-col">
            <span class="hp-badge hp-badge--primary hp-spotlight__badge">Спецпредложение</span>
            <img
              :src="resolveImageSrc(spotlightProduct.image_url)"
              :alt="spotlightProduct.name"
              class="hp-spotlight__img"
              loading="lazy"
              @error="applyImageFallback"
            />
          </div>
          <div class="hp-spotlight__content">
            <p v-if="spotlightProduct.brand" class="hp-spotlight__brand">{{ spotlightProduct.brand }}</p>
            <h2 class="hp-spotlight__name">{{ spotlightProduct.name }}</h2>
            <ul class="hp-spotlight__features">
              <li v-for="feat in ['Оригинальный товар', 'Доставка 1–5 дней', 'Возврат 14 дней']" :key="feat">
                <Check :size="14" :stroke-width="2.5" aria-hidden="true" />
                {{ feat }}
              </li>
            </ul>
            <div class="hp-spotlight__pricing">
              <span class="hp-spotlight__price">{{ formatPrice(spotlightProduct.price) }} ₽</span>
              <span v-if="spotlightProduct.old_price" class="hp-spotlight__old-price">
                {{ formatPrice(spotlightProduct.old_price) }} ₽
              </span>
            </div>
            <RouterLink :to="`/product/${spotlightProduct.slug}`" class="hp-btn hp-btn--primary hp-spotlight__cta">
              Смотреть товар
            </RouterLink>
          </div>
        </section>

        <!-- ======================== 9. БРЕНДЫ ======================== -->
        <section v-if="homeSectionOn('brands') && brands.length" class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Бренды</h2>
              <p class="hp-section__sub">Ведущие производители из нашего каталога.</p>
            </div>
            <div class="hp-section__head-right">
              <RouterLink to="/brands" class="hp-section__more">
                Смотреть все <ArrowRight :size="14" :stroke-width="2" />
              </RouterLink>
              <div class="hp-slider-nav">
                <button type="button" class="hp-slider-btn" aria-label="Назад" @click="scrollSlider(brandsSlider, 'prev')">
                  <ChevronLeft :size="16" :stroke-width="2" />
                </button>
                <button type="button" class="hp-slider-btn" aria-label="Вперёд" @click="scrollSlider(brandsSlider, 'next')">
                  <ChevronRight :size="16" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>
          <div ref="brandsSlider" class="hp-brands-slider">
            <RouterLink
              v-for="brand in brands.slice(0, 4)"
              :key="`brand-${brand.id}`"
              :to="{ path: '/catalog', query: { brands: brand.name } }"
              class="hp-brand-card"
            >
              <div class="hp-brand-card__img-wrap">
                <img
                  :src="resolveImageSrc(brand.image_url)"
                  :alt="brand.name"
                  class="hp-brand-card__img"
                  loading="lazy"
                  @error="applyImageFallback"
                />
              </div>
              <div class="hp-brand-card__body">
                <span class="hp-brand-card__name">{{ brand.name }}</span>
                <span class="hp-brand-card__count">{{ brand.products_count }} моделей</span>
                <ArrowRight :size="16" :stroke-width="2" class="hp-brand-card__arrow" aria-hidden="true" />
              </div>
            </RouterLink>
          </div>
        </section>

        <!-- ======================== 10. ОБРАЗЫ (ЛУКБУК) ======================== -->
        <section class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Образы</h2>
              <p class="hp-section__sub">Вдохновение для сборки образа вокруг кроссовок.</p>
            </div>
          </div>
          <div class="hp-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="hp-tab"
              :class="{ 'hp-tab--active': selectedLookbookTab === 'ideas' }"
              @click="selectedLookbookTab = 'ideas'"
            >
              Идеи и стиль
            </button>
            <button
              type="button"
              role="tab"
              class="hp-tab"
              :class="{ 'hp-tab--active': selectedLookbookTab === 'capsules' }"
              @click="selectedLookbookTab = 'capsules'"
            >
              Модные капсулы
            </button>
          </div>
          <div class="hp-lookbook">
            <RouterLink
              v-for="item in currentLookbook"
              :key="`look-${item.id}`"
              :to="item.link"
              class="hp-lookbook__card"
              :style="{ '--look-img': `url('${item.image}')` }"
            >
              <div class="hp-lookbook__scrim" aria-hidden="true" />
              <div class="hp-lookbook__body">
                <span class="hp-badge hp-badge--white">{{ item.category }}</span>
                <p class="hp-lookbook__title">{{ item.title }}</p>
                <span class="hp-lookbook__btn" aria-hidden="true">
                  <ArrowRight :size="16" :stroke-width="2" />
                </span>
              </div>
            </RouterLink>
          </div>
        </section>

        <!-- ======================== 11. ОТЗЫВЫ ======================== -->
        <section class="hp-section hp-reviews">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Отзывы покупателей</h2>
              <p class="hp-section__sub">Реальный опыт клиентов Shoria.</p>
            </div>
          </div>
          <div class="hp-reviews__grid">
            <article v-for="review in staticReviews" :key="review.id" class="hp-review-card">
              <div class="hp-review-card__header">
                <div class="hp-review-card__avatar">{{ review.author.charAt(0) }}</div>
                <div>
                  <p class="hp-review-card__author">{{ review.author }}</p>
                  <p class="hp-review-card__date">{{ formatDate(review.date) }}</p>
                </div>
                <div class="hp-review-card__stars" aria-label="Рейтинг: {{ review.rating }} из 5">
                  <Star
                    v-for="s in 5"
                    :key="s"
                    :size="14"
                    :stroke-width="1.5"
                    :fill="s <= review.rating ? 'currentColor' : 'none'"
                    aria-hidden="true"
                  />
                </div>
              </div>
              <p class="hp-review-card__text">{{ review.text }}</p>
              <div class="hp-review-card__product">
                <ShoppingBag :size="14" :stroke-width="1.75" aria-hidden="true" />
                {{ review.product }}
              </div>
            </article>
          </div>
        </section>

        <!-- ======================== 12. ПОПУЛЯРНЫЕ КАТЕГОРИИ ======================== -->
        <section v-if="homeSectionOn('categories') && state.categories.length" class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Популярные категории</h2>
              <p class="hp-section__sub">Выбирай по стилю и сценарию носки.</p>
            </div>
            <RouterLink to="/catalog" class="hp-section__more">
              Весь каталог <ArrowRight :size="14" :stroke-width="2" />
            </RouterLink>
          </div>
          <div class="hp-cat-grid">
            <RouterLink
              v-for="cat in state.categories.slice(0, 10)"
              :key="`catgrid-${cat.id}`"
              :to="`/catalog/${cat.slug}`"
              class="hp-cat-tile"
            >
              <img
                :src="resolveImageSrc(cat.image_url)"
                :alt="cat.name"
                class="hp-cat-tile__img"
                loading="lazy"
                @error="applyImageFallback"
              />
              <div class="hp-cat-tile__scrim" aria-hidden="true" />
              <div class="hp-cat-tile__body">
                <span class="hp-cat-tile__name">{{ cat.name }}</span>
                <span class="hp-cat-tile__arrow" aria-hidden="true">
                  <ArrowRight :size="14" :stroke-width="2.5" />
                </span>
              </div>
            </RouterLink>
          </div>
        </section>

        <!-- ======================== 13. КОЛЛЕКЦИИ ======================== -->
        <section class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Коллекции</h2>
              <p class="hp-section__sub">Тематические подборки для любого случая.</p>
            </div>
          </div>
          <div class="hp-collections">
            <article v-for="col in staticCollections" :key="col.id" class="hp-collection-card">
              <div class="hp-collection-card__images">
                <img
                  v-for="(img, i) in col.images"
                  :key="i"
                  :src="img"
                  :alt="`${col.title} фото ${i + 1}`"
                  class="hp-collection-card__thumb"
                  loading="lazy"
                />
              </div>
              <h3 class="hp-collection-card__title">{{ col.title }}</h3>
              <p class="hp-collection-card__desc">{{ col.description }}</p>
              <RouterLink :to="col.link" class="hp-collection-card__link">
                Смотреть подборку <ArrowRight :size="14" :stroke-width="2" />
              </RouterLink>
            </article>
          </div>
        </section>

        <!-- ======================== 14. НЕДАВНО ПРОСМОТРЕННЫЕ ======================== -->
        <section v-if="homeSectionOn('recent') && recentlyViewed.length" class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Недавно просмотренные</h2>
              <p class="hp-section__sub">Быстрый возврат к моделям, которые вы уже смотрели.</p>
            </div>
            <div class="hp-slider-nav">
              <button type="button" class="hp-slider-btn" aria-label="Назад" @click="scrollSlider(recentSlider, 'prev')">
                <ChevronLeft :size="16" :stroke-width="2" />
              </button>
              <button type="button" class="hp-slider-btn" aria-label="Вперёд" @click="scrollSlider(recentSlider, 'next')">
                <ChevronRight :size="16" :stroke-width="2" />
              </button>
            </div>
          </div>
          <div ref="recentSlider" class="hp-products-row">
            <UnifiedProductCard
              v-for="product in recentlyViewed"
              :key="`recent-${product.id}`"
              :product="product"
              source="home_recent"
              :show-image-skeleton="false"
              class="hp-products-row__item"
            />
          </div>
        </section>

        <!-- ======================== 15. ПОЧЕМУ SHORIA ======================== -->
        <section v-if="homeSectionOn('why')" class="hp-section hp-why">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Почему Shoria</h2>
              <p class="hp-section__sub">Коротко о том, что снижает риск покупателя.</p>
            </div>
          </div>
          <div class="hp-why__grid">
            <article v-for="item in whyItems" :key="item.title" class="hp-why__card">
              <span class="hp-why__icon-wrap" aria-hidden="true">
                <component :is="item.icon" :size="18" :stroke-width="1.75" />
              </span>
              <p class="hp-why__metric">{{ item.metric }}</p>
              <h3 class="hp-why__title">{{ item.title }}</h3>
              <p class="hp-why__text">{{ item.text }}</p>
            </article>
          </div>
          <div class="hp-why__cta-row">
            <RouterLink to="/catalog" class="hp-btn hp-btn--primary">Перейти к выбору моделей</RouterLink>
          </div>
        </section>

        <!-- ======================== 16. ПОДПИСКА ======================== -->
        <section v-if="homeSectionOn('newsletter')" class="hp-newsletter" aria-label="Подписка">
          <div class="hp-newsletter__inner">
            <div class="hp-newsletter__text">
              <h2 class="hp-newsletter__title">Подписка на дропы и скидки</h2>
              <p class="hp-newsletter__sub">Получай подборки и важные релизы раньше всех.</p>
            </div>
            <form class="hp-newsletter__form" @submit.prevent="subscribeToNewsletter">
              <label class="sr-only" for="newsletter-email">Email</label>
              <input
                id="newsletter-email"
                v-model="newsletterEmail"
                type="email"
                autocomplete="email"
                class="hp-newsletter__input"
                placeholder="name@example.com"
                :disabled="newsletterState === 'loading'"
                required
              />
              <button type="submit" class="hp-btn hp-btn--dark" :disabled="newsletterState === 'loading'">
                {{ newsletterState === 'loading' ? 'Отправляем…' : 'Подписаться' }}
              </button>
            </form>
            <p
              v-if="newsletterMessage"
              :class="['hp-newsletter__msg', newsletterState === 'error' ? 'hp-newsletter__msg--error' : 'hp-newsletter__msg--ok']"
            >
              {{ newsletterMessage }}
            </p>
          </div>
        </section>

        <!-- ======================== 17. НОВОСТИ ======================== -->
        <section v-if="homeSectionOn('news')" class="hp-section">
          <div class="hp-section__head">
            <div>
              <h2 class="hp-section__title">Новости и подборки</h2>
              <p class="hp-section__sub">Актуальный контент о трендах и релизах.</p>
            </div>
            <RouterLink to="/news" class="hp-section__more">
              Все новости <ArrowRight :size="14" :stroke-width="2" />
            </RouterLink>
          </div>
          <div v-if="state.news.length" class="hp-news-grid">
            <article v-for="post in state.news" :key="post.id" class="hp-news-card">
              <RouterLink :to="{ name: 'news-post', params: { slug: post.slug } }" class="hp-news-card__link">
                <div class="hp-news-card__img-wrap">
                  <img
                    :src="resolveImageSrc(post.cover_url)"
                    :alt="post.title"
                    class="hp-news-card__img"
                    loading="lazy"
                    @error="applyImageFallback"
                  />
                </div>
                <div class="hp-news-card__body">
                  <p class="hp-news-card__date">{{ formatDate(post.published_at) }}</p>
                  <h3 class="hp-news-card__title">{{ post.title }}</h3>
                  <p class="hp-news-card__excerpt">{{ post.excerpt }}</p>
                  <span class="hp-news-card__read">Читать <ArrowRight :size="13" :stroke-width="2" /></span>
                </div>
              </RouterLink>
            </article>
          </div>
          <div v-else class="hp-empty">
            <p>Контент-блок готов к SEO-материалам. Добавь новости в админке.</p>
          </div>
        </section>

      </div><!-- /hp-container -->
    </template>

    <p v-if="hasError" class="hp-api-warn">
      API пока недоступно, показаны демо-данные. Проверь VITE_API_URL и backend контейнер.
    </p>
  </main>
</template>

<style scoped>
/* ====================================================
   PREMIER TOKENS — scoped to .home-premier
   Переопределяем shadcn-vars только внутри главной,
   другие страницы не затронуты.
   ==================================================== */
.home-premier {
  --pr-primary:        #35A8F5;
  --pr-primary-hover:  #2495E2;
  --pr-primary-active: #1C85CC;
  --pr-primary-50:     #EAF6FF;
  --pr-primary-200:    #B4E0FD;

  --pr-page:           #FFFFFF;
  --pr-surface:        #FFFFFF;
  --pr-surface-muted:  #F7F8FA;
  --pr-surface-soft:   #F2F4F7;

  --pr-border:         #E6EAF0;
  --pr-border-strong:  #D5DBE5;

  --pr-text:           #222222;
  --pr-text-2:         #666666;
  --pr-text-muted:     #999999;
  --pr-text-inv:       #FFFFFF;

  --pr-success:        #3DBE58;
  --pr-success-bg:     #EAF8EE;
  --pr-danger:         #FF5A5A;
  --pr-danger-bg:      #FFF0F0;
  --pr-warning:        #FFAA33;
  --pr-warning-bg:     #FFF5E5;

  --pr-r-sm:   8px;
  --pr-r-md:   12px;
  --pr-r-lg:   16px;
  --pr-r-xl:   20px;
  --pr-r-pill: 999px;

  --pr-shadow-sm: 0 2px 8px rgba(20,30,50,.04);
  --pr-shadow-md: 0 8px 24px rgba(20,30,50,.08);
  --pr-shadow-lg: 0 16px 40px rgba(20,30,50,.12);

  --pr-t-fast: 120ms ease;
  --pr-t-base: 180ms ease;
  --pr-t-slow: 260ms ease;

  --pr-font-display: 'Manrope', 'Inter', sans-serif;
  --pr-font-sans:    'Inter', Arial, system-ui, sans-serif;

  /* Перебиваем shadcn-переменные для дочерних компонентов */
  --card:              #FFFFFF;
  --card-foreground:   #222222;
  --border:            #E6EAF0;
  --background:        #FFFFFF;
  --foreground:        #222222;
  --muted:             #F7F8FA;
  --muted-foreground:  #666666;
  --primary:           #35A8F5;

  font-family: var(--pr-font-sans);
  background: var(--pr-page);
  color: var(--pr-text);
  width: 100%;
  padding-bottom: 64px;
  overflow-anchor: none;
}

/* ====================================================
   SKELETON
   ==================================================== */
.hp-hero--skeleton { background: var(--pr-surface-muted); }
.hp-trust--skeleton { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; padding: 32px 0; }
.hp-section-skeleton { padding: 40px 0; display: flex; flex-direction: column; gap: 20px; }
.hp-grid-skeleton { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }

/* ====================================================
   LAYOUT
   ==================================================== */
.hp-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* ====================================================
   HERO
   ==================================================== */
.hp-hero {
  position: relative;
  width: 100%;
  overflow: hidden;
}

.hp-hero__track {
  display: flex;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  scrollbar-width: none;
}
.hp-hero__track::-webkit-scrollbar { display: none; }

.hp-hero__slide {
  flex: 0 0 100%;
  scroll-snap-align: start;
  background: var(--hero-bg, var(--pr-primary-50));
  min-height: clamp(400px, 52vh, 540px);
}

.hp-hero__link {
  display: flex;
  align-items: center;
  gap: 48px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 60px 24px;
  text-decoration: none;
  color: var(--pr-text);
  min-height: inherit;
}

.hp-hero__content {
  flex: 1;
  max-width: 520px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.hp-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--pr-font-sans);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--pr-primary);
  background: white;
  padding: 6px 14px;
  border-radius: var(--pr-r-pill);
  width: fit-content;
}

.hp-hero__title {
  font-family: var(--pr-font-display);
  font-size: clamp(36px, 5vw, 56px);
  font-weight: 800;
  line-height: 1.08;
  letter-spacing: -.02em;
  color: var(--pr-text);
  margin: 0;
}

.hp-hero__subtitle {
  font-size: 16px;
  line-height: 1.55;
  color: var(--pr-text-2);
  margin: 0;
}

.hp-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.hp-hero__image-wrap {
  flex: 0 0 auto;
  width: clamp(240px, 38%, 480px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.hp-hero__img {
  width: 100%;
  max-height: 420px;
  object-fit: contain;
  border-radius: var(--pr-r-xl);
  transition: transform var(--pr-t-slow);
}
.hp-hero__link:hover .hp-hero__img { transform: scale(1.04); }

.hp-hero__dots {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
}

.hp-hero__dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgba(0,0,0,.2);
  border: none;
  cursor: pointer;
  transition: all var(--pr-t-base);
}
.hp-hero__dot--active { width: 24px; background: var(--pr-primary); }

.hp-hero__arrows {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  transform: translateY(-50%);
  display: flex;
  justify-content: space-between;
  padding: 0 16px;
  pointer-events: none;
}

.hp-hero__arrow {
  pointer-events: all;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,.85);
  backdrop-filter: blur(4px);
  border: 1px solid var(--pr-border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--pr-text);
  transition: background var(--pr-t-fast), box-shadow var(--pr-t-fast);
}
.hp-hero__arrow:hover { background: #fff; box-shadow: var(--pr-shadow-sm); }

/* ====================================================
   КНОПКИ
   ==================================================== */
.hp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 20px;
  height: 44px;
  border-radius: var(--pr-r-md);
  font-family: var(--pr-font-sans);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: background var(--pr-t-fast), color var(--pr-t-fast), box-shadow var(--pr-t-fast);
  white-space: nowrap;
}
.hp-btn--primary {
  background: var(--pr-primary);
  color: var(--pr-text-inv);
}
.hp-btn--primary:hover { background: var(--pr-primary-hover); box-shadow: var(--pr-shadow-sm); }

.hp-btn--secondary {
  background: var(--pr-surface);
  color: var(--pr-text);
  border: 1px solid var(--pr-border);
}
.hp-btn--secondary:hover { border-color: var(--pr-primary-200); box-shadow: var(--pr-shadow-sm); }

.hp-btn--dark {
  background: var(--pr-text);
  color: var(--pr-text-inv);
}
.hp-btn--dark:hover { background: #333; }
.hp-btn:disabled { opacity: .55; cursor: not-allowed; }

/* ====================================================
   БЕЙДЖИ
   ==================================================== */
.hp-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: var(--pr-r-pill);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
  text-transform: uppercase;
  width: fit-content;
}
.hp-badge--primary { background: var(--pr-primary); color: white; }
.hp-badge--white { background: rgba(255,255,255,.9); color: var(--pr-text); }
.hp-badge--success { background: var(--pr-success-bg); color: var(--pr-success); }
.hp-badge--danger  { background: var(--pr-danger-bg); color: var(--pr-danger); }

/* ====================================================
   СЕКЦИИ — общий паттерн
   ==================================================== */
.hp-section {
  padding: 48px 0 0;
}

.hp-section__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.hp-section__head-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hp-section__title {
  font-family: var(--pr-font-display);
  font-size: 32px;
  font-weight: 700;
  letter-spacing: -.02em;
  color: var(--pr-text);
  margin: 0 0 6px;
  line-height: 1.15;
}

.hp-section__sub {
  font-size: 14px;
  color: var(--pr-text-2);
  margin: 0;
}

.hp-section__more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--pr-primary);
  text-decoration: none;
  white-space: nowrap;
  transition: opacity var(--pr-t-fast);
}
.hp-section__more:hover { opacity: .75; }

/* ====================================================
   КАТЕГОРИИ — КРУГОВЫЕ ИКОНКИ
   ==================================================== */
.hp-cat-nav {
  display: flex;
  gap: 8px;
  padding: 32px 0 0;
  overflow-x: auto;
  scrollbar-width: none;
}
.hp-cat-nav::-webkit-scrollbar { display: none; }

.hp-cat-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex: 0 0 auto;
  min-width: 72px;
  transition: opacity var(--pr-t-fast);
}
.hp-cat-nav__item:hover { opacity: .75; }

.hp-cat-nav__circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform var(--pr-t-base), box-shadow var(--pr-t-base);
}
.hp-cat-nav__item:hover .hp-cat-nav__circle {
  transform: translateY(-2px);
  box-shadow: var(--pr-shadow-md);
}

.hp-cat-nav__label {
  font-size: 12px;
  font-weight: 500;
  color: var(--pr-text-2);
  text-align: center;
  line-height: 1.3;
  max-width: 72px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ====================================================
   ПРЕИМУЩЕСТВА
   ==================================================== */
.hp-trust {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  padding: 32px 0 0;
}

.hp-trust__card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 20px;
  background: var(--pr-surface);
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-lg);
}

.hp-trust__icon-wrap {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border-radius: var(--pr-r-sm);
  background: var(--pr-primary-50);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pr-primary);
}

.hp-trust__title {
  font-size: 14px;
  font-weight: 600;
  color: var(--pr-text);
  margin: 0 0 4px;
}
.hp-trust__text {
  font-size: 13px;
  color: var(--pr-text-2);
  margin: 0;
  line-height: 1.5;
}

/* ====================================================
   ТАБЫ
   ==================================================== */
.hp-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}

.hp-tab {
  padding: 8px 18px;
  border-radius: var(--pr-r-pill);
  border: 1px solid var(--pr-border);
  background: var(--pr-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--pr-text-2);
  cursor: pointer;
  transition: all var(--pr-t-fast);
}
.hp-tab:hover { border-color: var(--pr-primary-200); color: var(--pr-primary); }
.hp-tab--active {
  background: var(--pr-primary);
  border-color: var(--pr-primary);
  color: white;
}

/* ====================================================
   СЕТКА ТОВАРОВ
   ==================================================== */
.hp-products-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
}

.hp-products-row {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  padding-bottom: 4px;
}
.hp-products-row::-webkit-scrollbar { display: none; }
.hp-products-row__item { flex: 0 0 220px; scroll-snap-align: start; }

/* ====================================================
   МОЗАИКА ПОДБОРОК
   ==================================================== */
.hp-mosaic {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-auto-rows: 220px;
  gap: 16px;
}

.hp-mosaic__tile {
  position: relative;
  border-radius: var(--pr-r-lg);
  overflow: hidden;
  background: var(--pr-surface-soft) var(--mkt-bg) center/cover no-repeat;
  text-decoration: none;
  cursor: pointer;
  transition: transform var(--pr-t-base);
}
.hp-mosaic__tile--tall { grid-row: span 2; }
.hp-mosaic__tile:hover { transform: translateY(-2px); }
.hp-mosaic__tile:hover .hp-mosaic__scrim { opacity: 1; }

.hp-mosaic__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.55) 0%, transparent 55%);
  opacity: .7;
  transition: opacity var(--pr-t-base);
}

.hp-mosaic__body {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hp-mosaic__title {
  font-family: var(--pr-font-display);
  font-size: 18px;
  font-weight: 700;
  color: white;
  margin: 0;
}

/* ====================================================
   SPOTLIGHT
   ==================================================== */
.hp-spotlight {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 48px;
  align-items: center;
  background: var(--pr-surface);
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-xl);
  padding: 48px;
  margin-top: 48px;
}

.hp-spotlight__image-col {
  position: relative;
  border-radius: var(--pr-r-lg);
  overflow: hidden;
  background: var(--pr-surface-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 360px;
}

.hp-spotlight__badge {
  position: absolute;
  top: 16px;
  left: 16px;
  z-index: 1;
}

.hp-spotlight__img {
  width: 100%;
  max-height: 380px;
  object-fit: contain;
  padding: 24px;
}

.hp-spotlight__content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.hp-spotlight__brand {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--pr-text-muted);
  margin: 0;
}

.hp-spotlight__name {
  font-family: var(--pr-font-display);
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -.02em;
  line-height: 1.1;
  color: var(--pr-text);
  margin: 0;
}

.hp-spotlight__features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.hp-spotlight__features li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--pr-text-2);
}
.hp-spotlight__features li svg { color: var(--pr-success); flex: 0 0 auto; }

.hp-spotlight__pricing {
  display: flex;
  align-items: baseline;
  gap: 12px;
}

.hp-spotlight__price {
  font-family: var(--pr-font-display);
  font-size: 32px;
  font-weight: 700;
  color: var(--pr-text);
}

.hp-spotlight__old-price {
  font-size: 18px;
  font-weight: 500;
  color: var(--pr-text-muted);
  text-decoration: line-through;
}

.hp-spotlight__cta { align-self: flex-start; height: 48px; padding: 0 28px; font-size: 15px; }

/* ====================================================
   БРЕНДЫ
   ==================================================== */
.hp-brands-slider {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.hp-brand-card {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-lg);
  overflow: hidden;
  background: var(--pr-surface);
  transition: border-color var(--pr-t-base), box-shadow var(--pr-t-base), transform var(--pr-t-base);
}
.hp-brand-card:hover {
  border-color: var(--pr-primary-200);
  box-shadow: var(--pr-shadow-md);
  transform: translateY(-2px);
}

.hp-brand-card__img-wrap {
  aspect-ratio: 16/9;
  overflow: hidden;
  background: var(--pr-surface-soft);
}
.hp-brand-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--pr-t-slow);
}
.hp-brand-card:hover .hp-brand-card__img { transform: scale(1.04); }

.hp-brand-card__body {
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.hp-brand-card__name {
  font-size: 15px;
  font-weight: 700;
  color: var(--pr-text);
  flex: 1;
}
.hp-brand-card__count {
  font-size: 12px;
  color: var(--pr-text-muted);
}
.hp-brand-card__arrow { color: var(--pr-text-muted); flex: 0 0 auto; }

/* ====================================================
   ЛУКБУК
   ==================================================== */
.hp-lookbook {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.hp-lookbook__card {
  position: relative;
  border-radius: var(--pr-r-xl);
  overflow: hidden;
  background: var(--pr-surface-soft) var(--look-img) center/cover no-repeat;
  min-height: 340px;
  display: flex;
  align-items: flex-end;
  text-decoration: none;
  cursor: pointer;
  transition: transform var(--pr-t-base);
}
.hp-lookbook__card:hover { transform: translateY(-2px); }
.hp-lookbook__card:hover .hp-lookbook__card-img { transform: scale(1.04); }

.hp-lookbook__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.6) 0%, transparent 55%);
}

.hp-lookbook__body {
  position: relative;
  padding: 28px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.hp-lookbook__title {
  font-family: var(--pr-font-display);
  font-size: 24px;
  font-weight: 700;
  color: white;
  margin: 0;
}

.hp-lookbook__btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,.9);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pr-text);
  align-self: flex-start;
}

/* ====================================================
   ОТЗЫВЫ
   ==================================================== */
.hp-reviews__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.hp-review-card {
  background: var(--pr-surface);
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-lg);
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: box-shadow var(--pr-t-base);
}
.hp-review-card:hover { box-shadow: var(--pr-shadow-sm); }

.hp-review-card__header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hp-review-card__avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--pr-primary-50);
  color: var(--pr-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex: 0 0 auto;
}

.hp-review-card__author {
  font-size: 14px;
  font-weight: 600;
  color: var(--pr-text);
  margin: 0;
}
.hp-review-card__date {
  font-size: 12px;
  color: var(--pr-text-muted);
  margin: 0;
}

.hp-review-card__stars {
  display: flex;
  gap: 2px;
  color: var(--pr-warning);
  margin-left: auto;
  flex: 0 0 auto;
}

.hp-review-card__text {
  font-size: 14px;
  line-height: 1.6;
  color: var(--pr-text-2);
  margin: 0;
  flex: 1;
}

.hp-review-card__product {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--pr-text-muted);
  padding-top: 14px;
  border-top: 1px solid var(--pr-border);
}

/* ====================================================
   КАТЕГОРИИ — ФОТО-СЕТКА
   ==================================================== */
.hp-cat-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-auto-rows: 180px;
  gap: 12px;
}

.hp-cat-tile {
  position: relative;
  border-radius: var(--pr-r-lg);
  overflow: hidden;
  text-decoration: none;
  cursor: pointer;
  transition: transform var(--pr-t-base);
}
.hp-cat-tile:hover { transform: translateY(-2px); }
.hp-cat-tile:hover .hp-cat-tile__img { transform: scale(1.06); }

.hp-cat-tile__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--pr-t-slow);
  background: var(--pr-surface-soft);
}

.hp-cat-tile__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.55) 0%, transparent 55%);
}

.hp-cat-tile__body {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 14px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.hp-cat-tile__name {
  font-size: 13px;
  font-weight: 700;
  color: white;
  line-height: 1.3;
}

.hp-cat-tile__arrow {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(255,255,255,.9);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pr-text);
  flex: 0 0 auto;
}

/* ====================================================
   КОЛЛЕКЦИИ
   ==================================================== */
.hp-collections {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.hp-collection-card {
  background: var(--pr-surface);
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-lg);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: border-color var(--pr-t-base), box-shadow var(--pr-t-base);
}
.hp-collection-card:hover { border-color: var(--pr-primary-200); box-shadow: var(--pr-shadow-sm); }

.hp-collection-card__images {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  border-radius: var(--pr-r-md);
  overflow: hidden;
  aspect-ratio: 2/1;
}

.hp-collection-card__thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  background: var(--pr-surface-soft);
}

.hp-collection-card__title {
  font-size: 16px;
  font-weight: 700;
  color: var(--pr-text);
  margin: 0;
  font-family: var(--pr-font-display);
}

.hp-collection-card__desc {
  font-size: 13px;
  line-height: 1.55;
  color: var(--pr-text-2);
  margin: 0;
  flex: 1;
}

.hp-collection-card__link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--pr-primary);
  text-decoration: none;
  transition: opacity var(--pr-t-fast);
}
.hp-collection-card__link:hover { opacity: .75; }

/* ====================================================
   ПОЧЕМУ SHORIA
   ==================================================== */
.hp-why {
  background: var(--pr-surface-muted);
  border-radius: var(--pr-r-xl);
  padding: 48px;
  margin-top: 48px;
}

.hp-why__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}

.hp-why__card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.hp-why__icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: var(--pr-r-sm);
  background: var(--pr-primary-50);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pr-primary);
  flex: 0 0 auto;
}

.hp-why__metric {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--pr-primary);
  margin: 0;
  background: white;
  padding: 3px 10px;
  border-radius: var(--pr-r-pill);
  width: fit-content;
}

.hp-why__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--pr-text);
  margin: 0;
  font-family: var(--pr-font-display);
}

.hp-why__text {
  font-size: 13px;
  line-height: 1.55;
  color: var(--pr-text-2);
  margin: 0;
}

.hp-why__cta-row {
  display: flex;
  justify-content: center;
}

/* ====================================================
   ПОДПИСКА
   ==================================================== */
.hp-newsletter {
  margin-top: 48px;
  background: var(--pr-primary);
  border-radius: var(--pr-r-xl);
  padding: 56px 48px;
}

.hp-newsletter__inner {
  max-width: 640px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
  text-align: center;
}

.hp-newsletter__title {
  font-family: var(--pr-font-display);
  font-size: 32px;
  font-weight: 700;
  color: white;
  margin: 0;
  letter-spacing: -.02em;
}

.hp-newsletter__sub {
  font-size: 15px;
  color: rgba(255,255,255,.8);
  margin: 0;
}

.hp-newsletter__text { display: flex; flex-direction: column; gap: 6px; }

.hp-newsletter__form {
  display: flex;
  gap: 12px;
  width: 100%;
}

.hp-newsletter__input {
  flex: 1;
  height: 48px;
  padding: 0 16px;
  border-radius: var(--pr-r-md);
  border: none;
  font-family: var(--pr-font-sans);
  font-size: 14px;
  outline: none;
  background: rgba(255,255,255,.95);
  color: var(--pr-text);
}
.hp-newsletter__input:focus { background: white; box-shadow: 0 0 0 3px rgba(255,255,255,.3); }

.hp-newsletter__msg { font-size: 13px; color: rgba(255,255,255,.9); margin: 0; }
.hp-newsletter__msg--error { color: #FFE0E0; }

/* ====================================================
   НОВОСТИ
   ==================================================== */
.hp-news-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.hp-news-card {
  background: var(--pr-surface);
  border: 1px solid var(--pr-border);
  border-radius: var(--pr-r-lg);
  overflow: hidden;
  transition: border-color var(--pr-t-base), box-shadow var(--pr-t-base), transform var(--pr-t-base);
}
.hp-news-card:hover {
  border-color: var(--pr-primary-200);
  box-shadow: var(--pr-shadow-md);
  transform: translateY(-2px);
}

.hp-news-card__link { text-decoration: none; display: flex; flex-direction: column; height: 100%; }

.hp-news-card__img-wrap { aspect-ratio: 16/10; overflow: hidden; background: var(--pr-surface-soft); }
.hp-news-card__img { width: 100%; height: 100%; object-fit: cover; transition: transform var(--pr-t-slow); }
.hp-news-card:hover .hp-news-card__img { transform: scale(1.04); }

.hp-news-card__body { padding: 20px; display: flex; flex-direction: column; gap: 8px; flex: 1; }

.hp-news-card__date { font-size: 12px; color: var(--pr-text-muted); margin: 0; }
.hp-news-card__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--pr-text);
  margin: 0;
  font-family: var(--pr-font-display);
  line-height: 1.4;
}
.hp-news-card__excerpt { font-size: 13px; color: var(--pr-text-2); margin: 0; line-height: 1.55; flex: 1; }
.hp-news-card__read {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--pr-primary);
  margin-top: auto;
  padding-top: 12px;
}

/* ====================================================
   СЛАЙДЕР-НАВИГАЦИЯ
   ==================================================== */
.hp-slider-nav { display: flex; gap: 6px; }
.hp-slider-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--pr-border);
  background: var(--pr-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--pr-text-2);
  transition: border-color var(--pr-t-fast), color var(--pr-t-fast), box-shadow var(--pr-t-fast);
}
.hp-slider-btn:hover { border-color: var(--pr-primary); color: var(--pr-primary); box-shadow: var(--pr-shadow-sm); }

/* ====================================================
   ПУСТЫЕ СОСТОЯНИЯ
   ==================================================== */
.hp-empty {
  padding: 40px;
  text-align: center;
  background: var(--pr-surface-muted);
  border-radius: var(--pr-r-lg);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  color: var(--pr-text-2);
  font-size: 14px;
}

/* ====================================================
   API WARN
   ==================================================== */
.hp-api-warn {
  max-width: 1280px;
  margin: 16px auto;
  padding: 12px 24px;
  background: var(--pr-warning-bg);
  color: var(--pr-warning-fg, #B66A00);
  border-radius: var(--pr-r-md);
  font-size: 13px;
}

/* ====================================================
   АДАПТИВ
   ==================================================== */
@media (max-width: 1100px) {
  .hp-products-grid { grid-template-columns: repeat(4, 1fr); }
  .hp-cat-grid { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 900px) {
  .hp-trust { grid-template-columns: repeat(2, 1fr); }
  .hp-products-grid { grid-template-columns: repeat(3, 1fr); }
  .hp-brands-slider { grid-template-columns: repeat(2, 1fr); }
  .hp-reviews__grid { grid-template-columns: 1fr 1fr; }
  .hp-collections { grid-template-columns: 1fr 1fr; }
  .hp-why__grid { grid-template-columns: 1fr 1fr; }
  .hp-why { padding: 32px; }
  .hp-spotlight { grid-template-columns: 1fr; padding: 32px; gap: 24px; }
  .hp-mosaic { grid-template-columns: 1fr 1fr; }
  .hp-news-grid { grid-template-columns: 1fr 1fr; }
  .hp-cat-grid { grid-template-columns: repeat(3, 1fr); }
  .hp-lookbook { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .hp-hero__link { flex-direction: column; padding: 40px 20px; gap: 24px; }
  .hp-hero__image-wrap { width: 100%; max-height: 200px; }
  .hp-trust { grid-template-columns: 1fr; }
  .hp-products-grid { grid-template-columns: repeat(2, 1fr); }
  .hp-section__title { font-size: 24px; }
  .hp-brands-slider { grid-template-columns: 1fr; }
  .hp-reviews__grid { grid-template-columns: 1fr; }
  .hp-collections { grid-template-columns: 1fr; }
  .hp-why__grid { grid-template-columns: 1fr; }
  .hp-why { padding: 24px; }
  .hp-newsletter { padding: 32px 24px; }
  .hp-newsletter__form { flex-direction: column; }
  .hp-news-grid { grid-template-columns: 1fr; }
  .hp-cat-grid { grid-template-columns: repeat(2, 1fr); }
  .hp-mosaic { grid-template-columns: 1fr; }
  .hp-mosaic__tile--tall { grid-row: span 1; }
  .hp-container { padding: 0 16px; }
  .hp-section { padding-top: 32px; }
}
</style>
