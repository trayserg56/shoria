<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { trackEvent } from '@/lib/analytics'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { toProductRoute } from '@/lib/product-route'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const authStore = useAuthStore()
const cartStore = useCartStore()
const router = useRouter()
const { items, total, totalItems, lastOrder, orderHistory, checkoutOptions, isLoading: isCartLoading } = storeToRefs(cartStore)
const { user } = storeToRefs(authStore)

const customerName = ref('')
const customerEmail = ref('')
const customerPhone = ref('')
const comment = ref('')
const deliveryMethod = ref('')
const paymentMethod = ref('')
const promoCode = ref('')
const loyaltyPointsToSpend = ref(0)
const promoStatusMessage = ref('')
const promoStatusApplied = ref(false)
const checkoutError = ref('')
const checkoutLoading = ref(false)
const previewLoading = ref(false)

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
const showCartSkeleton = computed(() => isCartLoading.value && items.value.length === 0)
const showCheckoutSkeleton = computed(
  () => showCartSkeleton.value || (!checkoutOptions.value && (isCartLoading.value || previewLoading.value)),
)
const showCheckoutOverlay = computed(
  () => !showCheckoutSkeleton.value && (isCartLoading.value || previewLoading.value),
)
const unavailableCartMessage = computed(
  () =>
    items.value.find((item) => !item.available)?.availability_message
    ?? 'В корзине есть недоступные товары. Обнови состав заказа.',
)
const canCheckout = computed(
  () => items.value.length > 0 && !checkoutLoading.value && !!deliveryMethod.value && !hasUnavailableItems.value,
)
const deliveryMethods = computed(() => checkoutOptions.value?.delivery_methods ?? [])
const paymentMethods = computed(() => checkoutOptions.value?.payment_methods ?? [])
const subtotalAmount = ref(0)
const discountAmount = ref(0)
const loyaltyDiscountAmount = ref(0)
const deliveryAmount = ref(0)
const checkoutTotalPreview = ref(0)
const loyaltyMaxPoints = ref(0)
const loyaltyPointsBalance = ref(0)
const loyaltyPointsToEarn = ref(0)
const loyaltyAccrualPercent = ref(0)
const hasPreviewSnapshot = ref(false)
let previewRefreshTimer: ReturnType<typeof setTimeout> | null = null
const cartSubtotalFallback = computed(() => items.value.reduce((sum, item) => sum + item.total_price, 0))
const selectedDeliveryFee = computed(
  () => deliveryMethods.value.find((method) => method.code === deliveryMethod.value)?.fee ?? 0,
)
const displayedSubtotal = computed(() =>
  hasPreviewSnapshot.value ? subtotalAmount.value : cartSubtotalFallback.value,
)
const displayedDiscount = computed(() => (hasPreviewSnapshot.value ? discountAmount.value : 0))
const displayedLoyaltyDiscount = computed(() => (hasPreviewSnapshot.value ? loyaltyDiscountAmount.value : 0))
const displayedDelivery = computed(() =>
  hasPreviewSnapshot.value ? deliveryAmount.value : selectedDeliveryFee.value,
)
const loyaltyConfig = computed(() => checkoutOptions.value?.loyalty ?? null)
const loyaltyEnabled = computed(() => !!(loyaltyConfig.value?.is_enabled && user.value))
const displayedTotal = computed(
  () => displayedSubtotal.value - displayedDiscount.value - displayedLoyaltyDiscount.value + displayedDelivery.value,
)

async function refreshCheckoutPreview() {
  if (!deliveryMethod.value) {
    hasPreviewSnapshot.value = false
    return
  }

  previewLoading.value = true

  try {
    const preview = await cartStore.previewCheckout({
      delivery_method: deliveryMethod.value,
      promo_code: promoCode.value.trim() || undefined,
      customer_email: customerEmail.value.trim() || undefined,
      loyalty_points_to_spend: loyaltyEnabled.value ? loyaltyPointsToSpend.value : 0,
    })

    subtotalAmount.value = preview.subtotal
    discountAmount.value = preview.discount_total
    loyaltyDiscountAmount.value = preview.loyalty_discount_total
    deliveryAmount.value = preview.delivery_total
    checkoutTotalPreview.value = preview.total
    hasPreviewSnapshot.value = true
    promoStatusMessage.value = preview.promo.message ?? ''
    promoStatusApplied.value = preview.promo.is_applied
    loyaltyMaxPoints.value = preview.loyalty.max_points_to_spend
    loyaltyPointsBalance.value = preview.loyalty.points_balance
    loyaltyPointsToEarn.value = preview.loyalty.points_to_earn
    loyaltyAccrualPercent.value = preview.loyalty.accrual_percent
    if (loyaltyEnabled.value && loyaltyPointsToSpend.value > loyaltyMaxPoints.value) {
      loyaltyPointsToSpend.value = loyaltyMaxPoints.value
    }
  } catch (error) {
    console.error(error)
    hasPreviewSnapshot.value = false
    discountAmount.value = 0
    loyaltyDiscountAmount.value = 0
    deliveryAmount.value = selectedDeliveryFee.value
    subtotalAmount.value = cartSubtotalFallback.value
    checkoutTotalPreview.value = cartSubtotalFallback.value + selectedDeliveryFee.value
    loyaltyMaxPoints.value = 0
    loyaltyPointsBalance.value = 0
    loyaltyPointsToEarn.value = 0
    loyaltyAccrualPercent.value = 0
  } finally {
    previewLoading.value = false
  }
}

