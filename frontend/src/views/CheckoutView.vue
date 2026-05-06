<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { trackEvent } from '@/lib/analytics'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { toast } from '@/lib/toast'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useCheckoutPreview } from '@/composables/useCheckoutPreview'
import {
  clearCheckoutIncentives,
  loadCheckoutIncentives,
} from '@/lib/checkout-context'

const authStore = useAuthStore()
const cartStore = useCartStore()
const router = useRouter()
const route = useRoute()
const { items, total, totalItems, checkoutOptions, isLoading: isCartLoading, subtotal: cartSubtotal } =
  storeToRefs(cartStore)
const { user } = storeToRefs(authStore)

const customerName = ref('')
const customerEmail = ref('')
const customerPhone = ref('')
const comment = ref('')
const deliveryMethod = ref('')
const paymentMethod = ref('')
const promoCode = ref('')
const loyaltyPointsToSpend = ref(0)
const checkoutError = ref('')
const checkoutLoading = ref(false)

const {
  previewLoading,
  loyaltyEnabled,
  displayedSubtotal,
  displayedDiscount,
  displayedLoyaltyDiscount,
  displayedDelivery,
  displayedTotal,
  refreshPreview,
  schedulePreviewRefresh,
} = useCheckoutPreview({
  deliveryMethod,
  promoCode,
  loyaltyPointsToSpend,
  customerEmail,
})

function prefillCheckoutCustomerFields() {
  const profile = user.value
  if (!profile) {
    return
  }

  if (!customerName.value.trim() && profile.name) {
    customerName.value = profile.name
  }

  if (!customerEmail.value.trim() && profile.email) {
    customerEmail.value = profile.email
  }

  if (!customerPhone.value.trim() && profile.phone) {
    customerPhone.value = profile.phone
  }
}

