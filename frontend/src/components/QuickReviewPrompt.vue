<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { X } from 'lucide-vue-next'
import { requestJson } from '@/lib/api'
import { toast } from '@/lib/toast'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { toProductRoute } from '@/lib/product-route'

type PendingItem = {
  order_item_id: number
  product_name: string
  product_slug: string
  image_url: string | null
  order_number: string
  placed_at: string | null
}

const authStore = useAuthStore()
const { isAuthenticated } = storeToRefs(authStore)

const pending = ref<PendingItem[]>([])
const dismissed = ref<Set<number>>(new Set())
const isPosting = ref(false)

const current = computed(() => pending.value.find((p) => !dismissed.value.has(p.order_item_id)) ?? null)

const DISMISS_KEY = 'shoria.quick_review.dismissed'

function loadDismissed() {
  try {
    const raw = sessionStorage.getItem(DISMISS_KEY)
    if (!raw) {
      return
    }
    const arr = JSON.parse(raw) as unknown
    if (!Array.isArray(arr)) {
      return
    }
    dismissed.value = new Set(arr.map((x) => Number(x)).filter((n) => Number.isFinite(n)))
  } catch {
    /* ignore */
  }
}

function persistDismissed() {
  try {
    sessionStorage.setItem(DISMISS_KEY, JSON.stringify([...dismissed.value]))
  } catch {
    /* ignore */
  }
}

async function fetchPending() {
  if (!isAuthenticated.value) {
    pending.value = []
    return
  }

  try {
    const response = await requestJson<{ data: PendingItem[] }>('/api/reviews/pending')
    pending.value = response.data
  } catch {
    pending.value = []
  }
}

async function submitRating(rating: number) {
  const item = current.value
  if (!item || isPosting.value) {
    return
  }

  isPosting.value = true
  try {
    await requestJson('/api/reviews/quick', {
      method: 'POST',
      body: JSON.stringify({
        order_item_id: item.order_item_id,
        rating,
      }),
    })
    toast.success('Спасибо за оценку')
    dismissed.value.add(item.order_item_id)
    persistDismissed()
    await fetchPending()
  } catch (error) {
    console.error(error)
    toast.error('Не удалось сохранить оценку')
  } finally {
    isPosting.value = false
  }
}

function closeWidget() {
  const item = current.value
  if (!item) {
    return
  }
  dismissed.value.add(item.order_item_id)
  persistDismissed()
}

watch(isAuthenticated, () => {
  loadDismissed()
  void fetchPending()
})

onMounted(() => {
  loadDismissed()
  void fetchPending()
})
</script>

<template>
  <div
    v-if="isAuthenticated && current"
    class="quick-review"
    role="dialog"
    aria-label="Оценка покупки"
  >
    <button type="button" class="quick-review__close" aria-label="Закрыть" @click="closeWidget">
      <X :size="18" />
    </button>
    <p class="quick-review__kicker">Как вам покупка?</p>
    <RouterLink :to="toProductRoute({ slug: current.product_slug })" class="quick-review__product">
      {{ current.product_name }}
    </RouterLink>
    <p v-if="current.order_number" class="quick-review__meta">Заказ {{ current.order_number }}</p>
    <div class="quick-review__stars" role="group" aria-label="Оценка от 1 до 5">
      <button
        v-for="star in 5"
        :key="star"
        type="button"
        class="quick-review__star"
        :disabled="isPosting"
        :aria-label="`Оценка ${star} из 5`"
        @click="submitRating(star)"
      >
        ★
      </button>
    </div>
    <p class="quick-review__note">
      Текст отзыва можно добавить в
      <RouterLink to="/account/reviews">профиле</RouterLink>
      или на странице товара.
    </p>
  </div>
</template>

<style scoped>
.quick-review {
  position: fixed;
  right: 16px;
  bottom: 88px;
  z-index: 40;
  width: min(300px, calc(100vw - 32px));
  padding: 14px 14px 12px;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--popover);
  box-shadow: 0 12px 32px rgb(15 23 42 / 12%);
}

.quick-review__close {
  position: absolute;
  top: 8px;
  right: 8px;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--muted-foreground);
  cursor: pointer;
}

.quick-review__close:hover {
  background: var(--muted);
  color: var(--foreground);
}

.quick-review__kicker {
  margin: 0 28px 4px 0;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted-foreground);
}

.quick-review__product {
  display: inline-block;
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--foreground);
  text-decoration: none;
  line-height: 1.25;
}

.quick-review__product:hover {
  text-decoration: underline;
}

.quick-review__meta {
  margin: 6px 0 10px;
  font-size: 12px;
  color: var(--muted-foreground);
}

.quick-review__stars {
  display: flex;
  gap: 4px;
}

.quick-review__star {
  flex: 1;
  min-height: 40px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--background);
  font-size: 18px;
  line-height: 1;
  color: #ca8a04;
  cursor: pointer;
}

.quick-review__star:hover:not(:disabled) {
  border-color: #000;
}

.quick-review__star:disabled {
  opacity: 0.5;
  cursor: default;
}

.quick-review__note {
  margin: 10px 0 0;
  font-size: 11px;
  line-height: 1.35;
  color: var(--muted-foreground);
}

.quick-review__note a {
  color: inherit;
  font-weight: 600;
}

@media (max-width: 560px) {
  .quick-review {
    right: 12px;
    bottom: 72px;
  }
}
</style>