function scheduleCheckoutPreviewRefresh(delayMs = 300) {
  if (previewRefreshTimer !== null) {
    clearTimeout(previewRefreshTimer)
  }

  previewRefreshTimer = setTimeout(() => {
    previewRefreshTimer = null
    void refreshCheckoutPreview()
  }, delayMs)
}

async function increaseQty(itemId: number, qty: number) {
  await cartStore.updateQty(itemId, qty + 1)
}

async function decreaseQty(itemId: number, qty: number) {
  if (qty <= 1) {
    await cartStore.removeItem(itemId)
    return
  }

  await cartStore.updateQty(itemId, qty - 1)
}

async function removeItem(itemId: number) {
  await cartStore.removeItem(itemId)
}

function canIncreaseQty(item: (typeof items.value)[number]) {
  return item.available
}

function canDecreaseQty(item: (typeof items.value)[number]) {
  return item.qty > 1 || item.available_stock > 0
}

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

    void router.push({
      name: 'order-success',
      params: { orderNumber: order.order_number },
    })
  } catch (error) {
    console.error(error)
    checkoutError.value = hasUnavailableItems.value
      ? unavailableCartMessage.value
      : 'Не удалось оформить заказ. Проверь данные и повтори попытку.'
  } finally {
    checkoutLoading.value = false
  }
}

onMounted(async () => {
  if (!user.value) {
    await authStore.loadMe()
  }
  prefillCheckoutCustomerFields()
  await cartStore.loadCheckoutOptions()
  deliveryMethod.value = deliveryMethods.value[0]?.code ?? ''
  paymentMethod.value = paymentMethods.value[0]?.code ?? ''
  await cartStore.loadCart()
  await cartStore.loadOrderHistory()
  await refreshCheckoutPreview()
})

watch(
  () => [deliveryMethod.value, promoCode.value, customerEmail.value, loyaltyPointsToSpend.value, total.value, items.value.length],
  () => {
    if (loyaltyPointsToSpend.value < 0) {
      loyaltyPointsToSpend.value = 0
      return
    }

    scheduleCheckoutPreviewRefresh()
  },
)

onBeforeUnmount(() => {
  if (previewRefreshTimer !== null) {
    clearTimeout(previewRefreshTimer)
    previewRefreshTimer = null
  }
})

watch(
  () => user.value,
  () => {
    prefillCheckoutCustomerFields()
  },
  { immediate: true },
)
</script>

