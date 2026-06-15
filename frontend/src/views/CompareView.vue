<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { ShoppingCart, Trash2, X, ArrowLeft, Star, CheckCircle2 } from 'lucide-vue-next'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toast } from '@/lib/toast'
import { toProductRoute } from '@/lib/product-route'
import { useCompareStore } from '@/stores/compare'
import { useCartStore } from '@/stores/cart'
import { fetchJson } from '@/lib/api'

type ProductCharacteristic = {
  group: string | null
  name: string
  values: string[]
}

type ProductDetail = {
  slug: string
  sku: string | null
  characteristics: ProductCharacteristic[]
}

// Загруженные детали товаров (характеристики, артикул)
const productDetails = ref<Record<string, ProductDetail>>({})
const detailsLoading = ref(false)

const compareStore = useCompareStore()
const { items: compareItems } = storeToRefs(compareStore)
const cartStore = useCartStore()
const isBulkAdding = ref(false)
const addingIds = ref<Set<number>>(new Set())

function formatPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

async function addToCart(productSlug: string, productId: number) {
  addingIds.value = new Set([...addingIds.value, productId])
  try {
    await cartStore.addItemBySlug(productSlug, 1)
  } catch {
    /* тост из cart store */
  } finally {
    addingIds.value.delete(productId)
    addingIds.value = new Set(addingIds.value)
  }
}

async function addAllToCart() {
  if (!compareItems.value.length || isBulkAdding.value) return
  isBulkAdding.value = true
  let added = 0
  try {
    for (const item of compareItems.value) {
      try {
        await cartStore.addItemBySlug(item.slug, 1, undefined, { suppressSuccessToast: true })
        added += 1
      } catch (error) {
        console.error(error)
      }
    }
    if (added > 0) {
      toast.success(`Добавлено в корзину: ${added} товара`)
    }
  } finally {
    isBulkAdding.value = false
  }
}

// Строки сравнения — показываем только если хотя бы у одного товара есть значение
type CompareRow = {
  label: string
  key: string
  render: (item: (typeof compareItems.value)[0]) => string | null
}

// Базовые строки из данных карточки
const compareRows: CompareRow[] = [
  { label: 'Категория', key: 'category', render: (i) => i.category?.name ?? null },
  { label: 'Бренд', key: 'brand', render: (i) => i.brand ?? null },
  { label: 'Цена', key: 'price', render: (i) => new Intl.NumberFormat('ru-RU', { style: 'currency', currency: i.currency, maximumFractionDigits: 0 }).format(i.price) },
  { label: 'В наличии', key: 'stock', render: (i) => i.stock > 0 ? `${i.stock} шт.` : 'Нет в наличии' },
  { label: 'Рейтинг', key: 'rating', render: (i) => i.reviews_summary?.average != null ? `${i.reviews_summary.average} / 5 (${i.reviews_summary.count} отзывов)` : null },
  { label: 'Метки', key: 'tags', render: (i) => i.tags?.length ? i.tags.map((t) => t.label).join(', ') : null },
]

// Динамические строки из характеристик — все уникальные name по всем товарам
const characteristicRows = computed(() => {
  const names = new Map<string, string>() // key → label (name)
  for (const item of compareItems.value) {
    const chars = productDetails.value[item.slug]?.characteristics ?? []
    for (const c of chars) {
      const key = `char__${c.name}`
      if (!names.has(key)) names.set(key, c.name)
    }
  }
  return Array.from(names.entries()).map(([key, name]) => ({
    key,
    label: name,
    render: (item: (typeof compareItems.value)[0]) => {
      const chars = productDetails.value[item.slug]?.characteristics ?? []
      const found = chars.find((c) => c.name === name)
      return found ? found.values.join(', ') : null
    },
  }))
})

// Артикул — отдельно
const skuRow = computed(() => ({
  label: 'Артикул',
  key: 'sku',
  render: (item: (typeof compareItems.value)[0]) => productDetails.value[item.slug]?.sku ?? null,
}))

function rowHasAnyValue(row: { render: (i: (typeof compareItems.value)[0]) => string | null }): boolean {
  return compareItems.value.some((item) => row.render(item) !== null)
}

async function loadDetails() {
  detailsLoading.value = true
  const slugs = compareItems.value.map((i) => i.slug).filter((s) => !productDetails.value[s])
  await Promise.all(
    slugs.map(async (slug) => {
      try {
        const d = await fetchJson<ProductDetail>(`/api/products/${slug}`)
        productDetails.value[slug] = d
      } catch { /* тихо */ }
    }),
  )
  detailsLoading.value = false
}

