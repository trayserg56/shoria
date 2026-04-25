<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requestJson } from '@/lib/api'
import { toProductRoute } from '@/lib/product-route'
import AppSkeleton from '@/components/AppSkeleton.vue'

type ReviewItem = {
  id: number
  rating: number
  review_text: string
  is_verified_purchase: boolean
  created_at: string
  updated_at: string
  product: {
    id: number
    name: string
    slug: string
    category: {
      name: string
      slug: string
    } | null
  } | null
}

type EligibleProduct = {
  product_id: number
  product_name: string
  product_slug: string
  category: {
    name: string
    slug: string
  } | null
  order_number: string | null
  purchased_at: string | null
}

const isLoading = ref(false)
const reviews = ref<ReviewItem[]>([])
const eligibleProducts = ref<EligibleProduct[]>([])

function formatDate(value: string | null) {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
  }).format(new Date(value))
}

function buildStars(rating: number) {
  return '★'.repeat(rating) + '☆'.repeat(Math.max(0, 5 - rating))
}

async function loadReviewsCabinet() {
  isLoading.value = true

  try {
    const payload = await requestJson<{
      reviews: ReviewItem[]
      eligible_products: EligibleProduct[]
    }>('/api/reviews/me')

    reviews.value = payload.reviews
    eligibleProducts.value = payload.eligible_products
  } finally {
    isLoading.value = false
  }
}

onMounted(loadReviewsCabinet)
</script>

<template>
  <section class="reviews-page">
    <header>
      <h2>Ваши отзывы</h2>
      <p>Оценку можно поставить только вместе с текстом отзыва, а оставить отзыв можно только после покупки.</p>
    </header>

    <section v-if="isLoading" class="reviews-page__skeleton" aria-hidden="true">
      <AppSkeleton width="100%" height="120px" radius="20px" />
      <AppSkeleton width="100%" height="120px" radius="20px" />
      <AppSkeleton width="100%" height="120px" radius="20px" />
    </section>

    <section v-else-if="eligibleProducts.length > 0" class="card">
      <h3>Можно оставить отзыв</h3>
      <p class="card__hint">Товары, которые вы уже покупали.</p>
      <ul class="list">
        <li v-for="item in eligibleProducts" :key="`eligible-${item.product_id}`" class="row">
          <div>
            <strong>{{ item.product_name }}</strong>
            <p>
              Заказ {{ item.order_number ?? '—' }} · {{ formatDate(item.purchased_at) }}
            </p>
          </div>
          <RouterLink
            :to="toProductRoute({ slug: item.product_slug, category: item.category })"
            class="link-button"
          >
            Оставить отзыв
          </RouterLink>
        </li>
      </ul>
    </section>

    <section class="card">
      <h3>Опубликованные отзывы</h3>
      <p class="card__hint">Ваши оценки и тексты по купленным товарам.</p>

      <ul v-if="reviews.length > 0" class="list">
        <li v-for="review in reviews" :key="`review-${review.id}`" class="row row--review">
          <div class="row__top">
            <strong>{{ review.product?.name ?? 'Товар' }}</strong>
            <span class="rating">{{ buildStars(review.rating) }}</span>
          </div>
          <p class="text">{{ review.review_text }}</p>
          <div class="meta">
            <span>{{ formatDate(review.updated_at || review.created_at) }}</span>
            <span v-if="review.is_verified_purchase" class="meta__verified">Покупка подтверждена</span>
            <RouterLink
              v-if="review.product"
              :to="toProductRoute({ slug: review.product.slug, category: review.product.category })"
            >
              Редактировать
            </RouterLink>
          </div>
        </li>
      </ul>

      <div v-else class="empty">
        Отзывов пока нет. После покупки товара вы сможете оставить отзыв на его странице.
      </div>
    </section>
  </section>
</template>

<style scoped>
.reviews-page {
  display: grid;
  gap: 16px;
}

.reviews-page h2 {
  font-size: 34px;
}

.reviews-page p {
  color: var(--color-text-soft);
}

.reviews-page__skeleton {
  display: grid;
  gap: 10px;
}

.card {
  padding: 20px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.card__hint {
  margin-top: 4px;
}

.list {
  margin-top: 14px;
  display: grid;
  gap: 10px;
}

.row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  padding: 14px;
  border-radius: 16px;
  background: #fff7ee;
  border: 1px solid #f3e4d5;
}

.row p {
  margin-top: 4px;
  font-size: 14px;
}

.row--review {
  display: grid;
  gap: 10px;
}

.row__top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
}

.rating {
  color: #c74803;
  font-size: 14px;
  letter-spacing: 0.08em;
}

.text {
  color: var(--color-text);
  line-height: 1.45;
}

.meta {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.meta span,
.meta a {
  font-size: 13px;
}

.meta a {
  color: #c74803;
  text-decoration: none;
}

.meta__verified {
  padding: 4px 8px;
  border-radius: 999px;
  background: #ecf8ef;
  color: #185f2d;
}

.link-button {
  border: 1px solid #d7d4ce;
  border-radius: 12px;
  background: #fff;
  color: var(--color-text);
  text-decoration: none;
  font-size: 14px;
  padding: 10px 14px;
  white-space: nowrap;
}

.empty {
  margin-top: 12px;
  color: var(--color-text-soft);
}

@media (max-width: 760px) {
  .row {
    align-items: start;
    flex-direction: column;
  }
}
</style>

