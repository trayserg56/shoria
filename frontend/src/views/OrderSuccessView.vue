<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { CheckCircle2, Copy, Package } from 'lucide-vue-next'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { toast } from '@/lib/toast'
import { useCartStore } from '@/stores/cart'

type OrderDetails = {
  order_number: string
  status: string
  checkout_kind?: string
  order_status: string
  payment_status: string
  fulfillment_status: string
  refund_status: string
  payment_transaction_status: string | null
  delivery_method: string
  payment_method: string
  promo_code: string | null
  gift_certificate_code: string | null
  purchased_gift_certificate?: {
    code: string
    initial_amount: number
    balance_remaining: number
    status: string
    currency: string
  } | null
  gift_certificate_discount_total: number
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

const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)
let pollAttempts = 0
const MAX_GIFT_POLL_ATTEMPTS = 48

const isGiftCertificateOrder = computed(
  () => order.value?.checkout_kind === 'gift_certificate',
)

const purchasedGift = computed(() => order.value?.purchased_gift_certificate ?? null)

const giftCertificatePending = computed(() => {
  if (!isGiftCertificateOrder.value) {
    return false
  }

  if (purchasedGift.value?.code) {
    return false
  }

  const ps = order.value?.payment_status ?? ''

  if (ps === 'failed' || ps === 'cancelled') {
    return false
  }

  return true
})

