<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { useCartStore } from '@/stores/cart'

const cartStore = useCartStore()
const route = useRoute()
const router = useRouter()

const { orderHistory, orderHistoryMeta } = storeToRefs(cartStore)
const isLoading = ref(false)

const activeStatus = computed(() => String(route.query.status ?? ''))
const activePage = computed(() => Number(route.query.page ?? '1'))

function resolveOrderStatusLabel(status: string) {
  return (
    {
      placed: 'Оформлен',
      confirmed: 'Подтвержден',
      completed: 'Завершен',
      cancelled: 'Отменен',
    } as Record<string, string>
  )[status] ?? status
}

function resolvePaymentStatusLabel(status: string) {
  return (
    {
      unpaid: 'Не начата',
      pending: 'Ожидает',
      authorized: 'Холд',
      paid: 'Оплачено',
      failed: 'Ошибка',
      cancelled: 'Отменена',
      partially_refunded: 'Частичный возврат',
      refunded: 'Возврат',
    } as Record<string, string>
  )[status] ?? status
}

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

async function loadOrders() {
  isLoading.value = true

  try {
    await cartStore.loadOrderHistory({
      page: activePage.value,
      status: activeStatus.value || undefined,
      perPage: 8,
    })
  } finally {
    isLoading.value = false
  }
}

function setStatus(status = '') {
  void router.push({
    path: '/account/orders',
    query: {
      ...(status ? { status } : {}),
    },
  })
}

function setPage(page: number) {
  void router.push({
    path: '/account/orders',
    query: {
      ...(activeStatus.value ? { status: activeStatus.value } : {}),
      ...(page > 1 ? { page: String(page) } : {}),
    },
  })
}

onMounted(loadOrders)

watch(
  () => route.fullPath,
  async () => {
    await loadOrders()
  },
)
</script>

<template>
  <section class="orders-page">
    <div class="orders-page__header">
      <div>
        <h2>Заказы</h2>
        <p>Полная история покупок с фильтрами по статусам.</p>
      </div>
    </div>

    <div class="filters">
      <button class="chip" :class="{ 'chip--active': !activeStatus }" @click="setStatus('')">Все</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'new' }" @click="setStatus('new')">Новые</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'paid' }" @click="setStatus('paid')">Оплаченные</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'cancelled' }" @click="setStatus('cancelled')">
        Отмененные
      </button>
    </div>

    <section v-if="isLoading && !orderHistory.length" class="orders-list" aria-hidden="true">
      <article v-for="index in 4" :key="`order-skeleton-${index}`" class="order-row">
        <div class="order-row__left-skeleton">
          <AppSkeleton width="180px" height="20px" />
          <AppSkeleton width="140px" height="14px" />
          <AppSkeleton width="160px" height="14px" />
        </div>
        <div class="order-row__right">
          <div class="order-row__badges">
            <AppSkeleton width="110px" height="30px" radius="999px" />
            <AppSkeleton width="110px" height="30px" radius="999px" />
          </div>
          <AppSkeleton width="90px" height="24px" />
        </div>
      </article>
    </section>

    <section v-else-if="orderHistory.length > 0" class="orders-list">
      <article v-for="order in orderHistory" :key="order.order_number" class="order-row">
        <div>
          <RouterLink :to="{ name: 'order-success', params: { orderNumber: order.order_number } }">
            {{ order.order_number }}
          </RouterLink>
          <p>{{ formatDate(order.placed_at) }}</p>
          <p class="meta">
            {{ order.delivery_method === 'courier' ? 'Курьер' : 'Самовывоз' }} ·
            {{ order.payment_method === 'card' ? 'Карта' : 'Наличные' }}
          </p>
        </div>
        <div class="order-row__right">
          <div class="order-row__badges">
            <span class="status-badge">{{ resolveOrderStatusLabel(order.order_status) }}</span>
            <span class="status-badge status-badge--payment">{{ resolvePaymentStatusLabel(order.payment_status) }}</span>
          </div>
          <strong>{{ formatPrice(order.total) }}</strong>
        </div>
      </article>
    </section>

    <section v-else-if="!isLoading" class="empty">
      <p>Заказы пока не найдены.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>

    <footer v-if="orderHistoryMeta.lastPage > 1" class="pagination">
      <button :disabled="orderHistoryMeta.currentPage <= 1" @click="setPage(orderHistoryMeta.currentPage - 1)">
        Назад
      </button>
      <span>Страница {{ orderHistoryMeta.currentPage }} из {{ orderHistoryMeta.lastPage }}</span>
      <button
        :disabled="orderHistoryMeta.currentPage >= orderHistoryMeta.lastPage"
        @click="setPage(orderHistoryMeta.currentPage + 1)"
      >
        Дальше
      </button>
    </footer>
  </section>
</template>

<style scoped>
.orders-page {
  display: grid;
  gap: 16px;
}

.orders-page__header h2 {
  font-size: 34px;
}

.orders-page__header p,
.status,
.order-row p {
  color: var(--color-text-soft);
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.chip,
.pagination button {
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
  font: inherit;
}

.chip {
  padding: 10px 16px;
}

.chip--active {
  border-color: #f35b04;
  background: #fff2e8;
  color: #c74803;
}

.orders-list {
  display: grid;
  gap: 12px;
}

.order-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.order-row__left-skeleton {
  display: grid;
  gap: 10px;
}

.order-row a {
  color: #1f2233;
  text-decoration: none;
  font-weight: 800;
}

.meta {
  font-size: 13px;
}

.order-row__right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.order-row__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: end;
}

.status-badge {
  padding: 6px 10px;
  border-radius: 999px;
  background: #eef2fb;
  color: #4f5f80;
  font-size: 12px;
}

.status-badge--payment {
  background: #fff2e8;
  color: #c74803;
}

.empty a {
  color: #c74803;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.pagination button {
  padding: 8px 12px;
  border-radius: 12px;
}

.pagination button:disabled {
  opacity: 0.45;
  cursor: default;
}

@media (max-width: 760px) {
  .order-row {
    align-items: start;
    flex-direction: column;
  }
}
</style>