<template>
  <main class="cart-page">
    <header class="cart-header">
      <h1>Корзина</h1>
      <p>Проверь состав заказа и оформи покупку.</p>
    </header>

    <p v-if="lastOrder" class="success">
      Заказ <strong>{{ lastOrder.order_number }}</strong> успешно создан.
    </p>

    <section v-if="showCartSkeleton || items.length > 0" class="cart-layout">
      <div class="items" :class="{ 'items--busy': isCartLoading && items.length > 0 }">
        <div v-if="showCartSkeleton" class="items-skeleton">
          <article v-for="index in 2" :key="`cart-skeleton-${index}`" class="item item--skeleton">
            <AppSkeleton width="90px" height="90px" radius="10px" />
            <div class="item__body item__body--skeleton">
              <AppSkeleton width="48%" height="20px" />
              <AppSkeleton width="26%" height="14px" />
              <AppSkeleton width="22%" height="16px" />
              <div class="qty-row qty-row--skeleton">
                <AppSkeleton width="40px" height="36px" radius="10px" />
                <AppSkeleton width="24px" height="20px" radius="8px" />
                <AppSkeleton width="40px" height="36px" radius="10px" />
                <AppSkeleton width="92px" height="36px" radius="10px" />
              </div>
            </div>
            <AppSkeleton width="96px" height="24px" />
          </article>
        </div>

        <article v-for="item in items" :key="item.id" class="item" :class="{ 'item--unavailable': !item.available }">
          <img :src="resolveImageSrc(item.image_url)" :alt="item.product_name" @error="applyImageFallback" />
          <div class="item__body">
            <RouterLink :to="toProductRoute({ slug: item.product_slug })">
              {{ item.product_name }}
            </RouterLink>
            <p v-if="item.variant_label" class="variant">Размер: {{ item.variant_label }}</p>
            <p>{{ formatPrice(item.unit_price) }}</p>
            <p v-if="!item.available" class="availability availability--bad">
              {{ item.availability_message ?? 'Нет в наличии.' }}
            </p>
            <div class="qty-row">
              <button :disabled="!canDecreaseQty(item)" @click="decreaseQty(item.id, item.qty)">-</button>
              <span>{{ item.qty }}</span>
              <button :disabled="!canIncreaseQty(item)" @click="increaseQty(item.id, item.qty)">+</button>
              <button class="remove" @click="removeItem(item.id)">Удалить</button>
            </div>
          </div>
          <strong>{{ formatPrice(item.total_price) }}</strong>
        </article>

        <div v-if="isCartLoading && items.length > 0" class="items-overlay" aria-hidden="true">
          <article v-for="index in Math.min(items.length, 2)" :key="`cart-overlay-${index}`" class="item item--skeleton">
            <AppSkeleton width="90px" height="90px" radius="10px" />
            <div class="item__body item__body--skeleton">
              <AppSkeleton width="44%" height="20px" />
              <AppSkeleton width="24%" height="14px" />
              <AppSkeleton width="18%" height="16px" />
              <div class="qty-row qty-row--skeleton">
                <AppSkeleton width="40px" height="36px" radius="10px" />
                <AppSkeleton width="24px" height="20px" radius="8px" />
                <AppSkeleton width="40px" height="36px" radius="10px" />
              </div>
            </div>
            <AppSkeleton width="96px" height="24px" />
          </article>
        </div>
      </div>

      <form class="checkout" :class="{ 'checkout--busy': showCheckoutOverlay }" @submit.prevent="submitCheckout">
        <template v-if="showCheckoutSkeleton">
          <div class="checkout-skeleton" aria-hidden="true">
            <AppSkeleton width="180px" height="34px" />
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
            <div class="checkout-skeleton__field">
              <AppSkeleton width="94px" height="14px" />
              <AppSkeleton width="100%" height="48px" radius="10px" />
            </div>
            <div class="checkout-skeleton__field">
              <AppSkeleton width="78px" height="14px" />
              <AppSkeleton width="100%" height="48px" radius="10px" />
            </div>
            <div class="checkout-skeleton__field">
              <AppSkeleton width="94px" height="14px" />
              <AppSkeleton width="100%" height="48px" radius="10px" />
            </div>
            <div class="checkout-skeleton__field">
              <AppSkeleton width="112px" height="14px" />
              <AppSkeleton width="100%" height="112px" radius="10px" />
            </div>
            <div class="summary summary--stack checkout-skeleton__summary">
              <AppSkeleton width="34%" height="16px" />
              <AppSkeleton width="40%" height="16px" />
              <AppSkeleton width="36%" height="16px" />
              <AppSkeleton width="38%" height="16px" />
              <AppSkeleton width="44%" height="22px" />
            </div>
            <AppSkeleton width="100%" height="46px" radius="10px" />
          </div>
        </template>

        <template v-else>
          <h2>Оформление</h2>
          <label>
            Имя
            <Input v-model="customerName" class="checkout__input" type="text" required />
          </label>
          <label>
            Email
            <Input v-model="customerEmail" class="checkout__input" type="email" required />
          </label>
          <label>
            Телефон
            <Input v-model="customerPhone" class="checkout__input" type="text" required />
          </label>
          <label>
            Доставка
            <Select v-model="deliveryMethod" class="checkout__select" required>
              <option v-for="method in deliveryMethods" :key="method.code" :value="method.code">
                {{ method.name }}{{ method.is_test_mode ? ' · тест' : '' }} ({{ formatPrice(method.fee) }})
              </option>
            </Select>
          </label>
          <label>
            Оплата
            <Select v-model="paymentMethod" class="checkout__select" required>
              <option v-for="method in paymentMethods" :key="method.code" :value="method.code">
                {{ method.name }}{{ method.is_test_mode ? ' · тест' : '' }}
              </option>
            </Select>
          </label>
          <label>
            Промокод
            <Input v-model="promoCode" class="checkout__input" type="text" placeholder="Например, WELCOME10" />
          </label>
          <label v-if="loyaltyEnabled">
            Списать баллы
            <Input
              v-model.number="loyaltyPointsToSpend"
              class="checkout__input"
              type="number"
              min="0"
              :max="loyaltyMaxPoints"
              placeholder="0"
            />
            <small class="muted-inline">
              Доступно: {{ loyaltyPointsBalance }} · максимум к списанию: {{ loyaltyMaxPoints }}.
              Начислится за заказ: {{ loyaltyPointsToEarn }} ({{ loyaltyAccrualPercent.toFixed(2) }}%).
            </small>
          </label>
          <p v-if="promoStatusMessage" class="promo-status" :class="{ 'promo-status--ok': promoStatusApplied }">
            {{ promoStatusMessage }}
          </p>
          <label>
            Комментарий
            <Textarea v-model="comment" class="checkout__textarea" rows="3" />
          </label>
          <div class="summary summary--stack">
            <span>Товаров: {{ totalItems }}</span>
            <span>Подытог: {{ formatPrice(displayedSubtotal) }}</span>
            <span>Скидка: -{{ formatPrice(displayedDiscount) }}</span>
            <span v-if="loyaltyEnabled">Баллы: -{{ formatPrice(displayedLoyaltyDiscount) }}</span>
            <span>Доставка: {{ formatPrice(displayedDelivery) }}</span>
            <strong>Итого: {{ formatPrice(displayedTotal) }}</strong>
            <AppSkeleton
              v-if="previewLoading"
              class="preview-loading"
              inline
              width="136px"
              height="14px"
              radius="999px"
            />
          </div>
          <p v-if="hasUnavailableItems" class="error error--soft">
            {{ unavailableCartMessage }}
          </p>
          <Button type="submit" class="checkout__submit" :disabled="!canCheckout">
            {{
              checkoutLoading
                ? 'Оформляем...'
                : hasUnavailableItems
                  ? 'Недоступные товары в корзине'
                  : 'Оформить заказ'
            }}
          </Button>
          <p v-if="checkoutError" class="error">{{ checkoutError }}</p>
        </template>

        <div v-if="showCheckoutOverlay" class="checkout-overlay" aria-hidden="true">
          <div class="checkout-overlay__bar">
            <AppSkeleton width="100%" height="6px" radius="999px" />
          </div>
          <div class="checkout-overlay__summary">
            <AppSkeleton width="34%" height="14px" />
            <AppSkeleton width="42%" height="14px" />
            <AppSkeleton width="38%" height="14px" />
            <AppSkeleton width="48%" height="20px" />
            <AppSkeleton width="100%" height="42px" radius="10px" />
          </div>
        </div>
      </form>
    </section>

    <section v-else class="empty">
      <p>Корзина пока пустая.</p>
      <RouterLink to="/catalog">Перейти в каталог</RouterLink>
    </section>

    <section v-if="orderHistory.length > 0" class="history">
      <h2>Последние заказы</h2>
      <article v-for="order in orderHistory" :key="order.order_number" class="history-item">
        <div>
          <RouterLink :to="{ name: 'order-success', params: { orderNumber: order.order_number } }">
            {{ order.order_number }}
          </RouterLink>
          <p>{{ order.status }}</p>
        </div>
        <strong>{{ formatPrice(order.total) }}</strong>
      </article>
    </section>
  </main>