onMounted(() => loadDetails())
watch(() => compareItems.value.map((i) => i.slug).join(','), () => loadDetails())

// Подсветка лучшей цены
function isBestPrice(item: (typeof compareItems.value)[0]): boolean {
  if (compareItems.value.length < 2) return false
  const min = Math.min(...compareItems.value.map((i) => i.price))
  return item.price === min
}
</script>

<template>
  <main class="compare-page">

    <!-- Хлебные крошки / навигация -->
    <nav class="compare-nav">
      <RouterLink to="/catalog" class="compare-nav__back">
        <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
        Каталог
      </RouterLink>
    </nav>

    <header class="compare-header">
      <h1>Сравнение товаров</h1>
      <p v-if="compareItems.length">{{ compareItems.length }} {{ compareItems.length === 1 ? 'товар' : compareItems.length < 5 ? 'товара' : 'товаров' }}</p>
    </header>

    <!-- Пустое состояние -->
    <section v-if="!compareItems.length" class="compare-empty">
      <div class="compare-empty__icon" aria-hidden="true">
        <CheckCircle2 :size="48" :stroke-width="1.25" />
      </div>
      <h2>Нет товаров для сравнения</h2>
      <p>Добавьте товары из каталога или карточки товара, нажав на иконку сравнения.</p>
      <RouterLink to="/catalog" class="compare-empty__cta">
        Перейти в каталог
      </RouterLink>
    </section>

    <template v-else>
      <!-- Кнопки действий -->
      <div class="compare-actions-bar">
        <button
          type="button"
          class="cmp-btn cmp-btn--primary"
          :disabled="isBulkAdding"
          @click="addAllToCart"
        >
          <ShoppingCart :size="16" :stroke-width="2" aria-hidden="true" />
          {{ isBulkAdding ? 'Добавляем…' : 'Добавить все в корзину' }}
        </button>
        <RouterLink to="/cart" class="cmp-btn cmp-btn--secondary">Перейти в корзину</RouterLink>
        <button type="button" class="cmp-btn cmp-btn--ghost" @click="compareStore.clear">
          <Trash2 :size="15" :stroke-width="2" aria-hidden="true" />
          Очистить сравнение
        </button>
      </div>

      <!-- Таблица сравнения -->
      <div class="compare-table-wrap">
        <table class="compare-table">

          <!-- Шапка с карточками товаров -->
          <thead>
            <tr class="cmp-row cmp-row--products">
              <th class="cmp-label" scope="col">Товар</th>
              <th
                v-for="item in compareItems"
                :key="`head-${item.id}`"
                class="cmp-cell cmp-product-card"
                scope="col"
              >
                <button
                  type="button"
                  class="cmp-product-card__remove"
                  :aria-label="`Убрать ${item.name} из сравнения`"
                  @click="compareStore.remove(item.id)"
                >
                  <X :size="14" :stroke-width="2.5" />
                </button>

                <RouterLink :to="toProductRoute(item)" class="cmp-product-card__img-link">
                  <img
                    :src="resolveImageSrc(item.image_url)"
                    :alt="item.name"
                    class="cmp-product-card__img"
                    loading="lazy"
                    @error="applyImageFallback"
                  />
                </RouterLink>

                <div class="cmp-product-card__info">
                  <p v-if="item.brand" class="cmp-product-card__brand">{{ item.brand }}</p>
                  <RouterLink :to="toProductRoute(item)" class="cmp-product-card__name">
                    {{ item.name }}
                  </RouterLink>

                  <!-- Рейтинг -->
                  <div v-if="item.reviews_summary?.count" class="cmp-product-card__rating">
                    <Star :size="12" :stroke-width="1.5" fill="currentColor" aria-hidden="true" />
                    <span>{{ item.reviews_summary.average?.toFixed(1) }}</span>
                    <span class="cmp-product-card__rating-count">{{ item.reviews_summary.count }} отзывов</span>
                  </div>
                </div>

                <!-- Цена -->
                <div class="cmp-product-card__pricing" :class="{ 'cmp-product-card__pricing--best': isBestPrice(item) }">
                  <span v-if="isBestPrice(item) && compareItems.length > 1" class="cmp-best-badge">Лучшая цена</span>
                  <span class="cmp-product-card__price">{{ formatPrice(item.price, item.currency) }}</span>
                  <span v-if="item.old_price" class="cmp-product-card__old-price">
                    {{ formatPrice(item.old_price, item.currency) }}
                  </span>
                </div>

                <!-- Кнопка в корзину -->
                <button
                  type="button"
                  class="cmp-btn cmp-btn--primary cmp-btn--full"
                  :disabled="addingIds.has(item.id)"
                  @click="addToCart(item.slug, item.id)"
                >
                  <ShoppingCart :size="14" :stroke-width="2" aria-hidden="true" />
                  {{ addingIds.has(item.id) ? 'Добавляем…' : 'В корзину' }}
                </button>
              </th>
            </tr>
          </thead>

          <!-- Строки характеристик -->
          <tbody>
            <!-- Базовые: категория, бренд, цена, наличие, рейтинг, метки -->
            <template v-for="row in compareRows" :key="row.key">
              <tr v-if="rowHasAnyValue(row)" class="cmp-row">
                <td class="cmp-label">{{ row.label }}</td>
                <td v-for="item in compareItems" :key="`${row.key}-${item.id}`" class="cmp-cell">
                  <span v-if="row.render(item)">{{ row.render(item) }}</span>
                  <span v-else class="cmp-cell--empty">—</span>
                </td>
              </tr>
            </template>

            <!-- Артикул -->
            <tr v-if="rowHasAnyValue(skuRow)" class="cmp-row">
              <td class="cmp-label">{{ skuRow.label }}</td>
              <td v-for="item in compareItems" :key="`sku-${item.id}`" class="cmp-cell">
                <span v-if="skuRow.render(item)">{{ skuRow.render(item) }}</span>
                <span v-else class="cmp-cell--empty">—</span>
              </td>
            </tr>

            <!-- Разделитель перед характеристиками -->
            <tr v-if="characteristicRows.length" class="cmp-row cmp-row--group-header">
              <td :colspan="compareItems.length + 1" class="cmp-group-label">Характеристики</td>
            </tr>

            <!-- Динамические характеристики -->
            <template v-for="row in characteristicRows" :key="row.key">
              <tr v-if="rowHasAnyValue(row)" class="cmp-row">
                <td class="cmp-label">{{ row.label }}</td>
                <td v-for="item in compareItems" :key="`${row.key}-${item.id}`" class="cmp-cell">
                  <span v-if="row.render(item)">{{ row.render(item) }}</span>
                  <span v-else class="cmp-cell--empty">—</span>
                </td>
              </tr>
            </template>

            <!-- Лоадер пока грузятся характеристики -->
            <tr v-if="detailsLoading" class="cmp-row">
              <td :colspan="compareItems.length + 1" class="cmp-loading">Загружаем характеристики…</td>
            </tr>
          </tbody>

        </table>
      </div>
    </template>
  </main>
