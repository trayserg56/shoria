<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue'
import type { Ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { trackEvent } from '@/lib/analytics'
import { requestJson } from '@/lib/api'
import { getAppSessionId } from '@/lib/session'
import AppSkeleton from '@/components/AppSkeleton.vue'
import AddressAutocomplete from '@/components/AddressAutocomplete.vue'
import CdekPickupMap from '@/components/CdekPickupMap.vue'
import type { AddressSuggestion } from '@/composables/useAddressSuggest'
import { toast } from '@/lib/toast'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useCheckoutPreview } from '@/composables/useCheckoutPreview'
import {
  clearCheckoutIncentives,
  loadCheckoutIncentives,
} from '@/lib/checkout-context'
import { siteSettingsInjectionKey, defaultSiteFeatureFlags, type SiteSettingsPayload } from '@/lib/site-settings'
import { getStoredCityName } from '@/lib/site-city'

const authStore = useAuthStore()
const cartStore = useCartStore()
const router = useRouter()
const route = useRoute()
const { items, total, totalItems, checkoutOptions, isLoading: isCartLoading, subtotal: cartSubtotal } =
  storeToRefs(cartStore)
const { user } = storeToRefs(authStore)

const siteSettingsRef = inject(siteSettingsInjectionKey) as Ref<SiteSettingsPayload> | undefined
const giftCertificatesEnabled = computed(
  () => siteSettingsRef?.value.feature_flags.gift_certificates ?? defaultSiteFeatureFlags.gift_certificates,
)

const customerName = ref('')
const customerEmail = ref('')
const customerPhone = ref('')
const comment = ref('')
const deliveryMethod = ref('')
const paymentMethod = ref('')
const promoCode = ref('')
const giftCertificateCode = ref('')
const giftCertificateId = ref<number | null>(null)
const giftCodeExpanded = ref(false)
const myGiftCertificates = ref<
  Array<{
    id: number
    code: string
    balance_remaining: number
    initial_amount: number
    currency: string
    expires_at: string | null
  }>
>([])
const loyaltyPointsToSpend = ref(0)
const checkoutError = ref('')
const checkoutLoading = ref(false)

const deliveryCity = ref('')
const pickupAddress = ref('')
const pickupFocusPoint = ref<{ lat: number; lon: number } | null>(null)
const deliveryAddress = ref('')
const deliveryPickupPointCode = ref('')
const myAddresses = ref<
  Array<{
    id: number
    label: string
    city: string
    city_code: number | null
    address: string
    is_default: boolean
  }>
>([])
const pickupPoints = ref<
  Array<{
    code: string
    name: string
    address: string | null
    work_time: string | null
    lat: number | null
    lon: number | null
  }>
>([])
const pickupPointsLoading = ref(false)
const pickupPointsError = ref('')

