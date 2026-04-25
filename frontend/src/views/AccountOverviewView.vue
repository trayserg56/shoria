<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useCompareStore } from '@/stores/compare'

const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()

const { user } = storeToRefs(authStore)
const { orderHistory, orderHistoryMeta } = storeToRefs(cartStore)
const { totalItems: wishlistTotalItems } = storeToRefs(wishlistStore)
const { totalItems: compareTotalItems } = storeToRefs(compareStore)

const isLoadingOrders = ref(false)

const recentOrders = computed(() => orderHistory.value.slice(0, 3))
const stats = computed(() => [
  {
    label: 'Всего заказов',
    value: String(orderHistoryMeta.value.total),
    to: { name: 'account-orders' },
  },
  {
    label: 'В избранном',
    value: String(wishlistTotalItems.value),
    to: { name: 'account-saved' },
  },
  {
    label: 'В сравнении',
    value: String(compareTotalItems.value),
    to: { name: 'account-saved' },
  },
  {
    label: 'Баллы',
    value: String(user.value?.loyalty?.points_balance ?? 0),
    to: { name: 'account-loyalty' },
  },
])

function formatPrice(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(async () => {
  wishlistStore.hydrate()
  compareStore.hydrate()
  isLoadingOrders.value = true

  try {
    await Promise.all([authStore.loadMe(), cartStore.loadOrderHistory({ perPage: 3 })])
  } finally {
    isLoadingOrders.value = false
  }
})
</script>

<template>
  <section class="account-panel">
    <div class="account-panel__header">
      <div>
        <h2>Обзор</h2>
        <p>Краткий статус кабинета и быстрые переходы к важным действиям.</p>
      </div>
      <RouterLink :to="{ name: 'account-settings' }" class="ghost-link">Перейти к настройкам</RouterLink>
    </div>

    <section v-if="user" class="account-overview-card">
      <div>
        <p class="eyebrow">Аккаунт</p>
        <h3>{{ user.name }}</h3>
        <p>{{ user.email }}</p>
        <span class="status-pill" :class="user.email_verified_at ? 'status-pill--ok' : 'status-pill--warn'">
          {{ user.email_verified_at ? 'Email подтвержден' : 'Email требует подтверждения' }}
        </span>
      </div>
      <div class="account-overview-card__actions">
        <RouterLink :to="{ name: 'account-settings' }">Редактировать профиль</RouterLink>
        <RouterLink :to="{ name: 'account-orders' }">Смотреть заказы</RouterLink>
      </div>
    </section>

    <section class="account-stats">
      <RouterLink v-for="item in stats" :key="item.label" :to="item.to" class="account-stat">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </RouterLink>
    </section>

    <section class="account-split">
      <article class="account-box">
        <div class="account-box__head">
          <div>
            <h3>Последние заказы</h3>
            <p>Быстрый взгляд на недавнюю активность.</p>
          </div>
          <RouterLink :to="{ name: 'account-orders' }">Все заказы</RouterLink>
        </div>

        <div v-if="isLoadingOrders && !recentOrders.length" class="order-preview-list" aria-hidden="true">
          <article v-for="index in 3" :key="`overview-order-skeleton-${index}`" class="order-preview-item">
            <div class="order-preview-item__skeleton">
              <AppSkeleton width="160px" height="18px" />
              <AppSkeleton width="132px" height="14px" />
            </div>
            <AppSkeleton width="88px" height="20px" />
          </article>
        </div>

        <div v-else-if="recentOrders.length" class="order-preview-list">
          <RouterLink
            v-for="order in recentOrders"
            :key="order.order_number"
            :to="{ name: 'order-success', params: { orderNumber: order.order_number } }"
            class="order-preview-item"
          >
            <div>
              <strong>{{ order.order_number }}</strong>
              <p>{{ formatDate(order.placed_at) }}</p>
            </div>
            <span>{{ formatPrice(order.total) }}</span>
          </RouterLink>
        </div>

        <div v-else class="empty-inline">
          <p>Пока нет оформленных заказов.</p>
          <RouterLink to="/catalog">Перейти в каталог</RouterLink>
        </div>
      </article>

      <article class="account-box">
        <div class="account-box__head">
          <div>
            <h3>Быстрые действия</h3>
            <p>Самые частые сценарии в одном месте.</p>
          </div>
        </div>

        <div class="quick-actions">
          <RouterLink :to="{ name: 'account-settings' }">Изменить имя, телефон и email</RouterLink>
          <RouterLink :to="{ name: 'account-loyalty' }">Проверить баллы и уровень</RouterLink>
          <RouterLink :to="{ name: 'account-saved' }">Открыть избранное и сравнение</RouterLink>
          <RouterLink to="/catalog">Вернуться в каталог</RouterLink>
        </div>
      </article>
    </section>
  </section>
</template>

<style scoped>
.account-panel {
  display: grid;
  gap: 18px;
}

.account-panel__header,
.account-box__head,
.account-overview-card,
.order-preview-item,
.account-stat,
.quick-actions a {
  border-radius: 24px;
}

.account-panel__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: end;
}

.account-panel__header h2 {
  font-size: 34px;
}

.account-panel__header p,
.account-box__head p {
  color: var(--color-text-soft);
}

.ghost-link,
.account-box__head a {
  color: #c74803;
  text-decoration: none;
}

.account-overview-card,
.account-box {
  padding: 22px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.account-overview-card {
  display: flex;
  justify-content: space-between;
  gap: 18px;
}

.eyebrow {
  color: #7b869f;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 12px;
}

.account-overview-card h3 {
  margin-top: 8px;
  font-size: 32px;
}

.status-pill {
  display: inline-flex;
  margin-top: 14px;
  min-height: 34px;
  align-items: center;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.status-pill--ok {
  background: #ecf8ef;
  color: #185f2d;
}

.status-pill--warn {
  background: #fff2e8;
  color: #c74803;
}

.account-overview-card__actions {
  display: grid;
  gap: 10px;
  align-content: start;
}

.account-overview-card__actions a,
.quick-actions a {
  display: inline-flex;
  min-height: 46px;
  align-items: center;
  justify-content: center;
  padding: 0 16px;
  border-radius: 16px;
  background: #23263a;
  color: #fff;
  text-decoration: none;
}

.account-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.account-stat {
  padding: 18px 20px;
  background: rgba(255, 255, 255, 0.86);
  border: 1px solid #eadfcf;
  text-decoration: none;
  color: inherit;
}

.account-stat span {
  display: block;
  color: #6f7b95;
}

.account-stat strong {
  display: block;
  margin-top: 12px;
  font-size: 36px;
}

.account-split {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 14px;
}

.order-preview-list,
.quick-actions {
  display: grid;
  gap: 10px;
  margin-top: 16px;
}

.order-preview-item {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  background: #fff8f1;
  text-decoration: none;
  color: inherit;
}

.order-preview-item__skeleton {
  display: grid;
  gap: 8px;
}

.order-preview-item p,
.muted {
  color: #6f7b95;
}

.empty-inline {
  margin-top: 14px;
}

.empty-inline a {
  color: #c74803;
}

@media (max-width: 980px) {
  .account-overview-card,
  .account-stats,
  .account-split {
    grid-template-columns: 1fr;
  }
}
</style>
