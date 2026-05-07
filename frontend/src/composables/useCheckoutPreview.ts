import { computed, onBeforeUnmount, ref, type Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

type UseCheckoutPreviewParams = {
  deliveryMethod: Ref<string>
  promoCode: Ref<string>
  giftCertificateCode: Ref<string>
  /** Выбор сертификата из ЛК (взаимоисключающе с кодом). */
  giftCertificateId?: Ref<number | null>
  loyaltyPointsToSpend: Ref<number>
  /** Если передан — уходит в `POST /api/checkout/preview` (влияет на персональные промо). */
  customerEmail?: Ref<string>
}

/**
 * Расчёт черновика заказа через `previewCheckout`: суммы, доставка, промо, лояльность.
 * Общая логика для корзины (боковая панель) и страницы оформления.
 */
export function useCheckoutPreview(params: UseCheckoutPreviewParams) {
  const cartStore = useCartStore()
  const authStore = useAuthStore()
  const { items, checkoutOptions } = storeToRefs(cartStore)
  const { user } = storeToRefs(authStore)

  const previewLoading = ref(false)
  const hasPreviewSnapshot = ref(false)

  const subtotalAmount = ref(0)
  const discountAmount = ref(0)
  const giftCertificateDiscountAmount = ref(0)
  const loyaltyDiscountAmount = ref(0)
  const deliveryAmount = ref(0)
  const checkoutTotalPreview = ref(0)

  const loyaltyMaxPoints = ref(0)
  const loyaltyPointsBalance = ref(0)
  const loyaltyPointsToEarn = ref(0)
  const loyaltyAccrualPercent = ref(0)

  const promoStatusMessage = ref('')
  const promoStatusApplied = ref(false)

  const giftCertificateStatusMessage = ref('')
  const giftCertificateStatusApplied = ref(false)

  let previewRefreshTimer: ReturnType<typeof setTimeout> | null = null

  const deliveryMethods = computed(() => checkoutOptions.value?.delivery_methods ?? [])
  const cartSubtotalFallback = computed(() => items.value.reduce((sum, item) => sum + item.total_price, 0))
  const selectedDeliveryFee = computed(
    () => deliveryMethods.value.find((method) => method.code === params.deliveryMethod.value)?.fee ?? 0,
  )
  const loyaltyConfig = computed(() => checkoutOptions.value?.loyalty ?? null)
  const loyaltyEnabled = computed(() => !!(loyaltyConfig.value?.is_enabled && user.value))

  const displayedSubtotal = computed(() =>
    hasPreviewSnapshot.value ? subtotalAmount.value : cartSubtotalFallback.value,
  )
  const displayedDiscount = computed(() => (hasPreviewSnapshot.value ? discountAmount.value : 0))
  const displayedGiftCertificateDiscount = computed(() =>
    hasPreviewSnapshot.value ? giftCertificateDiscountAmount.value : 0,
  )
  const displayedLoyaltyDiscount = computed(() =>
    hasPreviewSnapshot.value ? loyaltyDiscountAmount.value : 0,
  )
  const displayedDelivery = computed(() =>
    hasPreviewSnapshot.value ? deliveryAmount.value : selectedDeliveryFee.value,
  )
  const displayedTotal = computed(
    () =>
      displayedSubtotal.value
      - displayedDiscount.value
      - displayedGiftCertificateDiscount.value
      - displayedLoyaltyDiscount.value
      + displayedDelivery.value,
  )

  function clearPreviewTimer() {
    if (previewRefreshTimer !== null) {
      clearTimeout(previewRefreshTimer)
      previewRefreshTimer = null
    }
  }

  async function refreshPreview() {
    if (!params.deliveryMethod.value) {
      hasPreviewSnapshot.value = false
      return
    }

    previewLoading.value = true

    try {
      const payload: {
        delivery_method: string
        promo_code?: string
        gift_certificate_code?: string
        gift_certificate_id?: number
        customer_email?: string
        loyalty_points_to_spend: number
      } = {
        delivery_method: params.deliveryMethod.value,
        promo_code: params.promoCode.value.trim() || undefined,
        loyalty_points_to_spend: loyaltyEnabled.value ? params.loyaltyPointsToSpend.value : 0,
      }

      const id = params.giftCertificateId?.value
      if (id != null && Number.isFinite(id)) {
        payload.gift_certificate_id = id
      } else {
        payload.gift_certificate_code = params.giftCertificateCode.value.trim() || undefined
      }

      if (params.customerEmail) {
        const email = params.customerEmail.value.trim()
        if (email) {
          payload.customer_email = email
        }
      }

      const preview = await cartStore.previewCheckout(payload)

      subtotalAmount.value = preview.subtotal
      discountAmount.value = preview.discount_total
      giftCertificateDiscountAmount.value = preview.gift_certificate_discount_total
      loyaltyDiscountAmount.value = preview.loyalty_discount_total
      deliveryAmount.value = preview.delivery_total
      checkoutTotalPreview.value = preview.total
      hasPreviewSnapshot.value = true
      promoStatusMessage.value = preview.promo.message ?? ''
      promoStatusApplied.value = preview.promo.is_applied
      giftCertificateStatusMessage.value = preview.gift_certificate.message ?? ''
      giftCertificateStatusApplied.value = preview.gift_certificate.is_applied
      loyaltyMaxPoints.value = preview.loyalty.max_points_to_spend
      loyaltyPointsBalance.value = preview.loyalty.points_balance
      loyaltyPointsToEarn.value = preview.loyalty.points_to_earn
      loyaltyAccrualPercent.value = preview.loyalty.accrual_percent
      if (loyaltyEnabled.value && params.loyaltyPointsToSpend.value > loyaltyMaxPoints.value) {
        params.loyaltyPointsToSpend.value = loyaltyMaxPoints.value
      }
    } catch (error) {
      console.error(error)
      hasPreviewSnapshot.value = false
      discountAmount.value = 0
      giftCertificateDiscountAmount.value = 0
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

  function schedulePreviewRefresh(delayMs = 300) {
    clearPreviewTimer()
    previewRefreshTimer = setTimeout(() => {
      previewRefreshTimer = null
      void refreshPreview()
    }, delayMs)
  }

  onBeforeUnmount(() => {
    clearPreviewTimer()
  })

  return {
    previewLoading,
    hasPreviewSnapshot,
    promoStatusMessage,
    promoStatusApplied,
    giftCertificateStatusMessage,
    giftCertificateStatusApplied,
    loyaltyEnabled,
    displayedSubtotal,
    displayedDiscount,
    displayedGiftCertificateDiscount,
    displayedLoyaltyDiscount,
    displayedDelivery,
    displayedTotal,
    checkoutTotalPreview,
    loyaltyMaxPoints,
    loyaltyPointsBalance,
    loyaltyPointsToEarn,
    loyaltyAccrualPercent,
    refreshPreview,
    schedulePreviewRefresh,
  }
}