const {
  previewLoading,
  loyaltyEnabled,
  displayedSubtotal,
  displayedDiscount,
  displayedGiftCertificateDiscount,
  displayedLoyaltyDiscount,
  displayedDelivery,
  deliveryPeriodMin,
  deliveryPeriodMax,
  displayedTotal,
  giftCertificateStatusMessage,
  giftCertificateStatusApplied,
  refreshPreview,
  schedulePreviewRefresh,
} = useCheckoutPreview({
  deliveryMethod,
  promoCode,
  giftCertificateCode,
  giftCertificateId,
  loyaltyPointsToSpend,
  customerEmail,
  deliveryCity,
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

const activeStep = ref(1)

const step1Valid = computed(
  () =>
    customerName.value.trim() !== ''
    && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerEmail.value.trim())
    && customerPhone.value.trim().length >= 5,
)

const step2Valid = computed(() => {
  if (!deliveryMethod.value) {
    return false
  }

  if (requiresPickupPoint.value) {
    return deliveryPickupPointCode.value !== ''
  }

  if (requiresAddress.value) {
    return deliveryAddress.value.trim() !== ''
  }

  return true
})

function continueToStep(step: number) {
  activeStep.value = step
}

const deliveryMethods = computed(() => checkoutOptions.value?.delivery_methods ?? [])
const paymentMethods = computed(() => checkoutOptions.value?.payment_methods ?? [])
const deliveryMethodOptions = computed(() =>
  deliveryMethods.value.map((method) => {
    const priceLabel = method.provider_code === 'cdek'
      ? `от ${formatPrice(method.fee)}`
      : formatPrice(method.fee)

    return {
      label: `${method.name}${method.is_test_mode ? ' · тест' : ''} (${priceLabel})`,
      value: String(method.code),
    }
  }),
)
const paymentMethodOptions = computed(() =>
  paymentMethods.value.map((method) => ({
    label: `${method.name}${method.is_test_mode ? ' · тест' : ''}`,
    value: String(method.code),
  })),
)

const selectedDeliveryMethodLabel = computed(
  () => deliveryMethods.value.find((method) => method.code === deliveryMethod.value)?.name ?? '',
)
const selectedPaymentMethodLabel = computed(
  () => paymentMethods.value.find((method) => method.code === paymentMethod.value)?.name ?? '',
)

const selectedDeliveryMethod = computed(() =>
  deliveryMethods.value.find((method) => method.code === deliveryMethod.value),
)
const requiresPickupPoint = computed(() => selectedDeliveryMethod.value?.requires_pickup_point ?? false)
const requiresAddress = computed(() => selectedDeliveryMethod.value?.requires_address ?? false)
const requiresCity = computed(() => requiresPickupPoint.value || requiresAddress.value)

const deliveryPeriodLabel = computed(() => {
  if (deliveryPeriodMin.value == null && deliveryPeriodMax.value == null) {
    return ''
  }

  if (deliveryPeriodMin.value === deliveryPeriodMax.value) {
    return `${deliveryPeriodMin.value} дн.`
  }

  return `${deliveryPeriodMin.value}–${deliveryPeriodMax.value} дн.`
})

const pickupPointOptions = computed(() =>
  pickupPoints.value.map((point) => ({
    label: [point.name, point.address].filter(Boolean).join(' — '),
    value: point.code,
  })),
)
const selectedPickupPointLabel = computed(
  () => pickupPointOptions.value.find((opt) => opt.value === deliveryPickupPointCode.value)?.label ?? '',
)

async function loadPickupPoints() {
  pickupPointsError.value = ''
  pickupPoints.value = []
  deliveryPickupPointCode.value = ''

  const city = deliveryCity.value.trim()
  if (!city) {
    return
  }

  pickupPointsLoading.value = true

  try {
    const response = await requestJson<{
      data: Array<{ code: string; name: string; address: string | null; work_time: string | null; lat: number | null; lon: number | null }>
      message?: string
    }>(`/api/delivery/cdek/pickup-points?city=${encodeURIComponent(city)}`)

    pickupPoints.value = response.data
    if (response.data.length === 0) {
      pickupPointsError.value = response.message || 'ПВЗ не найдены для этого города.'
    }
  } catch (error) {
    console.error(error)
    pickupPointsError.value = 'Не удалось загрузить список ПВЗ.'
  } finally {
    pickupPointsLoading.value = false
  }
}

async function loadMyAddresses() {
  if (!user.value) {
    myAddresses.value = []
    return
  }

  try {
    const response = await requestJson<{
      data: Array<{
        id: number
        label: string
        city: string
        city_code: number | null
        address: string
        is_default: boolean
      }>
    }>('/api/me/addresses')
    myAddresses.value = response.data
  } catch {
    myAddresses.value = []
  }
}

async function applySavedAddress(id: number) {
  const address = myAddresses.value.find((item) => item.id === id)
  if (!address) {
    return
  }

  deliveryCity.value = address.city
  deliveryAddress.value = address.address
  pickupAddress.value = address.address

  try {
    const response = await requestJson<{ data: AddressSuggestion[] }>(
      `/api/address/suggest?q=${encodeURIComponent(address.address)}`,
    )
    const [suggestion] = response.data

    if (suggestion) {
      onPickupAddressSelect(suggestion)
    }
  } catch {
    pickupFocusPoint.value = null
  }
}

function onDeliveryAddressSelect(suggestion: AddressSuggestion) {
  if (suggestion.city) {
    deliveryCity.value = suggestion.city
  }
}

function onPickupAddressSelect(suggestion: AddressSuggestion) {
  if (suggestion.city) {
    deliveryCity.value = suggestion.city
  }

  pickupFocusPoint.value = (suggestion.lat != null && suggestion.lon != null)
    ? { lat: suggestion.lat, lon: suggestion.lon }
    : null
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
      gift_certificate_code:
        giftCertificateId.value != null ? undefined : giftCertificateCode.value.trim() || undefined,
      gift_certificate_id:
        giftCertificateId.value != null ? giftCertificateId.value : undefined,
      loyalty_points_to_spend: loyaltyEnabled.value ? loyaltyPointsToSpend.value : undefined,
      comment: comment.value,
      delivery_city: deliveryCity.value.trim() || undefined,
      delivery_address: requiresAddress.value ? (deliveryAddress.value.trim() || undefined) : undefined,
      delivery_pickup_point_code: requiresPickupPoint.value
        ? (deliveryPickupPointCode.value || undefined)
        : undefined,
      delivery_pickup_point_address: requiresPickupPoint.value
        ? pickupPoints.value.find((point) => point.code === deliveryPickupPointCode.value)?.address ?? undefined
        : undefined,
    })

    void trackEvent('purchase', {
      order_number: order.order_number,
      total: order.total,
      items_count: order.items_count,
    })

    clearCheckoutIncentives()

    // Если payment_status === 'pending' — нужна онлайн-оплата (Робокасса и др.)
    if (order.payment_status === 'pending') {
      try {
        const sessionId = getAppSessionId()
        const { payment_url } = await requestJson<{ payment_url: string | null }>(
          `/api/orders/${order.order_number}/payment-url?session_id=${encodeURIComponent(sessionId)}`
        )
        if (payment_url) {
          window.location.href = payment_url
          return
        }
      } catch {
        // Не удалось получить ссылку — падаем на страницу заказа
      }
    }

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
    giftCertificateCode.value = fromCart.giftCertificateCode.trim()
    giftCertificateId.value =
      typeof fromCart.giftCertificateId === 'number' && Number.isFinite(fromCart.giftCertificateId)
        ? fromCart.giftCertificateId
        : null
    loyaltyPointsToSpend.value = Math.max(0, Math.floor(fromCart.loyaltyPointsToSpend))
    giftCodeExpanded.value = giftCertificateCode.value.trim() !== ''
  }
  applyPromoFromQuery()
}