function stopGiftPoll() {
  if (pollTimer.value !== null) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

function maybeStartGiftPoll() {
  stopGiftPoll()

  if (!giftCertificatePending.value) {
    return
  }

  pollAttempts = 0
  pollTimer.value = window.setInterval(() => {
    void (async () => {
      pollAttempts += 1

      if (pollAttempts > MAX_GIFT_POLL_ATTEMPTS) {
        stopGiftPoll()
        return
      }

      const orderNumber = String(route.params.orderNumber ?? '')
      if (!orderNumber) {
        stopGiftPoll()
        return
      }

      try {
        order.value = await cartStore.loadOrderDetails(orderNumber)
      } catch {
        /* keep polling briefly */
      }

      if (order.value?.purchased_gift_certificate?.code) {
        stopGiftPoll()
        return
      }

      const ps = order.value?.payment_status ?? ''
      if (ps === 'failed' || ps === 'cancelled') {
        stopGiftPoll()
      }
    })()
  }, 3500)
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

function deliveryMethodLabel(code: string) {
  return (
    {
      courier: 'Курьер',
      pickup: 'Самовывоз',
    } as Record<string, string>
  )[code] ?? code
}

function paymentMethodLabel(code: string) {
  return (
    {
      card: 'Картой онлайн',
      cash: 'Наличными',
    } as Record<string, string>
  )[code] ?? code
}

async function copyPurchasedGiftCode() {
  const code = purchasedGift.value?.code
  if (!code) {
    return
  }

  try {
    await navigator.clipboard.writeText(code)
    toast.success('Код сертификата скопирован')
  } catch {
    toast.error('Не удалось скопировать код')
  }
}

async function copyOrderNumber() {
  if (!order.value?.order_number) {
    return
  }
  try {
    await navigator.clipboard.writeText(order.value.order_number)
    toast.success('Номер скопирован')
  } catch {
    toast.error('Не удалось скопировать номер')
  }
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
    maybeStartGiftPoll()
  } catch (error) {
    console.error(error)
    hasError.value = true
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadOrder()
})

onBeforeUnmount(() => {
  stopGiftPoll()
})
</script>

<template>
  <main class="success-page">
    <nav class="success-breadcrumbs" aria-label="Навигация">
      <RouterLink to="/">Главная</RouterLink>
      <span class="success-breadcrumbs__sep">/</span>
      <span>Заказ оформлен</span>
    </nav>

    <section v-if="isLoading && !order" class="success-card success-card--skeleton" aria-hidden="true">
      <div class="success-hero success-hero--skeleton">
        <AppSkeleton width="64px" height="64px" radius="50%" />
        <AppSkeleton width="min(100%, 280px)" height="40px" />
        <AppSkeleton width="min(100%, 220px)" height="16px" />
        <AppSkeleton width="100%" height="52px" radius="12px" />
      </div>
      <div class="success-skeleton-grid">
        <AppSkeleton width="100%" height="14px" />
        <AppSkeleton width="100%" height="14px" />
        <AppSkeleton width="100%" height="14px" />
        <AppSkeleton width="100%" height="14px" />
      </div>
      <AppSkeleton width="100%" height="72px" radius="12px" />
    </section>

    <p v-else-if="hasError" class="success-error">Не удалось загрузить данные заказа.</p>

    <template v-else-if="order">
      <section class="success-card">
        <header class="success-hero">
          <div class="success-hero__icon" aria-hidden="true">
            <CheckCircle2 :size="40" :stroke-width="1.5" />
          </div>
          <h1 class="success-hero__title">Спасибо за заказ</h1>
          <p class="success-hero__lead">
            <template v-if="isGiftCertificateOrder">
              Вы купили подарочный сертификат. Код для передачи другому человеку будет ниже — после подтверждения оплаты он появится автоматически.
            </template>
            <template v-else>
              Сохраните номер заказа — по нему можно отследить статус. Краткая сводка ниже.
            </template>
          </p>

          <div class="order-number-card">
            <div class="order-number-card__head">
              <span class="order-number-card__label">Номер заказа</span>
              <button
                type="button"
                class="order-number-card__copy"
                aria-label="Скопировать номер заказа"
                @click="copyOrderNumber"
              >
                <Copy :size="16" :stroke-width="1.75" aria-hidden="true" />
                <span>Скопировать</span>
              </button>
            </div>
            <p class="order-number-card__value">
              {{ order.order_number }}
            </p>
          </div>

          <div v-if="isGiftCertificateOrder" class="gift-cert-card">
            <template v-if="purchasedGift?.code">
              <div class="gift-cert-card__head">
                <span class="gift-cert-card__label">Код подарочного сертификата</span>
                <button
                  type="button"
                  class="order-number-card__copy"
                  aria-label="Скопировать код сертификата"
                  @click="copyPurchasedGiftCode"
                >
                  <Copy :size="16" :stroke-width="1.75" aria-hidden="true" />
                  <span>Скопировать</span>
                </button>
              </div>
              <p class="gift-cert-card__value">{{ purchasedGift.code }}</p>
              <p class="gift-cert-card__hint">
                Номинал {{ formatPrice(purchasedGift.initial_amount) }}. Код также в разделе
                <RouterLink to="/account/gift-certificates">«Сертификаты»</RouterLink>
                в профиле.
              </p>
            </template>
            <div v-else class="gift-cert-card--pending" role="status">
              <p class="gift-cert-card__pending-title">Код появится после оплаты</p>
              <p class="gift-cert-card__pending-text">
                Завершите оплату, если браузер не открыл платёжную страницу. Мы обновим эту страницу, как только платёж пройдёт.
              </p>
            </div>
          </div>
        </header>

        <div class="success-section">
          <h2 class="success-section__title">Статус и доставка</h2>
          <dl class="meta-grid">
            <div class="meta-pair">
              <dt>Дата оформления</dt>
              <dd>{{ formatDate(order.placed_at) }}</dd>
            </div>
            <div class="meta-pair">
              <dt>Статус заказа</dt>
              <dd>{{ resolveOrderStatusLabel(order.order_status) }}</dd>
            </div>
            <div class="meta-pair">
              <dt>Оплата</dt>
              <dd>{{ resolvePaymentStatusLabel(order.payment_status) }}</dd>
            </div>
            <div class="meta-pair">
              <dt>Сборка и доставка</dt>
              <dd>{{ resolveFulfillmentStatusLabel(order.fulfillment_status) }}</dd>
            </div>
            <div class="meta-pair">
              <dt>Способ доставки</dt>
              <dd>{{ deliveryMethodLabel(order.delivery_method) }}</dd>
            </div>
            <div class="meta-pair">
              <dt>Способ оплаты</dt>
              <dd>{{ paymentMethodLabel(order.payment_method) }}</dd>
            </div>
            <div v-if="order.payment_transactions.length" class="meta-pair">
              <dt>Транзакция</dt>
              <dd>
                {{ resolvePaymentStatusLabel(order.payment_transactions[0]?.status ?? '') }}
              </dd>
            </div>
            <div v-if="order.promo_code" class="meta-pair">
              <dt>Промокод</dt>
              <dd>{{ order.promo_code }}</dd>
            </div>
            <div v-if="purchasedGift?.code" class="meta-pair">
              <dt>Код сертификата</dt>
              <dd class="meta-pair__mono">{{ purchasedGift.code }}</dd>
            </div>
            <div v-if="order.gift_certificate_code" class="meta-pair">
              <dt>Подарочный сертификат</dt>
              <dd>{{ order.gift_certificate_code }}</dd>
            </div>
            <div v-if="order.gift_certificate_discount_total > 0" class="meta-pair">
              <dt>Списано с сертификата</dt>
              <dd>{{ formatPrice(order.gift_certificate_discount_total) }}</dd>
            </div>
          </dl>
        </div>

        <div class="success-section">
          <h2 class="success-section__title">Состав заказа</h2>
          <ul class="order-lines">
            <li
              v-for="(item, index) in order.items"
              :key="`${item.product_slug}-${index}`"
              class="order-line"
            >
              <div v-if="item.image_url" class="order-line__thumb">
                <img :src="item.image_url" :alt="item.product_name" loading="lazy" />
              </div>
              <div v-else class="order-line__thumb order-line__thumb--placeholder" aria-hidden="true">
                <Package :size="22" :stroke-width="1.5" />
              </div>
              <div class="order-line__body">
                <p class="order-line__name">{{ item.product_name }}</p>
                <p v-if="item.variant_label" class="order-line__variant">
                  {{ item.variant_label }}
                </p>
                <p class="order-line__meta">
                  {{ item.qty }} шт. × {{ formatPrice(item.unit_price) }}
                </p>
              </div>
              <p class="order-line__price">{{ formatPrice(item.total_price) }}</p>
            </li>
          </ul>
        </div>

        <div class="success-section success-section--totals">
          <h2 class="success-section__title">Итого</h2>
          <dl class="totals-lines">
            <div class="totals-line">
              <dt>Подытог</dt>
              <dd>{{ formatPrice(order.subtotal) }}</dd>
            </div>
            <div v-if="order.discount_total > 0" class="totals-line totals-line--muted">
              <dt>Скидка</dt>
              <dd>−{{ formatPrice(order.discount_total) }}</dd>
            </div>
            <div class="totals-line totals-line--muted">
              <dt>Доставка</dt>
              <dd>{{ formatPrice(order.delivery_total) }}</dd>
            </div>
            <div class="totals-line totals-line--grand">
              <dt>Итого</dt>
              <dd>{{ formatPrice(order.total) }}</dd>
            </div>
          </dl>
        </div>

        <footer class="success-actions">
          <RouterLink to="/catalog" class="success-actions__primary">Продолжить покупки</RouterLink>
          <RouterLink :to="{ name: 'cart' }" class="success-actions__secondary">← В корзину</RouterLink>
        </footer>
      </section>
    </template>
  </main>
</template>

<style scoped>
.success-page {
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 24px 0 64px;
}

.success-breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
  font-size: 14px;
  color: var(--muted-foreground);
}

