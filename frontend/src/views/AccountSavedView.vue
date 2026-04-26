<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toProductRoute } from '@/lib/product-route'
import { useWishlistStore } from '@/stores/wishlist'
import { useCompareStore } from '@/stores/compare'
import { useCartStore } from '@/stores/cart'

const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const cartStore = useCartStore()
const cartMessage = ref('')

const { items: wishlistItems } = storeToRefs(wishlistStore)
const { items: compareItems } = storeToRefs(compareStore)

const wishlistPreview = computed(() => wishlistItems.value.slice(0, 3))
const comparePreview = computed(() => compareItems.value.slice(0, 3))

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
  } catch (error) {
    console.error(error)
    cartMessage.value = 'Не удалось добавить товар в корзину.'
  }
}

onMounted(() => {
  wishlistStore.hydrate()
  compareStore.hydrate()
})
</script>

<template>
  <section class="saved-page">
    <div class="saved-page__header">
      <div>
        <h2>Избранное и сравнение</h2>
        <p>Сохранённые товары и быстрые переходы к выбору перед покупкой.</p>
      </div>
      <div class="saved-page__actions">
        <RouterLink to="/wishlist">Полное избранное</RouterLink>
        <RouterLink to="/compare">Полное сравнение</RouterLink>
      </div>
    </div>

    <p v-if="cartMessage" class="status">{{ cartMessage }}</p>

    <div class="saved-summary">
      <article class="summary-card">
        <span>В избранном</span>
        <strong>{{ wishlistItems.length }}</strong>
      </article>
      <article class="summary-card">
        <span>В сравнении</span>
        <strong>{{ compareItems.length }}</strong>
      </article>
    </div>

    <section class="saved-grid">
      <article class="saved-card">
        <div class="saved-card__head">
          <div>
            <h3>Избранное</h3>
            <p>Товары, к которым хочется вернуться позже.</p>
          </div>
          <RouterLink to="/wishlist">Открыть всё</RouterLink>
        </div>

        <div v-if="wishlistPreview.length" class="saved-items">
          <article v-for="item in wishlistPreview" :key="item.id" class="saved-item">
            <RouterLink :to="toProductRoute(item)" class="saved-item__body">
              <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
              <div>
                <p>{{ item.category?.name ?? 'Sneakers' }}</p>
                <strong>{{ item.name }}</strong>
                <span>{{ formatPrice(item.price, item.currency) }}</span>
              </div>
            </RouterLink>
            <button type="button" @click="addToCart(item.slug)">В корзину</button>
          </article>
        </div>

        <div v-else class="empty-inline">
          <p>В избранном пока пусто.</p>
          <RouterLink to="/catalog">Перейти в каталог</RouterLink>
        </div>
      </article>

      <article class="saved-card">
        <div class="saved-card__head">
          <div>
            <h3>Сравнение</h3>
            <p>Товары, которые уже отложены для сопоставления.</p>
          </div>
          <RouterLink to="/compare">Открыть всё</RouterLink>
        </div>

        <div v-if="comparePreview.length" class="saved-items">
          <article v-for="item in comparePreview" :key="item.id" class="saved-item">
            <RouterLink :to="toProductRoute(item)" class="saved-item__body">
              <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
              <div>
                <p>{{ item.category?.name ?? 'Sneakers' }}</p>
                <strong>{{ item.name }}</strong>
                <span>{{ formatPrice(item.price, item.currency) }}</span>
              </div>
            </RouterLink>
            <button type="button" @click="addToCart(item.slug)">В корзину</button>
          </article>
        </div>

        <div v-else class="empty-inline">
          <p>В сравнении пока пусто.</p>
          <RouterLink to="/catalog">Перейти в каталог</RouterLink>
        </div>
      </article>
    </section>
  </section>
</template>

<style scoped>
.saved-page,
.saved-grid {
  display: grid;
  gap: 18px;
}

.saved-page__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: end;
}

.saved-page__header h2 {
  font-size: 34px;
}

.saved-page__header p,
.saved-card__head p,
.saved-item p,
.status {
  color: var(--color-text-soft);
}

.saved-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.saved-page__actions a,
.saved-card__head a,
.saved-item button,
.empty-inline a {
  color: #c74803;
}

.saved-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.summary-card,
.saved-card {
  padding: 22px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.summary-card span {
  color: #6f7b95;
}

.summary-card strong {
  display: block;
  margin-top: 12px;
  font-size: 38px;
}

.saved-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.saved-card__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: start;
}

.saved-items {
  display: grid;
  gap: 12px;
  margin-top: 16px;
}

.saved-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 14px;
  border-radius: 18px;
  background: #fff8f1;
}

.saved-item__body {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  gap: 12px;
  text-decoration: none;
  color: inherit;
}

.saved-item__body img {
  width: 84px;
  height: 84px;
  object-fit: cover;
  border-radius: 14px;
}

.saved-item__body strong,
.saved-item__body span {
  display: block;
}

.saved-item__body span {
  margin-top: 8px;
}

.saved-item button {
  min-height: 42px;
  padding: 0 14px;
  border-radius: 14px;
  border: 1px solid #f5c7a7;
  background: #fff2e8;
  font: inherit;
  cursor: pointer;
}

@media (max-width: 980px) {
  .saved-summary,
  .saved-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 680px) {
  .saved-page__header,
  .saved-card__head,
  .saved-item {
    grid-template-columns: 1fr;
    display: grid;
  }

  .saved-item__body {
    grid-template-columns: 72px minmax(0, 1fr);
  }
}
</style>
