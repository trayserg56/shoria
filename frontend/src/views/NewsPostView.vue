<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppSkeleton from '@/components/AppSkeleton.vue'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { clearStructuredData, setSeoMeta, setStructuredData } from '@/lib/seo'
import {
  buildBreadcrumbStructuredData,
  buildNewsArticleStructuredData,
  buildNewsPostSeo,
} from '@/lib/seo-templates'
import { resolveNewsTypeMeta, type NewsContentType } from '@/lib/news-types'

type SpotlightProduct = {
  id: number
  name: string
  slug: string
  price: number
  old_price: number | null
  stock: number
  currency: string
  image_url: string | null
  reviews_summary?: {
    count: number
    average: number | null
  }
  category: {
    name: string
    slug: string
  } | null
}

type RelatedPost = {
  id: number
  title: string
  slug: string
  content_type: NewsContentType
  excerpt: string | null
  cover_url: string | null
  published_at: string
}

type NewsPostPayload = {
  id: number
  title: string
  slug: string
  content_type: NewsContentType
  excerpt: string | null
  content: string | null
  cover_url: string | null
  seo_title: string | null
  seo_description: string | null
  published_at: string
  related: RelatedPost[]
  spotlight_products: SpotlightProduct[]
}

const route = useRoute()
const post = ref<NewsPostPayload | null>(null)
const isLoading = ref(false)
const hasError = ref(false)
const contentHtml = computed(() => post.value?.content?.trim() || '<p>Контент скоро появится.</p>')
const contentTypeMeta = computed(() => resolveNewsTypeMeta(post.value?.content_type))
const spotlightProducts = computed(() => post.value?.spotlight_products ?? [])

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value))
}

async function loadPost() {
  const slug = String(route.params.slug ?? '')

  if (!slug) {
    return
  }

  isLoading.value = true

  try {
    post.value = await fetchJson<NewsPostPayload>(`/api/news/${encodeURIComponent(slug)}`)
    hasError.value = false

    const seoPayload = buildNewsPostSeo({
        slug: post.value.slug,
        title: post.value.title,
        excerpt: post.value.excerpt,
        section: resolveNewsTypeMeta(post.value.content_type).seoSection,
      })

    setSeoMeta({
      ...seoPayload,
      title: post.value.seo_title?.trim() || seoPayload.title,
      description: post.value.seo_description?.trim() || seoPayload.description,
    })
    setStructuredData([
      buildBreadcrumbStructuredData([
        { name: 'Главная', path: '/' },
        { name: 'Новости', path: '/news' },
        { name: post.value.title, path: `/news/${post.value.slug}` },
      ]),
      buildNewsArticleStructuredData({
        slug: post.value.slug,
        title: post.value.title,
        excerpt: post.value.excerpt,
        publishedAt: post.value.published_at,
        coverUrl: post.value.cover_url,
        schemaType: resolveNewsTypeMeta(post.value.content_type).articleSchemaType,
      }),
    ])
  } catch (error) {
    console.error(error)
    hasError.value = true
    post.value = null
    clearStructuredData()
  } finally {
    isLoading.value = false
  }
}

onMounted(loadPost)

watch(
  () => route.fullPath,
  async () => {
    await loadPost()
  },
)
</script>