function formatPrice(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

const hasUnavailableItems = computed(() => items.value.some((item) => !item.available))
const showPageSkeleton = computed(() => isCartLoading.value && items.value.length === 0)
const showOrderSummarySkeleton = computed(
  () => showPageSkeleton.value || (!checkoutOptions.value && (isCartLoading.value || previewLoading.value)),
)
const showOrderSummaryBusy = computed(
  () => !showOrderSummarySkeleton.value && (isCartLoading.value || previewLoading.value),
)

const unavailableCartMessage = computed(
  () =>
    items.value.find((item) => !item.available)?.availability_message
    ?? 'В корзине есть недоступные товары. Обнови состав заказа.',
)
const canCheckout = computed(
  () =>
    items.value.length > 0
    && !checkoutLoading.value
    && !!deliveryMethod.value
    && !!paymentMethod.value
    && !hasUnavailableItems.value,
)
const deliveryMethods = computed(() => checkoutOptions.value?.delivery_methods ?? [])
const paymentMethods = computed(() => checkoutOptions.value?.payment_methods ?? [])
const deliveryMethodOptions = computed(() =>
  deliveryMethods.value.map((method) => ({
    label: `${method.name}${method.is_test_mode ? ' · тест' : ''} (${formatPrice(method.fee)})`,
    value: String(method.code),
  })),
)
const paymentMethodOptions = computed(() =>
  paymentMethods.value.map((method) => ({
    label: `${method.name}${method.is_test_mode ? ' · тест' : ''}`,
    value: String(method.code),
  })),
)

async function submitCheckout() {
  checkoutError.value = ''
  checkoutLoading.value = true

  try {
    void trackEvent('begin_checkout', {
      total: total.value,
      items_count: totalItems.value,
    })

    const order = await cartStore.checkout({
      customer_name: customerName.value,
      customer_email: customerEmail.value,
      customer_phone: customerPhone.value,
      delivery_method: deliveryMethod.value,
      payment_method: paymentMethod.value,
      promo_code: promoCode.value.trim() || undefined,
      loyalty_points_to_spend: loyaltyEnabled.value ? loyaltyPointsToSpend.value : undefined,
      comment: comment.value,
    })

    void trackEvent('purchase', {
      order_number: order.order_number,
      total: order.total,
      items_count: order.items_count,
    })

    clearCheckoutIncentives()
    toast.success(`Заказ ${order.order_number} оформлен`)

    void router.push({
      name: 'order-success',
      params: { orderNumber: order.order_number },
    })
  } catch (error) {
    console.error(error)
    checkoutError.value = hasUnavailableItems.value
      ? unavailableCartMessage.value
      : 'Не удалось оформить заказ. Проверь данные и повтори попытку.'
    toast.error(
      checkoutError.value,
    )
  } finally {
    checkoutLoading.value = false
  }
}

function applyPromoFromQuery() {
  const raw = route.query.promo
  const code = typeof raw === 'string' ? raw.trim() : ''
  if (code) {
    promoCode.value = code
  }
}

function hydrateIncentivesFromCart() {
  const fromCart = loadCheckoutIncentives()
  if (fromCart) {
    promoCode.value = fromCart.promoCode.trim()
    loyaltyPointsToSpend.value = Math.max(0, Math.floor(fromCart.loyaltyPointsToSpend))
  }
  applyPromoFromQuery()
}

onMounted(async () => {
  hydrateIncentivesFromCart()
  if (!user.value) {
    await authStore.loadMe()
  }
  prefillCheckoutCustomerFields()
  await cartStore.loadCheckoutOptions()
  deliveryMethod.value = deliveryMethods.value[0]?.code != null ? String(deliveryMethods.value[0].code) : ''
  paymentMethod.value = paymentMethods.value[0]?.code != null ? String(paymentMethods.value[0].code) : ''
  await cartStore.loadCart()

  if (items.value.length === 0) {
    void router.replace({ name: 'cart' })
    return
  }

  await refreshPreview()
})

watch(
  () =>
    [
      deliveryMethod.value,
      promoCode.value,
      customerEmail.value,
      loyaltyPointsToSpend.value,
      cartSubtotal.value,
    ],
  () => {
    if (loyaltyPointsToSpend.value < 0) {
      loyaltyPointsToSpend.value = 0
      return
    }

    schedulePreviewRefresh()
  },
)

watch(
  () => route.query.promo,
  () => {
    hydrateIncentivesFromCart()
    schedulePreviewRefresh()
  },
)

watch(
  () => user.value,
  () => {
    prefillCheckoutCustomerFields()
  },
  { immediate: true },
)
</script>

<template>
  <main class="checkout-page">
    <nav class="breadcrumbs" aria-label="Breadcrumbs">
      <RouterLink to="/">Главная</RouterLink>
      <span>/</span>
      <RouterLink :to="{ name: 'cart' }">Корзина</RouterLink>
      <span>/</span>
      <span>Оформление</span>
    </nav>

    <header class="checkout-page__header">
      <h1>Оформление заказа</h1>
      <p>Контакты, доставка и оплата. Промокод и баллы задаются в корзине.</p>
    </header>

    <section v-if="showPageSkeleton" class="checkout-with-sidebar">
      <div class="checkout-form checkout-form--main checkout-form--skeleton" aria-hidden="true">
        <AppSkeleton width="200px" height="28px" />
        <div class="checkout-skeleton__field">
          <AppSkeleton width="72px" height="14px" />
          <AppSkeleton width="100%" height="48px" radius="10px" />
        </div>
        <div class="checkout-skeleton__field">
          <AppSkeleton width="80px" height="14px" />
          <AppSkeleton width="100%" height="48px" radius="10px" />
        </div>
        <div class="checkout-skeleton__field">
          <AppSkeleton width="92px" height="14px" />
          <AppSkeleton width="100%" height="48px" radius="10px" />
        </div>
      </div>
      <aside class="order-summary order-summary--placeholder">
        <div class="order-summary__inner order-summary__inner--skeleton">
          <AppSkeleton width="140px" height="22px" />
          <AppSkeleton width="100%" height="16px" />
          <AppSkeleton width="100%" height="16px" />
          <AppSkeleton width="100%" height="48px" radius="12px" />
        </div>
      </aside>
    </section>

    <section v-else class="checkout-with-sidebar">
      <form id="checkout-form" class="checkout-form checkout-form--main" @submit.prevent="submitCheckout">
        <h2 class="checkout-form__title">Данные и доставка</h2>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-customer-name">Имя</label>
          <input
            id="checkout-customer-name"
            v-model="customerName"
            class="checkout__input"
            type="text"
            name="customer_name"
            autocomplete="name"
            required
          />
        </div>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-customer-email">Email</label>
          <input
            id="checkout-customer-email"
            v-model="customerEmail"
            class="checkout__input"
            type="email"
            name="customer_email"
            autocomplete="email"
            required
          />
        </div>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-customer-phone">Телефон</label>
          <input
            id="checkout-customer-phone"
            v-model="customerPhone"
            class="checkout__input"
            type="tel"
            name="customer_phone"
            autocomplete="tel"
            required
          />
        </div>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-delivery">Доставка</label>
          <select id="checkout-delivery" v-model="deliveryMethod" class="checkout__select" required>
            <option
              v-for="opt in deliveryMethodOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-payment">Оплата</label>
          <select id="checkout-payment" v-model="paymentMethod" class="checkout__select" required>
            <option
              v-for="opt in paymentMethodOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <div class="checkout-form__field">
          <label class="checkout-form__label" for="checkout-comment">Комментарий</label>
          <textarea
            id="checkout-comment"
            v-model="comment"
            class="checkout__textarea"
            name="comment"
            rows="3"
            placeholder="Комментарий к заказу"
          />
        </div>

        <p v-if="hasUnavailableItems" class="error error--soft">
          {{ unavailableCartMessage }}
        </p>
        <p v-if="checkoutError" class="error">{{ checkoutError }}</p>
      </form>

      <aside class="order-summary" :class="{ 'order-summary--busy': showOrderSummaryBusy }">
        <template v-if="showOrderSummarySkeleton">
          <div class="order-summary__inner order-summary__inner--skeleton">
            <AppSkeleton width="140px" height="22px" />
            <AppSkeleton width="100%" height="16px" />
            <AppSkeleton width="100%" height="16px" />
            <AppSkeleton width="100%" height="48px" radius="12px" />
          </div>
        </template>
        <template v-else>
          <div class="order-summary__inner">
            <h2 class="order-summary__title">Ваш заказ</h2>
            <dl class="order-summary__lines">
              <div class="order-summary__line">
                <dt>Товары, {{ totalItems }} шт.</dt>
                <dd>{{ formatPrice(displayedSubtotal) }}</dd>
              </div>
              <div v-if="displayedDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Скидка</dt>
                <dd>−{{ formatPrice(displayedDiscount) }}</dd>
              </div>
              <div v-if="loyaltyEnabled && displayedLoyaltyDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Баллы</dt>
                <dd>−{{ formatPrice(displayedLoyaltyDiscount) }}</dd>
              </div>
              <div class="order-summary__line order-summary__line--muted">
                <dt>Доставка</dt>
                <dd>{{ formatPrice(displayedDelivery) }}</dd>
              </div>
              <div class="order-summary__line order-summary__total">
                <dt>Итого</dt>
                <dd>{{ formatPrice(displayedTotal) }}</dd>
              </div>
            </dl>
            <AppSkeleton
              v-if="previewLoading"
              class="order-summary__preview-pulse"
              inline
              width="120px"
              height="12px"
              radius="999px"
            />

            <div class="order-summary__cta-wrap">
              <button
                type="submit"
                form="checkout-form"
                class="order-summary__checkout"
                :disabled="!canCheckout || checkoutLoading"
              >
                {{ checkoutLoading ? 'Оформляем…' : 'Подтвердить заказ' }}
              </button>
            </div>
            <RouterLink class="order-summary__back-link" :to="{ name: 'cart' }">← В корзину</RouterLink>
          </div>
        </template>
      </aside>
    </section>
  </main>
</template>

<style scoped>
.checkout-page {
  --cart-accent: #000;
  width: min(var(--layout-max-width), 92vw);
  margin: 0 auto;
  padding: 24px 0 56px;
}

.breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--muted-foreground);
  font-size: 14px;
}

.breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.breadcrumbs a:hover {
  color: var(--cart-accent);
}

.checkout-page__header h1 {
  font-size: clamp(26px, 4vw, 36px);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.15;
  margin: 0;
}

.checkout-page__header p {
  margin-top: 8px;
  color: var(--muted-foreground);
  font-size: 15px;
}

.checkout-with-sidebar {
  margin-top: 22px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  align-items: start;
}

.checkout-form {
  position: relative;
  border-radius: 16px;
  padding: 22px 22px 24px;
  border: 1px solid color-mix(in srgb, var(--border) 90%, transparent);
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
}

.checkout-form--main {
  min-width: 0;
}

.checkout-form--skeleton {
  display: grid;
  gap: 14px;
}

.checkout-form__title {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
}

.checkout-form__field {
  margin-top: 12px;
  display: grid;
  gap: 6px;
}

.checkout-form__label {
  font-size: 14px;
  color: #475569;
  font-weight: 600;
}

.checkout__input,
.checkout__select,
.checkout__textarea {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  font-family: inherit;
  font-size: 16px;
  line-height: 1.4;
  color: var(--foreground);
}

.checkout__input,
.checkout__textarea {
  min-height: 48px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--background);
}

.checkout__textarea {
  min-height: 96px;
  resize: vertical;
}