</template>

<style scoped>
.compare-page {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 20px 0 64px;
}

/* ── Навигация ─────────────────────────────────── */
.compare-nav {
  margin-bottom: 20px;
}

.compare-nav__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted-foreground);
  text-decoration: none;
  transition: color 0.15s;
}
.compare-nav__back:hover { color: var(--foreground); }

/* ── Заголовок ─────────────────────────────────── */
.compare-header {
  margin-bottom: 20px;
}
.compare-header h1 {
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 42px);
  font-weight: 800;
  line-height: 1.1;
  margin: 0 0 4px;
}
.compare-header p {
  font-size: 14px;
  color: var(--muted-foreground);
  margin: 0;
}

/* ── Пустое состояние ──────────────────────────── */
.compare-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 24px;
  text-align: center;
}
.compare-empty__icon {
  color: var(--muted-foreground);
  opacity: 0.4;
}
.compare-empty h2 {
  font-size: 22px;
  font-weight: 700;
  margin: 0;
}
.compare-empty p {
  font-size: 14px;
  color: var(--muted-foreground);
  max-width: 380px;
  margin: 0;
  line-height: 1.6;
}
.compare-empty__cta {
  display: inline-flex;
  align-items: center;
  height: 44px;
  padding: 0 24px;
  background: var(--primary);
  color: var(--primary-foreground);
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: opacity 0.15s;
}
.compare-empty__cta:hover { opacity: 0.88; }

/* ── Кнопки действий ───────────────────────────── */
.compare-actions-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}

.cmp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 40px;
  padding: 0 16px;
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s, opacity 0.15s;
  white-space: nowrap;
}
.cmp-btn--primary {
  background: var(--primary);
  color: var(--primary-foreground);
}
.cmp-btn--primary:hover { opacity: 0.88; }
.cmp-btn--primary:disabled { opacity: 0.55; cursor: not-allowed; }