<template>
  <main class="news-post-page">
    <article v-if="isLoading && !post" class="news-article-skeleton" aria-hidden="true">
      <AppSkeleton width="32%" height="14px" />
      <AppSkeleton width="16%" height="14px" />
      <AppSkeleton width="132px" height="28px" radius="999px" />
      <AppSkeleton width="72%" height="52px" />
      <AppSkeleton width="100%" height="18px" />
      <AppSkeleton width="84%" height="18px" />
      <AppSkeleton width="100%" height="360px" radius="28px" />
      <AppSkeleton width="100%" height="16px" />
      <AppSkeleton width="100%" height="16px" />
      <AppSkeleton width="92%" height="16px" />
      <AppSkeleton width="96%" height="16px" />
    </article>
    <p v-if="hasError" class="status status--warn">Новость не найдена или API недоступно.</p>

    <article v-if="post" class="news-article">
      <nav class="breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <RouterLink to="/news">Новости</RouterLink>
        <span>/</span>
        <span>{{ post.title }}</span>
      </nav>

      <p class="meta">{{ formatDate(post.published_at) }}</p>
      <span class="type-badge">{{ contentTypeMeta.label }}</span>
      <h1>{{ post.title }}</h1>
      <p v-if="post.excerpt" class="excerpt">{{ post.excerpt }}</p>

      <img :src="resolveImageSrc(post.cover_url)" :alt="post.title" loading="lazy" class="cover" @error="applyImageFallback" />

      <article class="content" v-html="contentHtml" />
    </article>

    <section v-if="spotlightProducts.length" class="spotlight">
      <div class="spotlight__intro" :class="`spotlight__intro--${post?.content_type}`">
        <div>
          <span class="spotlight__eyebrow">{{ contentTypeMeta.label }}</span>
          <h2>{{ contentTypeMeta.spotlightTitle }}</h2>
          <p>{{ contentTypeMeta.spotlightDescription }}</p>
        </div>
        <RouterLink :to="contentTypeMeta.spotlightCtaLink" class="spotlight__link">
          {{ contentTypeMeta.spotlightCtaLabel }}
        </RouterLink>
      </div>

      <div class="spotlight-grid">
        <UnifiedProductCard
          v-for="item in spotlightProducts"
          :key="item.id"
          :product="item"
          source="news-spotlight"
        />
      </div>
    </section>

    <section v-if="post?.related?.length" class="related">
      <header>
        <h2>Читайте также</h2>
      </header>
      <div class="related-grid">
        <article v-for="item in post.related" :key="item.id" class="related-card">
          <RouterLink :to="{ name: 'news-post', params: { slug: item.slug } }">
            <img :src="resolveImageSrc(item.cover_url)" :alt="item.title" loading="lazy" @error="applyImageFallback" />
            <div>
              <span class="related-type-badge">{{ resolveNewsTypeMeta(item.content_type).label }}</span>
              <p>{{ formatDate(item.published_at) }}</p>
              <h3>{{ item.title }}</h3>
              <p>{{ item.excerpt }}</p>
            </div>
          </RouterLink>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.news-post-page {
  width: min(980px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.status {
  color: #4d5b75;
}

.status--warn {
  color: #b95b09;
}

.news-article-skeleton {
  display: grid;
  gap: 16px;
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

.meta {
  color: #6f7b95;
}

.type-badge,
.related-type-badge {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  margin-top: 12px;
  padding: 0 12px;
  border-radius: 999px;
  background: #fff2e8;
  color: #c74803;
  font-size: 12px;
  font-weight: 800;
}

h1 {
  margin-top: 8px;
  font-family: var(--font-display);
  font-size: clamp(36px, 5vw, 62px);
  line-height: 0.95;
}

.excerpt {
  margin-top: 10px;
  font-size: 18px;
  color: #55627e;
}

.cover {
  width: 100%;
  margin-top: 18px;
  border-radius: 16px;
  aspect-ratio: 16 / 8;
  object-fit: cover;
}

.content {
  margin-top: 20px;
  font-size: 18px;
  line-height: 1.6;
}

.content :deep(p) {
  margin: 0 0 14px;
}

.content :deep(img) {
  width: 100%;
  max-width: 100%;
  height: auto;
  border-radius: 14px;
  margin: 14px 0;
  display: block;
}

.spotlight {
  margin-top: 34px;
}

.spotlight__intro {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 18px;
  padding: 24px;
  border-radius: 24px;
  background:
    radial-gradient(circle at top left, rgba(255, 201, 148, 0.45), transparent 34%),
    linear-gradient(135deg, rgba(255, 248, 240, 0.96), rgba(255, 255, 255, 0.92));
  border: 1px solid #eadfcf;
}

.spotlight__intro--guide {
  background:
    radial-gradient(circle at top left, rgba(129, 205, 255, 0.36), transparent 34%),
    linear-gradient(135deg, rgba(240, 248, 255, 0.98), rgba(255, 255, 255, 0.94));
}

.spotlight__intro--collection {
  background:
    radial-gradient(circle at top left, rgba(255, 211, 155, 0.4), transparent 34%),
    linear-gradient(135deg, rgba(255, 247, 238, 0.98), rgba(255, 255, 255, 0.94));
}

.spotlight__intro--promo {
  background:
    radial-gradient(circle at top left, rgba(255, 141, 102, 0.38), transparent 34%),
    linear-gradient(135deg, rgba(255, 241, 236, 0.98), rgba(255, 255, 255, 0.94));
}

.spotlight__eyebrow {
  display: inline-flex;
  min-height: 30px;
  align-items: center;
  padding: 0 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.86);
  color: #c74803;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.spotlight__intro h2 {
  margin-top: 12px;
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 42px);
  line-height: 0.95;
}

.spotlight__intro p {
  max-width: 620px;
  margin-top: 10px;
  color: #55627e;
  font-size: 17px;
}

.spotlight__link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 18px;
  border-radius: 999px;
  background: #23263a;
  color: #fff;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}

.spotlight-grid {
  margin-top: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 14px;
}

.related {
  margin-top: 34px;
}

.related h2 {
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 42px);
}

.related-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.related-card {
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
  border: 1px solid #eadfcf;
}

.related-card a {
  color: inherit;
  text-decoration: none;
}

.related-card img {
  width: 100%;
  height: 130px;
  object-fit: cover;
}

.related-card div {
  padding: 12px;
}

.related-card p {
  color: #6f7b95;
}

.related-card h3 {
  margin: 5px 0;
}

@media (max-width: 768px) {
  .spotlight__intro {
    align-items: start;
    flex-direction: column;
  }

  .spotlight__link {
    width: 100%;
  }
}
</style>
