<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { fetchJson } from '@/lib/api'
import { setSeoMeta } from '@/lib/seo'
import { buildNewsListSeoWithType } from '@/lib/seo-templates'
import { NEWS_CONTENT_TYPE_ORDER, resolveNewsTypeMeta, type NewsContentType } from '@/lib/news-types'

type NewsItem = {
  id: number
  title: string
  slug: string
  content_type: NewsContentType
  excerpt: string | null
  cover_url: string | null
  published_at: string
}

type NewsPaginated = {
  current_page: number
  last_page: number
  data: NewsItem[]
}

const route = useRoute()
const router = useRouter()
const state = ref<NewsPaginated>({
  current_page: 1,
  last_page: 1,
  data: [],
})
const isLoading = ref(false)
const hasError = ref(false)

const page = computed(() => Number(route.query.page ?? '1'))
const selectedType = computed<NewsContentType | null>(() => {
  const value = typeof route.query.type === 'string' ? route.query.type : null
  return NEWS_CONTENT_TYPE_ORDER.includes(value as NewsContentType) ? (value as NewsContentType) : null
})
const typeOptions = computed(() => [
  {
    value: null,
    label: 'Все материалы',
  },
  ...NEWS_CONTENT_TYPE_ORDER.map((type) => ({
    value: type,
    label: resolveNewsTypeMeta(type).label,
  })),
])
const selectedTypeMeta = computed(() =>
  selectedType.value ? resolveNewsTypeMeta(selectedType.value) : null,
)
const introText = computed(() => {
  if (!selectedTypeMeta.value) {
    return 'Материалы о трендах, новинках и практических гайдах.'
  }

  const descriptions: Record<NewsContentType, string> = {
    news: 'Свежие новости магазина, релизы и обновления ассортимента.',
    guide: 'Практические гайды, которые помогают выбрать товар и пользоваться им увереннее.',
    collection: 'Редакционные подборки с идеями, сочетаниями и сценариями покупки.',
    promo: 'Промо-материалы, акценты на специальных предложениях и сезонных коллекциях.',
  }

  return descriptions[selectedType.value as NewsContentType]
})

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value))
}

function newsTypeLabel(type: NewsContentType) {
  return resolveNewsTypeMeta(type).label
}

async function loadNews() {
  isLoading.value = true

  try {
    const params = new URLSearchParams()

    if (selectedType.value) {
      params.set('type', selectedType.value)
    }

    if (page.value > 1) {
      params.set('page', String(page.value))
    }

    state.value = await fetchJson<NewsPaginated>(`/api/news${params.size ? `?${params.toString()}` : ''}`)
    hasError.value = false
    setSeoMeta(
      buildNewsListSeoWithType({
        page: page.value,
        type: selectedType.value,
        section: selectedTypeMeta.value?.seoSection,
      }),
    )
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

function goToPage(nextPage: number) {
  const query: Record<string, string> = {}

  if (selectedType.value) {
    query.type = selectedType.value
  }

  if (nextPage > 1) {
    query.page = String(nextPage)
  }

  void router.push({
    path: '/news',
    query,
  })
}

function setTypeFilter(type: NewsContentType | null) {
  const query: Record<string, string> = {}

  if (type) {
    query.type = type
  }

  void router.push({
    path: '/news',
    query,
  })
}

onMounted(loadNews)

watch(
  () => route.fullPath,
  async () => {
    await loadNews()
  },
)
</script>

<template>
  <main class="news-page">
    <header class="news-header">
      <nav class="breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <span>Новости</span>
      </nav>
      <h1>{{ selectedTypeMeta ? selectedTypeMeta.seoSection : 'Новости и подборки' }}</h1>
      <p>{{ introText }}</p>
    </header>

    <section class="news-filters" aria-label="Тип материала">
      <button
        v-for="option in typeOptions"
        :key="option.label"
        type="button"
        class="news-filter-chip"
        :class="{ 'news-filter-chip--active': selectedType === option.value }"
        @click="setTypeFilter(option.value)"
      >
        {{ option.label }}
      </button>
    </section>

    <section v-if="state.data.length" class="news-grid">
      <article v-for="post in state.data" :key="post.id" class="news-card">
        <RouterLink :to="{ name: 'news-post', params: { slug: post.slug } }">
          <img v-if="post.cover_url" :src="post.cover_url" :alt="post.title" loading="lazy" />
          <div class="news-card__content">
            <div class="news-card__meta">
              <span class="news-type-badge">{{ newsTypeLabel(post.content_type) }}</span>
              <p>{{ formatDate(post.published_at) }}</p>
            </div>
            <h2>{{ post.title }}</h2>
            <p>{{ post.excerpt }}</p>
          </div>
        </RouterLink>
      </article>
    </section>

    <section v-else-if="!isLoading && !hasError" class="empty">
      <p>
        {{ selectedTypeMeta ? `Пока нет материалов типа «${selectedTypeMeta.label}».` : 'Пока нет опубликованных новостей.' }}
      </p>
      <RouterLink to="/">Вернуться на главную</RouterLink>
    </section>

    <footer v-if="state.last_page > 1" class="pagination">
      <button :disabled="state.current_page <= 1" @click="goToPage(state.current_page - 1)">Назад</button>
      <span>Страница {{ state.current_page }} из {{ state.last_page }}</span>
      <button :disabled="state.current_page >= state.last_page" @click="goToPage(state.current_page + 1)">Дальше</button>
    </footer>

    <p v-if="isLoading" class="status">Загружаем новости...</p>
    <p v-if="hasError" class="status status--warn">Ошибка загрузки новостей.</p>
  </main>
</template>

<style scoped>
.news-page {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
  color: var(--color-text-soft);
}

.breadcrumbs a {
  color: inherit;
}

.news-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 80px);
  line-height: 0.9;
}

.news-header p {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.news-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 22px;
}

.news-filter-chip {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid #decdb8;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
  color: var(--color-text);
  font: inherit;
  cursor: pointer;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.news-filter-chip:hover {
  transform: translateY(-1px);
  border-color: #f26a21;
  box-shadow: 0 10px 24px rgba(242, 106, 33, 0.12);
}

.news-filter-chip--active {
  border-color: #f26a21;
  background: #fff2e8;
  color: #c74803;
  box-shadow: 0 12px 26px rgba(242, 106, 33, 0.14);
}

.news-grid {
  margin-top: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 14px;
}

.news-card {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid #eadfcf;
  background: #fff;
}

.news-card a {
  display: block;
  color: inherit;
  text-decoration: none;
}

.news-card img {
  width: 100%;
  height: 180px;
  object-fit: cover;
}

.news-card__content {
  padding: 12px 14px;
}

.news-card__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.news-card__meta p {
  color: #6f7b95;
}

.news-type-badge {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  background: #fff2e8;
  color: #c74803;
  font-size: 12px;
  font-weight: 700;
}

.news-card__content > p:first-child {
  color: #6f7b95;
}

.news-card__content h2 {
  margin: 6px 0;
  font-size: 24px;
  line-height: 1.1;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 18px;
}

.pagination button {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  background: #fff;
  padding: 8px 12px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.status {
  margin-top: 10px;
  color: #4d5b75;
}

.status--warn {
  color: #b95b09;
}

.empty {
  margin-top: 16px;
}

.empty a {
  color: #1f2233;
}
</style>
