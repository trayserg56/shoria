<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useCartStore } from '@/stores/cart'

const cartStore = useCartStore()
const route = useRoute()
const router = useRouter()

const { orderHistory, orderHistoryMeta } = storeToRefs(cartStore)
const isLoading = ref(false)

const activeStatus = computed(() => String(route.query.status ?? ''))
const activePage = computed(() => Number(route.query.page ?? '1'))

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
    path: '/orders',
    query: {
      ...(status ? { status } : {}),
    },
  })
}

function setPage(page: number) {
  void router.push({
    path: '/orders',
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
  <main class="orders-page">
    <header class="orders-header">
      <h1>Мои заказы</h1>
      <p>История заказов по текущей сессии.</p>
    </header>

    <div class="filters">
      <button class="chip" :class="{ 'chip--active': !activeStatus }" @click="setStatus('')">Все</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'new' }" @click="setStatus('new')">Новые</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'paid' }" @click="setStatus('paid')">Оплаченные</button>
      <button class="chip" :class="{ 'chip--active': activeStatus === 'cancelled' }" @click="setStatus('cancelled')">
        Отмененные
      </button>
    </div>

    <p v-if="isLoading" class="status">Загружаем заказы...</p>

    <section v-if="orderHistory.length > 0" class="list">
      <article v-for="order in orderHistory" :key="order.order_number" class="row">
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
        <div class="right">
          <span class="status-badge">{{ order.status }}</span>
          <strong>{{ formatPrice(order.total) }}</strong>
        </div>
      </article>
    </section>

    <section v-else class="empty">
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
  </main>
</template>

<style scoped>
.orders-page {
  width: min(1040px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.orders-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 78px);
  line-height: 0.9;
}

.orders-header p {
  margin-top: 6px;
  color: var(--color-text-soft);
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 16px 0;
}

.chip {
  padding: 8px 14px;
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
}

.chip--active {
  border-color: #f35b04;
  background: #fff2e8;
  color: #c74803;
}

.status {
  color: #5e677b;
}

.list {
  display: grid;
  gap: 10px;
}

.row {
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  padding: 12px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.row a {
  color: #1f2233;
  text-decoration: none;
  font-weight: 700;
}

.row p {
  color: #6c7488;
  font-size: 14px;
}

.row .meta {
  font-size: 13px;
}

.right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 999px;
  background: #eef2fb;
  color: #4f5f80;
  font-size: 12px;
}

.empty {
  margin-top: 12px;
}

.empty a {
  color: #1f2233;
}

.pagination {
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.pagination button {
  padding: 8px 12px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.45;
  cursor: default;
}
</style>
