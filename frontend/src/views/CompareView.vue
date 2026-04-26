<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toProductRoute } from '@/lib/product-route'
import { useCompareStore } from '@/stores/compare'
import { useCartStore } from '@/stores/cart'

const compareStore = useCompareStore()
const { items: compareItems } = storeToRefs(compareStore)
const cartStore = useCartStore()
const cartMessage = ref('')
const isBulkAdding = ref(false)

function formatPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

async function addToCart(productSlug: string) {
  try {
    await cartStore.addItemBySlug(productSlug, 1)
    cartMessage.value = 'Товар добавлен в корзину.'
  } catch {
    cartMessage.value = 'Не удалось добавить товар в корзину. Попробуйте еще раз.'
  }
}

async function addAllToCart() {
  if (!compareItems.value.length || isBulkAdding.value) {
    return
  }

  isBulkAdding.value = true
  let added = 0

  try {
    for (const item of compareItems.value) {
      try {
        await cartStore.addItemBySlug(item.slug, 1)
        added += 1
      } catch (error) {
        console.error(error)
      }
    }

    cartMessage.value =
      added > 0
        ? `Добавили в корзину ${added} ${added === 1 ? 'товар' : added < 5 ? 'товара' : 'товаров'}.`
        : 'Не удалось добавить товары в корзину. Попробуйте еще раз.'
  } finally {
    isBulkAdding.value = false
  }
}

onMounted(() => {
  compareStore.hydrate()
})
</script>

<template>
  <main class="compare-page">
    <header class="compare-header">
      <h1>Сравнение</h1>
      <p>Сравнивайте товары рядом по ключевым параметрам.</p>
    </header>
    <p v-if="cartMessage" class="status">{{ cartMessage }}</p>
    <div v-if="compareItems.length" class="compare-head-actions">
      <button type="button" :disabled="isBulkAdding" @click="addAllToCart">
        {{ isBulkAdding ? 'Добавляем...' : 'Добавить все в корзину' }}
      </button>
      <button type="button" @click="compareStore.clear">Очистить сравнение</button>
      <RouterLink to="/cart">Перейти в корзину</RouterLink>
    </div>

    <section v-if="compareItems.length" class="compare-table">
      <div class="compare-row compare-row--header">
        <div class="compare-label">Параметр</div>
        <article v-for="item in compareItems" :key="item.id" class="compare-cell compare-product">
          <RouterLink :to="toProductRoute(item)">
            <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
            <h3>{{ item.name }}</h3>
          </RouterLink>
          <div class="compare-actions">
            <button type="button" class="compare-actions__cart" @click="addToCart(item.slug)">В корзину</button>
            <button type="button" @click="compareStore.remove(item.id)">Убрать</button>
          </div>
        </article>
      </div>

      <div class="compare-row">
        <div class="compare-label">Категория</div>
        <div v-for="item in compareItems" :key="`category-${item.id}`" class="compare-cell">
          {{ item.category?.name ?? '—' }}
        </div>
      </div>

      <div class="compare-row">
        <div class="compare-label">Цена</div>
        <div v-for="item in compareItems" :key="`price-${item.id}`" class="compare-cell">
          <strong>{{ formatPrice(item.price, item.currency) }}</strong>
          <p v-if="item.old_price" class="old-price">{{ formatPrice(item.old_price, item.currency) }}</p>
        </div>
      </div>

      <div class="compare-row">
        <div class="compare-label">В наличии</div>
        <div v-for="item in compareItems" :key="`stock-${item.id}`" class="compare-cell">
          {{ item.stock }} шт.
        </div>
      </div>

      <div class="compare-row">
        <div class="compare-label">Теги</div>
        <div v-for="item in compareItems" :key="`tags-${item.id}`" class="compare-cell">
          <span v-if="item.tags?.length">{{ item.tags.map((tag) => tag.label).join(', ') }}</span>
          <span v-else>—</span>
        </div>
      </div>
    </section>

    <section v-else class="empty">
      <p>Добавьте товары в сравнение из каталога или карточки товара.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>
  </main>
</template>

<style scoped>
.compare-page {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.compare-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 78px);
  line-height: 0.9;
}

.compare-header p {
  margin-top: 6px;
  color: var(--color-text-soft);
}

.compare-table {
  margin-top: 16px;
  border: 1px solid #e5dfd5;
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
}

.status {
  margin-top: 10px;
  color: #4a5e7e;
}

.compare-head-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 10px 0 14px;
}

.compare-head-actions button,
.compare-head-actions a {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  color: #1f2233;
  font: inherit;
  text-decoration: none;
  cursor: pointer;
}

.compare-row {
  display: grid;
  grid-template-columns: 220px repeat(auto-fit, minmax(220px, 1fr));
  border-top: 1px solid #eee7dd;
}

.compare-row:first-child {
  border-top: none;
}

.compare-label {
  padding: 14px;
  background: #fff7ef;
  border-right: 1px solid #eee7dd;
  font-weight: 700;
}

.compare-cell {
  padding: 14px;
  border-right: 1px solid #eee7dd;
}

.compare-cell:last-child {
  border-right: none;
}

.compare-product a {
  color: inherit;
  text-decoration: none;
}

.compare-product img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 10px;
}

.compare-product h3 {
  margin-top: 8px;
}

.compare-product button {
  margin-top: 8px;
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  padding: 8px 10px;
  background: #fff;
  cursor: pointer;
}

.compare-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.compare-actions__cart {
  background: #1f2233;
  color: #fff;
}

.old-price {
  color: #8a95ab;
  text-decoration: line-through;
}

.empty {
  margin-top: 16px;
}

.empty a {
  color: #1f2233;
}

@media (max-width: 840px) {
  .compare-table {
    overflow-x: auto;
  }

  .compare-row {
    min-width: 760px;
  }
}
</style>
