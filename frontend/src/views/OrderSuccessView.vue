<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useCartStore } from '@/stores/cart'

type OrderDetails = {
  order_number: string
  status: string
  order_status: string
  payment_status: string
  fulfillment_status: string
  refund_status: string
  payment_transaction_status: string | null
  delivery_method: string
  payment_method: string
  promo_code: string | null
  total: number
  subtotal: number
  discount_total: number
  delivery_total: number
  currency: string
  customer_name: string
  customer_email: string
  customer_phone: string
  comment: string | null
  placed_at: string
  payment_transactions: Array<{
    provider: string
    type: string
    status: string
    amount: number
    currency: string
    provider_payment_id: string | null
    confirmed_at: string | null
    failed_at: string | null
    cancelled_at: string | null
  }>
  items: Array<{
    product_name: string
    product_slug: string
    variant_label: string | null
    image_url: string | null
    qty: number
    unit_price: number
    total_price: number
  }>
}

const route = useRoute()
const cartStore = useCartStore()

const order = ref<OrderDetails | null>(null)
const isLoading = ref(false)
const hasError = ref(false)

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
      pending: 'Ожидает подтверждения',
      authorized: 'Холд',
      paid: 'Оплачено',
      failed: 'Ошибка оплаты',
      cancelled: 'Отменена',
      partially_refunded: 'Частичный возврат',
      refunded: 'Возвращена',
      created: 'Создана',
      confirmed: 'Подтверждена',
      succeeded: 'Успешна',
    } as Record<string, string>
  )[status] ?? status
}

function resolveFulfillmentStatusLabel(status: string) {
  return (
    {
      pending: 'Ожидает обработки',
      processing: 'Сборка',
      packed: 'Упакован',
      shipped: 'Передан в доставку',
      ready_for_pickup: 'Готов к выдаче',
      delivered: 'Доставлен',
      returned: 'Возвращен',
    } as Record<string, string>
  )[status] ?? status
}

async function loadOrder() {
  const orderNumber = String(route.params.orderNumber ?? '')

  if (!orderNumber) {
    hasError.value = true
    return
  }

  isLoading.value = true

  try {
    order.value = await cartStore.loadOrderDetails(orderNumber)
    hasError.value = false
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

onMounted(loadOrder)
</script>

<template>
  <main class="success-page">
    <p v-if="isLoading">Загружаем заказ...</p>
    <p v-if="hasError" class="error">Не удалось загрузить данные заказа.</p>

    <section v-if="order" class="card">
      <h1>Спасибо за заказ</h1>
      <p class="subtitle">
        Номер: <strong>{{ order.order_number }}</strong>
      </p>
      <p class="subtitle">Дата: {{ formatDate(order.placed_at) }}</p>
      <p class="subtitle">Статус заказа: {{ resolveOrderStatusLabel(order.order_status) }}</p>
      <p class="subtitle">Статус оплаты: {{ resolvePaymentStatusLabel(order.payment_status) }}</p>
      <p class="subtitle">Статус исполнения: {{ resolveFulfillmentStatusLabel(order.fulfillment_status) }}</p>
      <p class="subtitle">Доставка: {{ order.delivery_method === 'courier' ? 'Курьер' : 'Самовывоз' }}</p>
      <p class="subtitle">Оплата: {{ order.payment_method === 'card' ? 'Картой онлайн' : 'Наличными' }}</p>
      <p v-if="order.promo_code" class="subtitle">Промокод: {{ order.promo_code }}</p>
      <p v-if="order.payment_transactions.length" class="subtitle">
        Транзакция: {{ resolvePaymentStatusLabel(order.payment_transactions[0]?.status ?? '') }}
      </p>

      <h2>Состав заказа</h2>
      <article v-for="item in order.items" :key="`${item.product_slug}-${item.product_name}`" class="line">
        <div>
          <p>{{ item.product_name }}</p>
          <small v-if="item.variant_label">Размер: {{ item.variant_label }}</small>
          <br v-if="item.variant_label" />
          <small>{{ item.qty }} x {{ formatPrice(item.unit_price) }}</small>
        </div>
        <strong>{{ formatPrice(item.total_price) }}</strong>
      </article>

      <div class="total">
        <div class="totals">
          <span>Подытог: {{ formatPrice(order.subtotal) }}</span>
          <span>Скидка: -{{ formatPrice(order.discount_total) }}</span>
          <span>Доставка: {{ formatPrice(order.delivery_total) }}</span>
          <strong>Итого: {{ formatPrice(order.total) }}</strong>
        </div>
      </div>

      <div class="actions">
        <RouterLink to="/catalog">Продолжить покупки</RouterLink>
        <RouterLink to="/cart">К корзине</RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.success-page {
  width: min(920px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.card {
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  padding: 18px 16px;
}

.card h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 72px);
  line-height: 0.9;
}

.subtitle {
  color: #55617a;
}

.card h2 {
  margin-top: 14px;
  margin-bottom: 8px;
}

.line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #efebe4;
}

.line small {
  color: #707a8f;
}

.total {
  margin-top: 12px;
}

.totals {
  display: grid;
  gap: 4px;
}

.actions {
  margin-top: 14px;
  display: flex;
  gap: 10px;
}

.actions a {
  color: #1f2233;
}

.error {
  color: #a83a0f;
}
</style>