async function loadMyGiftCertificates() {
  if (!user.value) {
    myGiftCertificates.value = []
    return
  }

  try {
    const response = await requestJson<{
      data: Array<{
        id: number
        code: string
        balance_remaining: number
        initial_amount: number
        currency: string
        expires_at: string | null
      }>
    }>('/api/me/gift-certificates')
    myGiftCertificates.value = response.data
  } catch {
    myGiftCertificates.value = []
  }
}

function toggleGiftFromAccount(id: number) {
  if (giftCertificateId.value === id) {
    giftCertificateId.value = null
  } else {
    giftCertificateId.value = id
    giftCertificateCode.value = ''
    giftCodeExpanded.value = false
  }
  void refreshPreview()
}

function revealGiftCodeField() {
  giftCodeExpanded.value = true
  giftCertificateId.value = null
  void refreshPreview()
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

  deliveryCity.value = getStoredCityName()

  await loadMyGiftCertificates()
  await loadMyAddresses()

  if (requiresPickupPoint.value) {
    await loadPickupPoints()
  }

  await refreshPreview()
})

watch(
  () => giftCertificateCode.value,
  (v) => {
    if (v.trim() !== '') {
      giftCertificateId.value = null
    }
  },
)

watch(
  () => user.value?.id,
  () => {
    void loadMyGiftCertificates()
    void loadMyAddresses()
  },
)

watch(
  () => [deliveryMethod.value, deliveryCity.value] as const,
  () => {
    if (requiresPickupPoint.value) {
      void loadPickupPoints()
    } else {
      pickupPoints.value = []
      pickupPointsError.value = ''
      deliveryPickupPointCode.value = ''
    }
  },
)

