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

const groupedBrands = computed(() => {
  const map = new Map<string, Brand[]>()
  const sorted = [...brands.value].sort((a, b) =>
    a.name.localeCompare(b.name, 'ru', { sensitivity: 'base' }),
  )
  for (const brand of sorted) {
    const first = brand.name.charAt(0).toUpperCase()
    if (!map.has(first)) {
      map.set(first, [])
    }
    map.get(first)!.push(brand)
  }
  return map
})

const letters = computed(() => Array.from(groupedBrands.value.keys()))

function scrollToLetter(letter: string) {
  document.getElementById(`brands-letter-${letter}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

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
      <article v-for="index in 12" :key="`brand-skeleton-${index}`" class="brand-card brand-card--skeleton">
        <AppSkeleton class="brand-card__image brand-card__image--skeleton" width="100%" height="160px" radius="0" />
        <div class="brand-card__body">
          <AppSkeleton width="58%" height="28px" />
          <AppSkeleton width="34%" height="16px" />
        </div>
      </article>
    </section>

    <template v-else-if="brands.length">
      <nav class="brands-alphabet" aria-label="Алфавитный указатель">
        <button
          v-for="letter in letters"
          :key="letter"
          type="button"
          class="brands-alphabet__btn"
          @click="scrollToLetter(letter)"
        >
          {{ letter }}
        </button>
      </nav>

      <template v-for="letter in letters" :key="letter">
        <div :id="`brands-letter-${letter}`" class="brands-group">
          <h2 class="brands-group__letter">{{ letter }}</h2>
          <div class="brands-grid">
            <RouterLink
              v-for="brand in groupedBrands.get(letter)"
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
                <h3>{{ brand.name }}</h3>
                <p>{{ brand.products_count }} товаров</p>
              </div>
            </RouterLink>
          </div>
        </div>
      </template>
    </template>

    <section v-else-if="!hasError" class="empty">
      <p>Пока нет активных брендов с товарами.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>

    <p v-if="hasError" class="status status--warn">Не удалось загрузить список брендов.</p>
  </main>
</template>

<style scoped>
.brands-page {
  width: min(var(--layout-max-width), 92vw);
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

.brands-alphabet {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 28px;
  padding: 16px 20px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.brands-alphabet__btn {
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: transparent;
  color: var(--foreground);
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.18s ease,
    border-color 0.18s ease;
}

.brands-alphabet__btn:hover {
  background: var(--muted);
  border-color: var(--ring);
}

.brands-group {
  margin-top: 40px;
  scroll-margin-top: calc(var(--site-header-sticky-offset) + 16px);
}

.brands-group__letter {
  font-family: var(--font-display);
  font-size: clamp(32px, 5vw, 56px);
  line-height: 1;
  color: var(--muted-foreground);
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}

.brands-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.brand-card {
  color: inherit;
  text-decoration: none;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 24px;
  overflow: hidden;
  display: grid;
  gap: 0;
  transition: border-color 0.18s ease;
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

.brand-card h3 {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--foreground);
}

.brand-card p {
  margin: 0;
  color: var(--color-text-soft);
  font-size: 14px;
}

.brand-card:hover {
  border-color: var(--ring);
}

.brand-card--skeleton {
  pointer-events: none;
}

.brand-card__image--skeleton {
  display: block;
}

.empty {
  margin-top: 28px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--color-text-soft);
  display: grid;
  gap: 10px;
}

.status--warn {
  margin-top: 18px;
  color: #b45309;
}
</style>
