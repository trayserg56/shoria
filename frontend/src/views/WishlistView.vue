<script setup lang="ts">
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import type { WishlistItem } from '@/stores/wishlist'
import { useWishlistStore } from '@/stores/wishlist'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'

const wishlistStore = useWishlistStore()
const { items: wishlistItems } = storeToRefs(wishlistStore)

/** Приводим сохранённое избранное к форме, которую ждёт UnifiedProductCard */
function toProductCardData(item: WishlistItem) {
  const stock: number = typeof item.stock === 'number' ? item.stock : 1

  return {
    id: item.id,
    name: item.name,
    brand: null as string | null,
    slug: item.slug,
    price: item.price,
    old_price: item.old_price,
    stock,
    currency: item.currency,
    image_url: item.image_url,
    category: item.category,
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
    </header>
    <div v-if="wishlistItems.length" class="wishlist-grid">
      <UnifiedProductCard
        v-for="item in wishlistItems"
        :key="item.id"
        :product="toProductCardData(item)"
        source="wishlist"
      />
    </div>
    <p v-else class="empty">Пока пусто. Добавляйте сердечком на карточках товаров.</p>
  </main>
</template>

<style scoped>
.wishlist-page {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 28px 20px 48px;
}

.wishlist-header h1 {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(32px, 4vw, 44px);
}

.wishlist-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 248px), 1fr));
  gap: 16px;
  margin-top: 24px;
}

.empty {
  margin-top: 24px;
  color: var(--color-text-soft);
}

@media (max-width: 720px) {
  .wishlist-page {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