</template>

<style scoped>
.cart-page {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 20px 0 60px;
}

.cart-header h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 78px);
  line-height: 0.9;
}

.cart-header p {
  margin-top: 6px;
  color: var(--color-text-soft);
}

.success {
  margin-top: 10px;
  color: #137c3b;
}

.cart-layout {
  margin-top: 18px;
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 20px;
}

.items {
  position: relative;
  display: grid;
  gap: 12px;
  align-content: start;
  min-height: 520px;
  padding: 14px;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.88);
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.item {
  display: grid;
  grid-template-columns: 90px 1fr auto;
  gap: 12px;
  align-items: center;
  border-radius: 20px;
  padding: 16px;
  background: #fffdf9;
  border: 1px solid #efe2d4;
}

.item--skeleton {
  align-items: center;
}

.item--unavailable {
  border-color: #efb39a;
  background: #fff8f3;
}

.item__body--skeleton {
  display: grid;
  gap: 10px;
}

.item img {
  width: 90px;
  height: 90px;
  object-fit: cover;
  border-radius: 10px;
}

.item__body a {
  color: #1f2233;
  text-decoration: none;
  font-weight: 700;
}

.item__body p {
  color: #546179;
}

.item__body .variant {
  margin-top: 4px;
  color: #7a4a1d;
  font-size: 13px;
}

