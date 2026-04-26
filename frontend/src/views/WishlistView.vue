<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { toProductRoute } from '@/lib/product-route'
import { useWishlistStore } from '@/stores/wishlist'
import { useCartStore } from '@/stores/cart'

const wishlistStore = useWishlistStore()
const { items: wishlistItems } = storeToRefs(wishlistStore)
const cartStore = useCartStore()
const cartMessage = ref('')
const isBulkAdding = ref(false)

function formatCatalogPrice(value: number, currency: string) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function removeFromWishlist(productId: number) {
  wishlistStore.remove(productId)
}

function isOutOfStock(stock?: number | null) {
  return typeof stock === 'number' && stock <= 0
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
  if (!wishlistItems.value.length || isBulkAdding.value) {
    return
  }

  isBulkAdding.value = true
  let added = 0

  try {
    for (const item of wishlistItems.value) {
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
  wishlistStore.hydrate()
})
</script>

<template>
  <main class="wishlist-page">
    <header class="wishlist-header">
      <h1>Избранное</h1>
      <p>Сохраненные товары для быстрого возврата к покупке.</p>
    </header>
    <p v-if="cartMessage" class="status">{{ cartMessage }}</p>
    <div v-if="wishlistItems.length" class="wishlist-actions">
      <button type="button" :disabled="isBulkAdding" @click="addAllToCart">
        {{ isBulkAdding ? 'Добавляем...' : 'Добавить все в корзину' }}
      </button>
      <button type="button" @click="wishlistStore.clear">Очистить избранное</button>
      <RouterLink to="/cart">Перейти в корзину</RouterLink>
    </div>

    <div v-if="wishlistItems.length" class="wishlist-grid">
      <article v-for="item in wishlistItems" :key="item.id" class="wishlist-card">
        <RouterLink class="wishlist-card__link" :to="toProductRoute(item)">
          <img :src="resolveImageSrc(item.image_url)" :alt="item.name" loading="lazy" @error="applyImageFallback" />
          <div class="wishlist-card__content">
            <p>{{ item.category?.name ?? 'Sneakers' }}</p>
            <h3>{{ item.name }}</h3>
            <div class="wishlist-card__price">
              <strong>{{ formatCatalogPrice(item.price, item.currency) }}</strong>
              <s v-if="item.old_price">{{ formatCatalogPrice(item.old_price, item.currency) }}</s>
            </div>
            <p v-if="isOutOfStock(item.stock)" class="wishlist-card__stock">Нет в наличии</p>
          </div>
        </RouterLink>
        <div class="wishlist-card__actions">
          <button
            type="button"
            class="wishlist-card__cart"
            :disabled="isOutOfStock(item.stock)"
            @click="addToCart(item.slug)"
          >
            {{ isOutOfStock(item.stock) ? 'Нет в наличии' : 'В корзину' }}
          </button>
          <button type="button" @click="removeFromWishlist(item.id)">Убрать</button>
        </div>
      </article>
    </div>

    <section v-else class="empty">
      <p>В избранном пока пусто.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>
  </main>
</template>

<style scoped>
.wishlist-page {
  width: min(1040px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.wishlist-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 78px);
  line-height: 0.9;
}

.wishlist-header p {
  margin-top: 6px;
  color: var(--color-text-soft);
}

.wishlist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-top: 16px;
}

.status {
  margin-top: 10px;
  color: #4a5e7e;
}

.wishlist-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 10px 0 14px;
}

.wishlist-actions button,
.wishlist-actions a {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  color: #1f2233;
  font: inherit;
  text-decoration: none;
  cursor: pointer;
}

.wishlist-card {
  overflow: hidden;
  border-radius: 14px;
  background: #fffaf4;
  border: 1px solid #eedecb;
}

.wishlist-card__link {
  display: block;
  color: inherit;
  text-decoration: none;
}

.wishlist-card img {
  width: 100%;
  height: 160px;
  object-fit: cover;
}

.wishlist-card__content {
  padding: 12px 14px 10px;
}

.wishlist-card__content p {
  color: #7f8ca8;
  font-size: 12px;
}

.wishlist-card__content h3 {
  margin-top: 6px;
}

.wishlist-card__price {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.wishlist-card__price s {
  color: #8a95ab;
}

.wishlist-card__stock {
  margin-top: 8px;
  color: #b2552f;
  font-size: 13px;
  font-weight: 600;
}

.wishlist-card button {
  margin: 0 14px 14px;
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  font: inherit;
  cursor: pointer;
}

.wishlist-card__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
}

.wishlist-card__cart {
  background: #1f2233;
  color: #fff;
}

.wishlist-card__cart:disabled {
  background: #f3e8db;
  color: #9d8d7f;
  cursor: not-allowed;
}

.empty {
  margin-top: 16px;
}

.empty a {
  color: #1f2233;
}
</style>
