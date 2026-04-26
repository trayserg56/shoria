<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { fetchJson } from '@/lib/api'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { setSeoMeta } from '@/lib/seo'
import AppSkeleton from '@/components/AppSkeleton.vue'

type Brand = {
  id: number
  name: string
  slug: string
  image_url: string | null
  products_count: number
}

const brands = ref<Brand[]>([])
const isLoading = ref(false)
const hasError = ref(false)

const heroText = computed(() => {
  if (!brands.value.length) {
    return 'Подборки товаров по брендам из каталога.'
  }

  return `В каталоге доступно брендов: ${brands.value.length}.`
})

async function loadBrands() {
  isLoading.value = true

  try {
    brands.value = await fetchJson<Brand[]>('/api/brands')
    hasError.value = false
    setSeoMeta({
      title: 'Бренды — Shoria',
      description: 'Страница брендов магазина Shoria. Переходите в каталог выбранного бренда.',
    })
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

onMounted(loadBrands)
</script>

<template>
  <main class="brands-page">
    <header class="brands-header">
      <nav class="breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <span>Бренды</span>
      </nav>
      <h1>Бренды</h1>
      <p>{{ heroText }}</p>
    </header>

    <section v-if="isLoading" class="brands-grid" aria-hidden="true">
      <article v-for="index in 12" :key="`brand-skeleton-${index}`" class="brand-card">
        <AppSkeleton width="50%" height="26px" />
        <AppSkeleton width="40%" height="14px" />
        <AppSkeleton width="120px" height="40px" radius="999px" />
      </article>
    </section>

    <section v-else-if="brands.length" class="brands-grid">
      <RouterLink
        v-for="brand in brands"
        :key="brand.id"
        :to="{ path: '/catalog', query: { brands: brand.name } }"
        class="brand-card"
      >
        <img
          :src="resolveImageSrc(brand.image_url)"
          :alt="brand.name"
          loading="lazy"
          class="brand-card__image"
          @error="applyImageFallback"
        />
        <div class="brand-card__body">
          <h2>{{ brand.name }}</h2>
          <p>{{ brand.products_count }} товаров</p>
        </div>
      </RouterLink>
    </section>

    <section v-else-if="!hasError" class="empty">
      <p>Пока нет активных брендов с товарами.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>

    <p v-if="hasError" class="status status--warn">Не удалось загрузить список брендов.</p>
  </main>
</template>

<style scoped>
.brands-page {
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

.brands-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 80px);
  line-height: 0.9;
}

.brands-header p {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.brands-grid {
  margin-top: 28px;
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.brand-card {
  color: inherit;
  text-decoration: none;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 24px;
  overflow: hidden;
  display: grid;
  gap: 0;
}

.brand-card__image {
  width: 100%;
  height: 160px;
  object-fit: cover;
}

.brand-card__body {
  padding: 16px 18px 18px;
  display: grid;
  gap: 8px;
}

.brand-card h2 {
  margin: 0;
  font-size: 28px;
}

.brand-card p {
  margin: 0;
  color: var(--color-text-soft);
}

.brand-card:hover {
  border-color: var(--color-accent);
}

.empty {
  margin-top: 28px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-soft);
  display: grid;
  gap: 10px;
}

.status--warn {
  margin-top: 18px;
  color: #b45309;
}
</style>
