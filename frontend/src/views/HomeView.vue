<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { trackEvent } from '@/lib/analytics'
import { captureFirstTouchAttribution } from '@/lib/attribution'
import { fetchJson, requestJson } from '@/lib/api'
import { readRecentlyViewed, type RecentlyViewedItem } from '@/lib/recently-viewed'
import { getAppSessionId } from '@/lib/session'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'

type Category = {
  id: number
  name: string
  slug: string
  image_url: string | null
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
  tags?: {
    code: string
    label: string
  }[]
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

type HomePayload = {
  banner: Banner | null
  banners?: Banner[]
  categories: Category[]
  featured_products: Product[]
  news: NewsPost[]
}

type NewsletterSubscribeResponse = {
  ok: boolean
  status: 'subscribed' | 'already_subscribed'
  message: string
}

type PersonalRecommendationsPayload = {
  source: 'order_history' | 'view_history' | 'featured_fallback'
  data: Product[]
}

const fallbackBanner: Banner = {
  title: 'Shoria Spring Drop',
  subtitle: 'Текущая версия витрины загружена в демо-режиме, API подключается автоматически.',
  cta_label: 'Открыть каталог',
  cta_url: '/catalog',
  image_url: 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=1600&q=80',
  bg_color: '#F35B04',
}

const fallback: HomePayload = {
  banner: fallbackBanner,
  categories: [],
  featured_products: [],
  news: [],
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
const personalSlider = ref<HTMLElement | null>(null)
const brandsSlider = ref<HTMLElement | null>(null)
const personalRecommendations = ref<Product[]>([])
const brands = ref<Brand[]>([])
const personalRecommendationsSource = ref<'order_history' | 'view_history' | 'featured_fallback' | null>(null)
const heroBanners = computed<Banner[]>(() => {
  if (state.value.banners?.length) {
    return state.value.banners
  }

  if (state.value.banner) {
    return [state.value.banner]
  }

  return [fallbackBanner]
})
const trustHighlights = [
  {
    title: 'Доставка по РФ',
    text: 'Прозрачные сроки и стоимость доставки до оплаты.',
  },
  {
    title: 'Легкий возврат',
    text: 'Понятные условия возврата и обмена без лишних шагов.',
  },
  {
    title: 'Оригинальные товары',
    text: 'Контроль качества, размеры и наличие обновляются из API.',
  },
  {
    title: 'Поддержка 7 дней',
    text: 'Оперативные ответы по заказам, размерам и оплате.',
  },
]
const whyShoria = [
  {
    title: 'Доставка без сюрпризов',
    metric: '1-5 дней по РФ',
    text: 'Показываем срок и стоимость заранее, без скрытых доплат на последнем шаге.',
  },
  {
    title: 'Возврат и обмен',
    metric: '14 дней',
    text: 'Понятная процедура возврата: заявка, подтверждение, быстрый статус.',
  },
  {
    title: 'Поддержка до и после покупки',
    metric: 'SLA до 30 минут',
    text: 'Помогаем с подбором размера, оплатой и вопросами по текущему заказу.',
  },
  {
    title: 'Прозрачная оплата',
    metric: 'SSL + чек',
    text: 'Безопасный checkout, корректные суммы и документы по каждой покупке.',
  },
]
const appSessionId = getAppSessionId()

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

async function loadHome() {
  try {
    state.value = await fetchJson<HomePayload>('/api/home')
    hasError.value = false
    void trackEvent('view_home', {
      featured_count: state.value.featured_products.length,
      categories_count: state.value.categories.length,
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
    state.value = fallback
  } finally {
    isLoading.value = false
  }
}

async function loadPersonalRecommendations() {
  try {
    const payload = await fetchJson<PersonalRecommendationsPayload>(
      `/api/recommendations/personal?session_id=${encodeURIComponent(appSessionId)}`,
    )
    personalRecommendations.value = payload.data
    personalRecommendationsSource.value = payload.source
  } catch (error) {
    console.error(error)
    personalRecommendations.value = []
    personalRecommendationsSource.value = null
  }
}

async function loadBrands() {
  try {
    const payload = await fetchJson<Brand[]>('/api/brands')
    brands.value = payload.slice(0, 8)
  } catch (error) {
    console.error(error)
    brands.value = []
  }
}

onMounted(async () => {
  recentlyViewed.value = readRecentlyViewed()
  await loadHome()
  await loadPersonalRecommendations()
  await loadBrands()
})

async function subscribeToNewsletter() {
  const email = newsletterEmail.value.trim().toLowerCase()

  if (!email) {
    newsletterState.value = 'error'
    newsletterMessage.value = 'Укажи email для подписки.'
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

    void trackEvent('subscribe_newsletter', {
      source: 'home',
      status: response.status,
    })
  } catch (error) {
    console.error(error)
    newsletterState.value = 'error'
    newsletterMessage.value = 'Не удалось оформить подписку. Проверь email и попробуй снова.'
  }
}
</script>

<template>
  <main class="home">
    <template v-if="isLoading">
      <section class="hero-block hero-block--skeleton">
        <AppSkeleton width="100%" height="460px" radius="28px" />
      </section>

      <section class="trust trust--skeleton">
        <article v-for="index in 4" :key="`trust-skeleton-${index}`" class="trust-card trust-card--skeleton">
          <AppSkeleton width="42%" height="18px" />
          <AppSkeleton width="100%" height="14px" />
          <AppSkeleton width="82%" height="14px" />
        </article>
      </section>

      <section class="section">
        <header class="section__header">
          <AppSkeleton width="180px" height="32px" />
          <AppSkeleton width="320px" height="16px" />
        </header>
        <div class="category-grid">
          <article v-for="index in 4" :key="`category-skeleton-${index}`" class="card category-card category-card--skeleton">
            <AppSkeleton width="100%" height="180px" radius="22px 22px 0 0" />
            <div class="card__content">
              <AppSkeleton width="48%" height="20px" />
            </div>
          </article>
        </div>
      </section>

      <section class="section">
        <header class="section__header">
          <AppSkeleton width="140px" height="32px" />
          <AppSkeleton width="280px" height="16px" />
        </header>
        <div class="slider">
          <article v-for="index in 4" :key="`featured-skeleton-${index}`" class="slider-card slider-card--skeleton">
            <AppSkeleton width="100%" height="260px" radius="28px 28px 0 0" />
            <div class="slider-card__skeleton-body">
              <AppSkeleton width="28%" height="14px" />
              <AppSkeleton width="56%" height="24px" />
              <AppSkeleton width="34%" height="24px" />
              <AppSkeleton width="100%" height="52px" radius="16px" />
            </div>
          </article>
        </div>
      </section>

      <section class="section">
        <header class="section__header">
          <AppSkeleton width="220px" height="32px" />
          <AppSkeleton width="300px" height="16px" />
        </header>
        <div class="news-grid">
          <article v-for="index in 3" :key="`news-skeleton-${index}`" class="card news-card">
            <AppSkeleton width="100%" height="220px" radius="24px 24px 0 0" />
            <div class="card__content">
              <AppSkeleton width="24%" height="14px" />
              <AppSkeleton width="72%" height="24px" />
              <AppSkeleton width="100%" height="14px" />
              <AppSkeleton width="84%" height="14px" />
            </div>
          </article>
        </div>
      </section>
    </template>

    <template v-else>
    <section class="hero-block">
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(heroSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(heroSlider, 'next')">→</button>
      </div>
      <div ref="heroSlider" class="slider">
        <article
          v-for="(banner, index) in heroBanners"
          :key="`${banner.title}-${index}`"
          class="hero"
          :style="{
            '--hero-bg': banner.bg_color ?? '#ee4e34',
            '--hero-image': banner.image_url ? `url(${banner.image_url})` : 'none',
          }"
        >
          <component :is="bannerLinkTag(banner)" class="hero__link" v-bind="bannerLinkProps(banner)">
            <div class="hero__overlay" />
            <div class="hero__content">
              <p class="hero__eyebrow">SHORIA SNEAKER STORE</p>
              <h1>{{ banner.title ?? 'Shoria' }}</h1>
              <p class="hero__subtitle">
                {{ banner.subtitle ?? 'Магазин кроссовок с живым API и админкой на Filament.' }}
              </p>
              <span class="hero__cta">
                {{ banner.cta_label ?? 'Смотреть каталог' }}
              </span>
            </div>
          </component>
        </article>
      </div>
    </section>

    <section class="trust">
      <article v-for="item in trustHighlights" :key="item.title" class="trust-card">
        <h2>{{ item.title }}</h2>
        <p>{{ item.text }}</p>
      </article>
    </section>

    <section class="section">
      <header class="section__header">
        <h2>Категории</h2>
        <p>Быстрый выбор по стилю и сценарию носки.</p>
      </header>
      <div class="category-grid">
        <template v-if="state.categories.length">
          <article v-for="item in state.categories" :key="item.id" class="card category-card">
            <RouterLink class="category-link" :to="{ path: '/catalog', query: { category: item.slug } }">
              <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
              <div class="card__content">
                <h3>{{ item.name }}</h3>
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

    <section v-if="brands.length" class="section">
      <header class="section__header">
        <h2>Бренды</h2>
        <p>Популярные бренды из каталога.</p>
        <RouterLink class="section__more-link" to="/brands">Смотреть все бренды</RouterLink>
      </header>
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(brandsSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(brandsSlider, 'next')">→</button>
      </div>
      <div ref="brandsSlider" class="slider brands-slider">
        <RouterLink
          v-for="brand in brands"
          :key="`home-brand-${brand.id}`"
          :to="{ path: '/catalog', query: { brands: brand.name } }"
          class="brand-slide"
        >
          <img v-if="brand.image_url" :src="brand.image_url" :alt="brand.name" loading="lazy" />
          <div class="brand-slide__body">
            <h3>{{ brand.name }}</h3>
            <p>{{ brand.products_count }} товаров</p>
          </div>
        </RouterLink>
      </div>
    </section>

    <section class="section">
      <header class="section__header">
        <h2>Для вас</h2>
        <p>Товары с высоким приоритетом на главной.</p>
      </header>
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(featuredSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(featuredSlider, 'next')">→</button>
      </div>
      <div ref="featuredSlider" class="slider">
        <template v-if="state.featured_products.length">
          <UnifiedProductCard
            v-for="product in state.featured_products"
            :key="product.id"
            :product="product"
            source="home_featured"
            class="slider-card"
          />
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

    <section v-if="personalRecommendations.length" class="section">
      <header class="section__header">
        <h2>Рекомендуем для вас</h2>
        <p v-if="personalRecommendationsSource === 'order_history'">Подборка на основе ваших покупок.</p>
        <p v-else-if="personalRecommendationsSource === 'view_history'">Подборка на основе ваших просмотров.</p>
        <p v-else>Подборка популярных товаров.</p>
      </header>
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(personalSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(personalSlider, 'next')">→</button>
      </div>
      <div ref="personalSlider" class="slider">
        <UnifiedProductCard
          v-for="product in personalRecommendations"
          :key="`personal-${product.id}`"
          :product="product"
          source="home_personal"
          class="slider-card"
        />
      </div>
    </section>

    <section v-if="recentlyViewed.length" class="section">
      <header class="section__header">
        <h2>Недавно просмотренные</h2>
        <p>Быстрый возврат к моделям, которые вы уже смотрели.</p>
      </header>
      <div class="section__head-actions">
        <button type="button" class="slider-nav" @click="scrollSlider(recentSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(recentSlider, 'next')">→</button>
      </div>
      <div ref="recentSlider" class="slider">
        <UnifiedProductCard
          v-for="product in recentlyViewed"
          :key="`recent-${product.id}`"
          :product="product"
          source="home_recent"
          class="slider-card"
        />
      </div>
    </section>

    <section class="section why">
      <header class="section__header">
        <h2>Почему Shoria</h2>
        <p>Коротко о том, что снижает риск для покупателя и повышает конверсию.</p>
      </header>
      <div class="why-grid">
        <article v-for="item in whyShoria" :key="item.title" class="why-card">
          <p class="why-card__metric">{{ item.metric }}</p>
          <h3>{{ item.title }}</h3>
          <p>{{ item.text }}</p>
        </article>
      </div>
      <RouterLink class="why__cta" to="/catalog">Перейти к выбору моделей</RouterLink>
    </section>

    <section class="section capture">
      <header class="section__header">
        <h2>Подписка на дропы и скидки</h2>
        <p>Оставь email, чтобы получать подборки и важные релизы раньше.</p>
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

    <section class="section">
      <header class="section__header">
        <h2>Новости и подборки</h2>
        <p>Контент-блок для SEO и возвратного трафика.</p>
        <RouterLink class="section__more-link" to="/news">Все новости</RouterLink>
      </header>
      <div class="news-grid">
        <template v-if="state.news.length">
          <article v-for="post in state.news" :key="post.id" class="card news-card">
            <RouterLink class="news-link" :to="{ name: 'news-post', params: { slug: post.slug } }">
              <img v-if="post.cover_url" :src="post.cover_url" :alt="post.title" loading="lazy" />
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
    </template>

    <p v-if="hasError" class="status status--warn">
      API пока недоступно, показаны демо-данные. Проверь `VITE_API_URL` и backend контейнер.
    </p>
  </main>
</template>

<style scoped>
.home {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 28px 0 54px;
}

.hero-block {
  position: relative;
}

.hero-block--skeleton {
  margin-bottom: 16px;
}

.hero {
  position: relative;
  flex: 0 0 100%;
  overflow: hidden;
  border-radius: 28px;
  min-height: 460px;
  background: linear-gradient(135deg, var(--hero-bg), #1f2233 72%);
  color: #fffaf3;
  isolation: isolate;
}

.hero__link {
  display: block;
  min-height: inherit;
  color: inherit;
  text-decoration: none;
}

.hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: var(--hero-image);
  background-position: center;
  background-size: cover;
  opacity: 0.36;
  z-index: -2;
}

.hero__overlay {
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 20% 18%, rgb(255 255 255 / 30%), transparent 54%);
  z-index: -1;
}

.hero__content {
  max-width: 630px;
  padding: 54px clamp(24px, 5vw, 68px);
}

.hero__eyebrow {
  display: inline-block;
  margin-bottom: 18px;
  padding: 5px 12px;
  border: 1px solid rgb(255 255 255 / 55%);
  border-radius: 999px;
  letter-spacing: 0.08em;
  font-size: 12px;
}

.hero h1 {
  margin-bottom: 14px;
  font-size: clamp(42px, 7vw, 86px);
  font-family: var(--font-display);
  line-height: 0.95;
}

.hero__subtitle {
  max-width: 54ch;
  margin-bottom: 28px;
  font-size: clamp(15px, 2.2vw, 18px);
  color: rgb(255 250 243 / 92%);
}

.hero__cta {
  display: inline-block;
  padding: 12px 20px;
  border-radius: 999px;
  background: #fff;
  color: #131524;
  text-decoration: none;
  font-weight: 700;
}

.section__head-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin: -8px 0 10px;
}

.slider-nav {
  width: 36px;
  height: 36px;
  border: 1px solid #d8d0c4;
  border-radius: 50%;
  background: #fff;
  cursor: pointer;
}

.slider {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.slider::-webkit-scrollbar {
  display: none;
}

.slider > * {
  scroll-snap-align: start;
}

.slider-card--skeleton {
  flex: 0 0 clamp(260px, 28vw, 340px);
  border-radius: 28px;
  background: #fffdf9;
  border: 1px solid #efe2d4;
  overflow: hidden;
}

.slider-card__skeleton-body {
  display: grid;
  gap: 12px;
  padding: 18px;
}

.trust--skeleton .trust-card--skeleton {
  display: grid;
  gap: 10px;
}

.category-card--skeleton {
  overflow: hidden;
}

.section {
  margin-top: 44px;
}

.trust {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-top: 18px;
}

.trust-card {
  border-radius: 16px;
  background: #fff8ef;
  border: 1px solid #f2dcc0;
  padding: 14px 16px;
}

.trust-card h2 {
  font-size: 18px;
  line-height: 1.2;
}

.trust-card p {
  margin-top: 6px;
  color: var(--color-text-soft);
}

.section__header {
  margin-bottom: 18px;
}

.section__header h2 {
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 44px);
  line-height: 1;
}

.section__header p {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.section__more-link {
  display: inline-block;
  margin-top: 10px;
  color: #bf4b08;
  font-weight: 700;
  text-decoration: none;
}

.category-grid,
.news-grid {
  display: grid;
  gap: 16px;
}

.category-grid {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.slider-card {
  flex: 0 0 clamp(230px, 24vw, 300px);
}

.brands-slider {
  gap: 14px;
}

.brand-slide {
  flex: 0 0 calc((100% - 42px) / 4);
  min-width: 220px;
  border: 1px solid #eadbc8;
  border-radius: 20px;
  background: #fffdfa;
  overflow: hidden;
  color: inherit;
  text-decoration: none;
}

.brand-slide:hover {
  border-color: var(--color-accent, #bf4b08);
}

.brand-slide img {
  width: 100%;
  height: 140px;
  object-fit: cover;
}

.brand-slide__body {
  padding: 12px 14px 14px;
}

.brand-slide__body h3 {
  margin: 0;
  font-size: 20px;
}

.brand-slide__body p {
  margin-top: 6px;
  color: var(--color-text-soft);
  font-size: 14px;
}

.news-grid {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.why {
  padding: 22px;
  border-radius: 20px;
  background: linear-gradient(130deg, #fffdf8, #fff5ea);
  border: 1px solid #f0e1cc;
}

.why-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.why-card {
  padding: 14px;
  border-radius: 14px;
  border: 1px solid #efdcc4;
  background: rgb(255 255 255 / 75%);
}

.why-card__metric {
  color: #b55413;
  font-weight: 800;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.why__cta {
  display: inline-block;
  margin-top: 14px;
  color: #bf4b08;
  font-weight: 800;
  text-decoration: none;
}

.why__cta:hover {
  text-decoration: underline;
}

.capture {
  padding: 22px;
  border-radius: 20px;
  background: linear-gradient(130deg, #fff7ef, #fff1e4);
  border: 1px solid #f0dbc3;
}

.capture__form {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.capture__form input {
  flex: 1 1 260px;
  min-height: 44px;
  border-radius: 12px;
  border: 1px solid #d8cdbf;
  background: #fffdf9;
  padding: 0 12px;
}

.capture__form button {
  min-height: 44px;
  border: 0;
  border-radius: 12px;
  background: #f35b04;
  color: #fff;
  padding: 0 16px;
  font-weight: 700;
  cursor: pointer;
}

.capture__form button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.capture__message {
  margin-top: 10px;
  color: #2c7a4b;
}

.capture__message--error {
  color: #b8401a;
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
  border-radius: 18px;
  background: var(--card-bg);
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  transition: transform 0.2s ease;
}

.card:hover {
  transform: translateY(-3px);
}

.card img {
  width: 100%;
  height: 186px;
  object-fit: cover;
}

.card__content {
  padding: 14px 16px 16px;
}

.card h3 {
  margin-bottom: 5px;
  font-size: 20px;
  line-height: 1.2;
}

.category-link {
  display: block;
  color: inherit;
  text-decoration: none;
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
  color: #7f8ca8;
  font-size: 13px;
}

.empty-card {
  border: 1px dashed #d6d3cc;
  background: #fffcf6;
}

.empty-card a {
  display: inline-block;
  margin-top: 10px;
  color: #bf4b08;
  font-weight: 700;
  text-decoration: none;
}

.empty-card a:hover {
  text-decoration: underline;
}

.status {
  margin-top: 18px;
  color: #4d5b75;
}

.status--warn {
  color: #b95b09;
}

@media (max-width: 720px) {
  .hero {
    min-height: 380px;
  }

  .hero__content {
    padding: 30px 20px;
  }

  .brand-slide {
    flex: 0 0 calc((100% - 14px) / 2);
  }
}
</style>