watch(
  () =>
    [
      deliveryMethod.value,
      promoCode.value,
      giftCertificateCode.value,
      giftCertificateId.value,
      customerEmail.value,
      loyaltyPointsToSpend.value,
      cartSubtotal.value,
      deliveryCity.value,
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
      <p>Контакты, доставка и оплата. Промокод, сертификат и баллы можно уточнить справа.</p>
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
        <!-- Шаг 1: Покупатель -->
        <section class="checkout-block">
          <div class="checkout-block__header">
            <h2 class="checkout-block__title">Покупатель</h2>
            <button
              v-if="activeStep > 1"
              type="button"
              class="checkout-block__edit"
              @click="continueToStep(1)"
            >
              Изменить
            </button>
          </div>

          <div v-if="activeStep === 1" class="checkout-block__body">
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
            <button
              type="button"
              class="checkout-block__continue"
              :disabled="!step1Valid"
              @click="continueToStep(2)"
            >
              Продолжить
            </button>
          </div>
          <div v-else class="checkout-block__summary">
            <p class="checkout-block__summary-line">{{ customerName }}</p>
            <p class="checkout-block__summary-line checkout-block__summary-line--muted">
              {{ customerEmail }} · {{ customerPhone }}
            </p>
          </div>
        </section>

        <!-- Шаг 2: Способ доставки -->
        <section class="checkout-block" :class="{ 'checkout-block--locked': activeStep < 2 }">
          <div class="checkout-block__header">
            <h2 class="checkout-block__title">Способ доставки</h2>
            <button
              v-if="activeStep > 2"
              type="button"
              class="checkout-block__edit"
              @click="continueToStep(2)"
            >
              Изменить
            </button>
          </div>

          <p v-if="activeStep < 2" class="checkout-block__locked-hint">
            Сначала заполните данные покупателя
          </p>

          <div v-if="activeStep === 2" class="checkout-block__body">
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
            <div v-if="requiresPickupPoint" class="checkout-form__field">
              <label class="checkout-form__label" for="checkout-delivery-city">Город доставки</label>
              <AddressAutocomplete
                id="checkout-delivery-city"
                v-model="pickupAddress"
                input-class="checkout__input"
                name="delivery_city"
                placeholder="Например, Оренбург, ул Транспортная"
                @select="onPickupAddressSelect"
              />
              <p v-if="deliveryPeriodLabel" class="checkout-form__hint">Срок доставки: {{ deliveryPeriodLabel }}</p>
            </div>
            <div v-else-if="requiresCity" class="checkout-form__field">
              <label class="checkout-form__label" for="checkout-delivery-city">Город доставки</label>
              <input
                id="checkout-delivery-city"
                v-model="deliveryCity"
                class="checkout__input"
                type="text"
                name="delivery_city"
                placeholder="Например, Москва"
              />
              <p v-if="deliveryPeriodLabel" class="checkout-form__hint">Срок доставки: {{ deliveryPeriodLabel }}</p>
            </div>

            <div v-if="user && myAddresses.length > 0 && requiresCity" class="checkout-form__field">
              <label class="checkout-form__label">Сохранённые адреса</label>
              <div class="checkout-address-chips">
                <button
                  v-for="address in myAddresses"
                  :key="address.id"
                  type="button"
                  class="checkout-address-chip"
                  @click="applySavedAddress(address.id)"
                >
                  {{ address.label }}: {{ address.city }}, {{ address.address }}
                </button>
              </div>
            </div>

            <div v-if="requiresPickupPoint" class="checkout-form__field">
              <label class="checkout-form__label" for="checkout-pickup-point">Пункт выдачи СДЭК</label>
              <select
                id="checkout-pickup-point"
                v-model="deliveryPickupPointCode"
                class="checkout__select"
                :disabled="pickupPointsLoading || pickupPointOptions.length === 0"
              >
                <option value="" disabled>Выберите ПВЗ</option>
                <option v-for="opt in pickupPointOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <p v-if="pickupPointsLoading" class="checkout-form__hint">Загрузка пунктов выдачи…</p>
              <p v-else-if="pickupPointsError" class="checkout-form__hint">{{ pickupPointsError }}</p>
              <CdekPickupMap
                v-if="pickupPoints.length > 0 || pickupFocusPoint"
                v-model:selected-code="deliveryPickupPointCode"
                :points="pickupPoints"
                :focus-point="pickupFocusPoint"
              />
            </div>

            <div v-if="requiresAddress" class="checkout-form__field">
              <label class="checkout-form__label" for="checkout-delivery-address">Адрес доставки</label>
              <AddressAutocomplete
                id="checkout-delivery-address"
                v-model="deliveryAddress"
                input-class="checkout__input"
                name="delivery_address"
                placeholder="Например: Транспортная 1/1, город Оренбург"
                @select="onDeliveryAddressSelect"
              />
            </div>

            <button
              type="button"
              class="checkout-block__continue"
              :disabled="!step2Valid"
              @click="continueToStep(3)"
            >
              Продолжить
            </button>
          </div>
          <div v-else-if="activeStep > 2" class="checkout-block__summary">
            <div class="checkout-block__summary-row">
              <span>Способ доставки</span>
              <strong>{{ selectedDeliveryMethodLabel }}</strong>
            </div>
            <div v-if="deliveryCity" class="checkout-block__summary-row">
              <span>Город</span>
              <strong>{{ deliveryCity }}</strong>
            </div>
            <div v-if="requiresPickupPoint && selectedPickupPointLabel" class="checkout-block__summary-row">
              <span>Пункт выдачи</span>
              <strong>{{ selectedPickupPointLabel }}</strong>
            </div>
            <div v-else-if="requiresAddress && deliveryAddress" class="checkout-block__summary-row">
              <span>Адрес</span>
              <strong>{{ deliveryAddress }}</strong>
            </div>
            <div class="checkout-block__summary-row">
              <span>Стоимость доставки</span>
              <strong>{{ formatPrice(displayedDelivery) }}</strong>
            </div>
          </div>
        </section>

        <!-- Шаг 3: Оплата и комментарий -->
        <section class="checkout-block" :class="{ 'checkout-block--locked': activeStep < 3 }">
          <div class="checkout-block__header">
            <h2 class="checkout-block__title">Оплата и комментарий</h2>
          </div>

          <p v-if="activeStep < 3" class="checkout-block__locked-hint">
            Сначала укажите способ доставки
          </p>

          <div v-else class="checkout-block__body">
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
          </div>
        </section>

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
              <div v-if="displayedGiftCertificateDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Сертификат</dt>
                <dd>−{{ formatPrice(displayedGiftCertificateDiscount) }}</dd>
              </div>
              <div v-if="loyaltyEnabled && displayedLoyaltyDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Баллы</dt>
                <dd>−{{ formatPrice(displayedLoyaltyDiscount) }}</dd>
              </div>
              <div class="order-summary__line order-summary__line--muted">
                <dt>Доставка{{ deliveryPeriodLabel ? ` (${deliveryPeriodLabel})` : '' }}</dt>
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

            <div v-if="giftCertificatesEnabled" class="order-summary__gift">
              <p v-if="user && myGiftCertificates.length" class="order-summary__gift-title">Ваши сертификаты</p>
              <div v-if="user && myGiftCertificates.length" class="order-summary__gift-cards">
                <button
                  v-for="gc in myGiftCertificates"
                  :key="gc.id"
                  type="button"
                  class="gift-card-tile"
                  :class="{ 'gift-card-tile--active': giftCertificateId === gc.id }"
                  @click="toggleGiftFromAccount(gc.id)"
                >
                  <span class="gift-card-tile__label">Сертификат</span>
                  <span class="gift-card-tile__amount">{{ formatPrice(gc.balance_remaining) }}</span>
                  <span class="gift-card-tile__hint">остаток</span>
                </button>
              </div>
              <button
                v-if="!giftCodeExpanded"
                type="button"
                class="order-summary__gift-code-toggle"
                @click="revealGiftCodeField"
              >
                Ввести сертификат кодом
              </button>
              <input
                v-else
                v-model="giftCertificateCode"
                class="order-summary__input"
                type="text"
                name="checkout_gift_certificate_code"
                autocomplete="off"
                placeholder="Код подарочного сертификата"
              />
            </div>
            <div class="order-summary__promo">
              <input
                v-model="promoCode"
                class="order-summary__input"
                type="text"
                name="checkout_promo_code"
                autocomplete="off"
                placeholder="Промокод"
              />
            </div>
            <p v-if="giftCertificateStatusMessage" class="order-summary__promo-msg" :class="{ 'order-summary__promo-msg--ok': giftCertificateStatusApplied }">
              {{ giftCertificateStatusMessage }}
            </p>

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
}

.checkout-form--main {
  min-width: 0;
  display: grid;
  gap: 16px;
}

.checkout-form--skeleton {
  display: grid;
  gap: 14px;
  border-radius: 16px;
  padding: 22px 22px 24px;
  border: 1px solid color-mix(in srgb, var(--border) 90%, transparent);
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
}

.checkout-block {
  border-radius: 16px;
  padding: 22px 22px 24px;
  border: 1px solid color-mix(in srgb, var(--border) 90%, transparent);
  background: var(--card);
  box-shadow: 0 1px 2px rgb(15 23 42 / 4%);
}

.checkout-block__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.checkout-block__title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.checkout-block__edit {
  flex: 0 0 auto;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--background);
  color: inherit;
  cursor: pointer;
}