.success-breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.success-breadcrumbs a:hover {
  color: #000;
}

.success-breadcrumbs__sep {
  color: var(--border);
}

.success-card {
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--border) 90%, transparent);
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
  padding: clamp(20px, 4vw, 32px);
}

.success-card--skeleton {
  display: grid;
  gap: 20px;
}

.success-hero {
  text-align: center;
  padding-bottom: 8px;
}

.success-hero--skeleton {
  display: grid;
  place-items: center;
  gap: 14px;
  text-align: center;
}

.success-hero__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--muted);
  color: var(--foreground);
}

.success-hero__title {
  margin: 0 0 10px;
  font-size: clamp(26px, 4.4vw, 36px);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.15;
  color: var(--foreground);
}

.success-hero__lead {
  margin: 0 auto 22px;
  max-width: 32rem;
  font-size: 15px;
  line-height: 1.5;
  color: var(--muted-foreground);
}

.order-number-card {
  margin: 0 auto;
  max-width: 28rem;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--background);
  padding: 14px 16px;
  text-align: left;
}

.order-number-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.order-number-card__label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted-foreground);
}

.order-number-card__copy {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  margin: 0;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: var(--foreground);
  cursor: pointer;
}

.order-number-card__copy:hover {
  border-color: #000;
  background: #000;
  color: #fff;
}

