<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { fetchJson } from '@/lib/api'
import { clearStructuredData, setSeoMeta } from '@/lib/seo'

type ServicePagePayload = {
  id: number
  title: string
  slug: string
  excerpt: string | null
  content: string | null
  seo_title: string | null
  seo_description: string | null
  updated_at: string | null
}

const route = useRoute()
const page = ref<ServicePagePayload | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const contentHtml = computed(() => page.value?.content?.trim() || '<p>Контент скоро появится.</p>')

function formatDate(value: string | null) {
  if (!value) {
    return null
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value))
}

async function loadPage() {
  const slug = String(route.params.slug ?? '')

  if (!slug) {
    return
  }

  isLoading.value = true

  try {
    page.value = await fetchJson<ServicePagePayload>(`/api/pages/${encodeURIComponent(slug)}`)
    hasError.value = false

    const title = page.value.seo_title?.trim() || `${page.value.title} — Shoria`
    const description =
      page.value.seo_description?.trim()
      || page.value.excerpt?.trim()
      || 'Служебная информация интернет-магазина Shoria.'

    setSeoMeta({
      title,
      description,
      canonical: `${window.location.origin}/pages/${page.value.slug}`,
      robots: 'index,follow',
    })
    clearStructuredData()
  } catch (error) {
    console.error(error)
    hasError.value = true
    page.value = null
    setSeoMeta({
      title: 'Страница не найдена — Shoria',
      description: 'Служебная страница не найдена.',
      canonical: `${window.location.origin}/pages/${slug}`,
      robots: 'noindex,nofollow',
    })
    clearStructuredData()
  } finally {
    isLoading.value = false
  }
}

onMounted(loadPage)

watch(
  () => route.fullPath,
  async () => {
    await loadPage()
  },
)
</script>

<template>
  <main class="service-page">
    <article v-if="isLoading && !page" class="service-page__skeleton" aria-hidden="true">
      <AppSkeleton width="34%" height="14px" />
      <AppSkeleton width="42%" height="46px" />
      <AppSkeleton width="72%" height="20px" />
      <AppSkeleton width="100%" height="340px" radius="20px" />
    </article>

    <p v-if="hasError" class="service-page__status service-page__status--warn">
      Страница не найдена или API временно недоступно.
    </p>

    <article v-if="page" class="service-page__card">
      <nav class="service-page__breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <span>{{ page.title }}</span>
      </nav>

      <h1>{{ page.title }}</h1>
      <p v-if="page.excerpt" class="service-page__excerpt">{{ page.excerpt }}</p>
      <p v-if="page.updated_at" class="service-page__date">Обновлено: {{ formatDate(page.updated_at) }}</p>
      <article class="service-page__content" v-html="contentHtml" />
    </article>
  </main>
</template>

<style scoped>
.service-page {
  width: min(980px, 92vw);
  margin: 0 auto;
  padding: 24px 0 64px;
}

.service-page__skeleton {
  display: grid;
  gap: 14px;
}

.service-page__status {
  color: #516180;
}

.service-page__status--warn {
  color: #b95b09;
}

.service-page__card {
  padding: 24px;
  border: 1px solid #e0ddd6;
  border-radius: 24px;
  background: #fbfbfb;
}

.service-page__breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--color-text-soft);
}

.service-page__breadcrumbs a {
  color: inherit;
}

.service-page h1 {
  margin-bottom: 10px;
}

.service-page__excerpt {
  margin-bottom: 8px;
  color: #4d5b75;
}

.service-page__date {
  margin-bottom: 18px;
  font-size: 14px;
  color: #6f7b95;
}

.service-page__content {
  color: #1f2233;
  line-height: 1.6;
}

.service-page__content :deep(h2),
.service-page__content :deep(h3) {
  margin: 18px 0 10px;
}

.service-page__content :deep(p),
.service-page__content :deep(li) {
  margin-bottom: 10px;
}

.service-page__content :deep(ul),
.service-page__content :deep(ol) {
  padding-left: 20px;
  margin: 0 0 14px;
}
</style>