.checkout-block__edit:hover {
  border-color: #000;
}

.checkout-block__body {
  margin-top: 6px;
}

.checkout-block__continue {
  margin-top: 18px;
  min-height: 50px;
  padding: 0 28px;
  border: none;
  border-radius: 12px;
  background: var(--primary);
  color: var(--primary-foreground);
  font-weight: 700;
  font-size: 15px;
  cursor: pointer;
}

.checkout-block__continue:disabled {
  opacity: 0.4;
  cursor: default;
}

.checkout-block--locked {
  opacity: 0.5;
}

.checkout-block--locked .checkout-block__title {
  color: #94a3b8;
}

.checkout-block__locked-hint {
  margin: 6px 0 0;
  font-size: 14px;
  color: #94a3b8;
}

.checkout-block__summary {
  margin-top: 4px;
  display: grid;
  gap: 6px;
}

.checkout-block__summary-line {
  margin: 0;
  font-weight: 600;
}

.checkout-block__summary-line--muted {
  font-weight: 400;
  color: #64748b;
}

.checkout-block__summary-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.checkout-block__summary-row span {
  color: #64748b;
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

.checkout-form__hint {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.checkout-address-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.checkout-address-chip {
  font-size: 13px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--background);
  cursor: pointer;
  color: var(--foreground);
}

.checkout-address-chip:hover {
  border-color: #000;
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

.order-summary__gift {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
}

.order-summary__gift-title {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted-foreground);
}

.order-summary__gift-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
  gap: 8px;
}

.gift-card-tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 10px 12px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--background);
  cursor: pointer;
  text-align: left;
  font: inherit;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.gift-card-tile:hover {
  border-color: #000;
}

.gift-card-tile--active {
  border-color: #000;
  box-shadow: 0 0 0 1px #000 inset;
}

.gift-card-tile__label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted-foreground);
}

.gift-card-tile__amount {
  font-size: 15px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.gift-card-tile__hint {
  font-size: 11px;
  color: var(--muted-foreground);
}

.order-summary__gift-code-toggle {
  align-self: flex-start;
  padding: 0;
  border: none;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary);
  text-decoration: underline;
  cursor: pointer;
}

.order-summary__gift-code-toggle:hover {
  opacity: 0.85;
}

.order-summary__promo {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}

.order-summary__input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 14px;
  box-sizing: border-box;
}

.order-summary__promo-msg {
  margin: 0;
  font-size: 13px;
  color: #b42318;
}

.order-summary__promo-msg--ok {
  color: var(--muted-foreground);
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