.order-number-card__value {
  margin: 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: 0.02em;
  word-break: break-all;
  color: var(--foreground);
}

.gift-cert-card {
  margin: 16px auto 0;
  max-width: 28rem;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--muted);
  padding: 14px 16px;
  text-align: left;
}

.gift-cert-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.gift-cert-card__label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted-foreground);
}

.gift-cert-card__value {
  margin: 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: 0.02em;
  word-break: break-all;
}

.gift-cert-card__hint {
  margin: 10px 0 0;
  font-size: 13px;
  line-height: 1.45;
  color: var(--muted-foreground);
}

.gift-cert-card__hint a {
  color: inherit;
  font-weight: 600;
}

.gift-cert-card--pending .gift-cert-card__pending-title {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 700;
}

.gift-cert-card--pending .gift-cert-card__pending-text {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  color: var(--muted-foreground);
}

.meta-pair__mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 14px;
  word-break: break-all;
}

.success-section {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid var(--border);
}

.success-card > header + .success-section {
  margin-top: 24px;
}

.success-section__title {
  margin: 0 0 14px;
  font-size: 16px;
  font-weight: 700;
  color: var(--foreground);
}

.success-section--totals .success-section__title {
  margin-bottom: 12px;
}

.meta-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px 20px;
}

@media (min-width: 640px) {
  .meta-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.meta-pair {
  display: grid;
  gap: 4px;
}

.meta-pair dt {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted-foreground);
}

.meta-pair dd {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: var(--foreground);
}

.order-lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0;
}

.order-line {
  display: grid;
  grid-template-columns: 56px 1fr auto;
  align-items: start;
  gap: 12px;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}

.order-line:last-child {
  border-bottom: none;
}

.order-line__thumb {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border);
  background: var(--muted);
}

.order-line__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.order-line__thumb--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted-foreground);
}

.order-line__body {
  min-width: 0;
}

.order-line__name {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--foreground);
}

.order-line__variant {
  margin: 0 0 4px;
  font-size: 13px;
  color: var(--muted-foreground);
}

.order-line__meta {
  margin: 0;
  font-size: 13px;
  color: var(--muted-foreground);
}

.order-line__price {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  white-space: nowrap;
  color: var(--foreground);
}

.totals-lines {
  margin: 0;
  display: grid;
  gap: 8px;
  max-width: 22rem;
  margin-left: auto;
}

.totals-line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 16px;
  font-size: 14px;
}

.totals-line dt {
  margin: 0;
  color: var(--muted-foreground);
  font-weight: 500;
}

.totals-line dd {
  margin: 0;
  font-weight: 600;
  color: var(--foreground);
}

.totals-line--muted dd {
  font-weight: 500;
  color: var(--muted-foreground);
}

.totals-line--grand {
  margin-top: 8px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
  font-size: 16px;
}

.totals-line--grand dt {
  font-weight: 700;
  color: var(--foreground);
}

.totals-line--grand dd {
  font-size: 18px;
  font-weight: 700;
}

.success-actions {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 12px 20px;
}

.success-actions__primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 24px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--foreground);
}

.success-actions__primary:hover {
  background: #000;
  color: #fff;
  border-color: #000;
}

.success-actions__secondary {
  font-size: 14px;
  font-weight: 500;
  color: var(--muted-foreground);
  text-decoration: none;
}

.success-actions__secondary:hover {
  color: #000;
}

.success-skeleton-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 20px;
}

.success-error {
  color: #a83a0f;
  font-size: 15px;
}

@media (max-width: 520px) {
  .totals-lines {
    margin-left: 0;
    max-width: none;
  }
}
</style>