.availability {
  margin-top: 6px;
  font-size: 13px;
}

.availability--bad {
  color: #a83a0f;
  font-weight: 600;
}

.qty-row {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.qty-row--skeleton {
  margin-top: 2px;
}

.qty-row button {
  border: 1px solid #d7d4ce;
  border-radius: 8px;
  background: #fff;
  padding: 4px 8px;
  cursor: pointer;
}

.qty-row .remove {
  border-color: #efb39a;
  color: #a83a0f;
}

.checkout {
  position: relative;
  border-radius: 28px;
  padding: 20px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.checkout--busy > *:not(.checkout-overlay) {
  opacity: 0.64;
}

.checkout-skeleton {
  display: grid;
  gap: 12px;
}

.checkout-skeleton__field {
  display: grid;
  gap: 6px;
}

.checkout-skeleton__summary {
  margin-top: 4px;
}

.checkout h2 {
  font-size: 22px;
}

.checkout label {
  margin-top: 10px;
  display: grid;
  gap: 6px;
  font-size: 14px;
  color: #475569;
  font-weight: 600;
}

.checkout__input {
  min-height: 48px;
  border-radius: 14px;
  border-color: #d6dbe8;
  background: rgb(255 255 255 / 92%);
}

.checkout__select {
  min-height: 50px;
  border-radius: 14px;
  border-color: #d6dbe8;
  background: rgb(255 255 255 / 92%);
}

.checkout__textarea {
  border-radius: 14px;
  border-color: #d6dbe8;
  background: rgb(255 255 255 / 92%);
}

.summary {
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.summary--stack {
  display: grid;
  justify-content: stretch;
  gap: 6px;
}

.promo-status {
  margin-top: 8px;
  color: #8f5014;
  font-size: 13px;
}

.muted-inline {
  color: #66748f;
  font-size: 12px;
}

.promo-status--ok {
  color: #1f7a44;
}

.preview-loading {
  margin-top: 2px;
}

.checkout__submit {
  margin-top: 14px;
  width: 100%;
  min-height: 50px;
  border-radius: 14px;
  font-size: 16px;
}

.checkout__submit:disabled {
  opacity: 0.5;
  cursor: default;
}

.error {
  margin-top: 8px;
  color: #a83a0f;
  font-size: 14px;
}

.error--soft {
  margin-top: 10px;
}

.checkout-overlay {
  position: absolute;
  inset: 20px;
  pointer-events: none;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.checkout-overlay__bar {
  padding-top: 4px;
}

.checkout-overlay__summary {
  display: grid;
  gap: 10px;
  padding: 16px;
  border-radius: 18px;
  background: linear-gradient(180deg, rgb(255 255 255 / 66%), rgb(255 248 240 / 78%));
  backdrop-filter: blur(3px);
}

.items-skeleton {
  display: grid;
  gap: 12px;
}

.items-overlay {
  position: absolute;
  inset: 14px;
  display: grid;
  gap: 12px;
  pointer-events: none;
  padding: 6px;
  background: linear-gradient(180deg, rgb(255 255 255 / 28%), rgb(255 250 243 / 16%));
  backdrop-filter: blur(2px);
  border-radius: 22px;
}

.items--busy > .item {
  opacity: 0.58;
}

.empty {
  margin-top: 16px;
}

.empty a {
  color: #1f2233;
}

.history {
  margin-top: 26px;
}

.history h2 {
  margin-bottom: 10px;
  font-size: 22px;
}

.history-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 12px 40px rgb(16 24 40 / 9%);
  padding: 10px 12px;
  margin-bottom: 8px;
}

.history-item a {
  color: #1f2233;
  font-weight: 700;
  text-decoration: none;
}

.history-item p {
  color: #6b7386;
  font-size: 14px;
}

@media (max-width: 960px) {
  .cart-layout {
    grid-template-columns: 1fr;
  }

  .items {
    min-height: auto;
  }
}
</style>