.cmp-btn--secondary {
  background: var(--background);
  color: var(--foreground);
  border-color: var(--border);
}
.cmp-btn--secondary:hover { border-color: var(--primary); color: var(--primary); }

.cmp-btn--ghost {
  background: transparent;
  color: var(--muted-foreground);
  border-color: var(--border);
}
.cmp-btn--ghost:hover { color: var(--foreground); border-color: color-mix(in srgb, var(--border), var(--foreground) 20%); }

.cmp-btn--full { width: 100%; margin-top: 10px; height: 44px; font-size: 14px; }

/* ── Таблица ───────────────────────────────────── */
.compare-table-wrap {
  overflow-x: auto;
  border-radius: 14px;
  border: 1px solid var(--border);
}

.compare-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  background: var(--card);
}

.cmp-row {
  border-bottom: 1px solid var(--border);
}
.cmp-row:last-child { border-bottom: none; }

.cmp-row--group-header td {
  padding: 10px 16px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--muted-foreground);
  background: color-mix(in srgb, var(--muted), transparent 20%);
  border-top: 2px solid var(--border);
}

.cmp-loading td {
  padding: 14px 16px;
  font-size: 13px;
  color: var(--muted-foreground);
  text-align: center;
}

/* Чётные строки — лёгкий фон */
tbody .cmp-row:nth-child(even) .cmp-cell,
tbody .cmp-row:nth-child(even) .cmp-label {
  background: color-mix(in srgb, var(--muted), transparent 55%);
}

.cmp-label {
  width: 160px;
  min-width: 140px;
  padding: 14px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted-foreground);
  vertical-align: middle;
  border-right: 1px solid var(--border);
  background: color-mix(in srgb, var(--muted), transparent 30%);
  text-align: left;
  white-space: nowrap;
}

.cmp-cell {
  padding: 14px 16px;
  font-size: 14px;
  color: var(--foreground);
  vertical-align: middle;
  border-right: 1px solid var(--border);
  min-width: 220px;
  text-align: left;
}
.cmp-cell:last-child { border-right: none; }
.cmp-cell--empty { color: var(--muted-foreground); }

/* ── Карточка товара (заголовок таблицы) ───────── */
.cmp-row--products th { vertical-align: top; }

.cmp-product-card {
  padding: 16px;
  position: relative;
}

.cmp-product-card__remove {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--background);
  color: var(--muted-foreground);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.cmp-product-card__remove:hover { color: var(--destructive, #ef4444); border-color: var(--destructive, #ef4444); }

.cmp-product-card__img-link {
  display: block;
  border-radius: 10px;
  overflow: hidden;
  background: var(--muted);
  margin-bottom: 12px;
}
.cmp-product-card__img {
  width: 100%;
  height: 160px;
  object-fit: contain;
  padding: 8px;
  transition: transform 0.25s ease;
}
.cmp-product-card__img-link:hover .cmp-product-card__img { transform: scale(1.04); }

.cmp-product-card__info { margin-bottom: 12px; }

.cmp-product-card__brand {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--muted-foreground);
  margin: 0 0 4px;
}
.cmp-product-card__name {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--foreground);
  text-decoration: none;
  line-height: 1.4;
  margin-bottom: 8px;
  transition: color 0.15s;
}
.cmp-product-card__name:hover { color: var(--primary); }

.cmp-product-card__rating {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: #f59e0b;
}
.cmp-product-card__rating-count { color: var(--muted-foreground); font-weight: 400; font-size: 12px; }

.cmp-product-card__pricing {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 2px;
  padding: 10px 12px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--muted), transparent 50%);
}
.cmp-product-card__pricing--best {
  background: color-mix(in srgb, var(--primary), transparent 88%);
  border: 1px solid color-mix(in srgb, var(--primary), transparent 70%);
}

.cmp-best-badge {
  width: 100%;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--primary);
}
.cmp-product-card__price {
  font-size: 20px;
  font-weight: 700;
  font-family: var(--font-display);
  color: var(--foreground);
}
.cmp-product-card__old-price {
  font-size: 14px;
  color: var(--muted-foreground);
  text-decoration: line-through;
}

@media (max-width: 720px) {
  .compare-table-wrap { border-radius: 10px; }
  .cmp-label { width: 110px; min-width: 100px; font-size: 12px; padding: 12px 10px; }
  .cmp-cell { min-width: 180px; padding: 12px 10px; font-size: 13px; }
}
</style>