.checkout__input:focus,
.checkout__textarea:focus,
.checkout__select:focus {
  outline: none;
  border-color: #000;
}

.checkout__select {
  min-height: 50px;
  padding: 10px 36px 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background-color: var(--background);
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
}

.order-summary {
  position: sticky;
  top: calc(var(--site-header-sticky-offset) + 12px);
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
  background: var(--card);
  box-shadow: 0 8px 30px rgb(15 23 42 / 7%);
}

.order-summary--placeholder {
  min-height: 200px;
}

.order-summary--busy .order-summary__inner {
  opacity: 0.65;
}

.order-summary__inner {
  padding: 20px 18px 22px;
}

.order-summary__inner--skeleton {
  display: grid;
  gap: 14px;
}

.order-summary__title {
  margin: 0 0 14px;
  font-size: 18px;
  font-weight: 700;
}

.order-summary__lines {
  margin: 0;
  display: grid;
  gap: 8px;
}

.order-summary__line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  font-size: 14px;
}

.order-summary__line dt {
  margin: 0;
  color: var(--muted-foreground);
  font-weight: 500;
}

.order-summary__line dd {
  margin: 0;
  font-weight: 600;
  color: var(--foreground);
}

.order-summary__line--muted dd {
  font-weight: 500;
  color: var(--muted-foreground);
}

.order-summary__total {
  margin-top: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
  font-size: 16px;
}

.order-summary__total dt {
  color: var(--foreground);
  font-weight: 700;
}

.order-summary__total dd {
  font-size: 18px;
  font-weight: 700;
}

.order-summary__preview-pulse {
  margin-top: 8px;
}

.order-summary__cta-wrap {
  margin-top: 16px;
}

.order-summary__checkout {
  width: 100%;
  min-height: 48px;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  border: 1px solid var(--border);
  cursor: pointer;
  background: transparent;
  color: var(--foreground);
}

.order-summary__checkout:hover:not(:disabled) {
  background: #000;
  color: #fff;
  border-color: #000;
}

.order-summary__checkout:disabled {
  opacity: 0.5;
  cursor: default;
}

.order-summary__back-link {
  display: block;
  margin-top: 14px;
  text-align: center;
  font-size: 14px;
  color: var(--muted-foreground);
  text-decoration: none;
}

.order-summary__back-link:hover {
  color: #000;
}

.error {
  margin-top: 10px;
  color: #a83a0f;
  font-size: 14px;
}

.error--soft {
  margin-top: 0;
}

.checkout-skeleton__field {
  display: grid;
  gap: 6px;
}

@media (max-width: 1020px) {
  .checkout-with-sidebar {
    grid-template-columns: 1fr;
  }

  .order-summary {
    position: static;
    order: 2;
  }

  .checkout-form--main {
    order: 1;
  }
}
</style>
