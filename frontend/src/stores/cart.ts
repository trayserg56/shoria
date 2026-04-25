import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { requestJson } from '@/lib/api'
import { captureFirstTouchAttribution } from '@/lib/attribution'
import { getAuthToken } from '@/lib/auth-token'
import { getAppSessionId } from '@/lib/session'

type CartItem = {
  id: number
  product_id: number
  product_variant_id: number | null
  product_slug: string
  product_name: string
  variant_label: string | null
  image_url: string | null
  qty: number
  unit_price: number
  total_price: number
  available: boolean
  available_stock: number
  availability_message: string | null
}

type CartPayload = {
  id: number
  session_id: string
  status: string
  currency: string
  subtotal: number
  total: number
  total_items: number
  items: CartItem[]
}

type CheckoutPayload = {
  customer_name: string
  customer_email: string
  customer_phone: string
  delivery_method: string
  payment_method: string
  promo_code?: string
  loyalty_points_to_spend?: number
  comment?: string
}

type CheckoutOptions = {
  delivery_methods: Array<{
    code: string
    name: string
    fee: number
    provider_code: string | null
    provider_mode: string | null
    is_test_mode: boolean
  }>
  payment_methods: Array<{
    code: string
    name: string
    driver: string
    mode: string
    is_test_mode: boolean
  }>
  promo_codes: Array<{
    code: string
    name: string
    discount_type: 'fixed_percent' | 'fixed_amount'
    discount_value: number
    min_subtotal: number | null
  }>
  loyalty: {
    is_enabled: boolean
    base_accrual_percent: number
    max_redeem_percent: number
    point_value: number
    min_order_total_for_redeem: number
    tiers: Array<{
      name: string
      min_spent: number
      accrual_percent: number
    }>
    terms_content: string | null
    account: {
      points_balance: number
      total_spent: number
      accrual_percent: number
      current_tier: {
        name: string
        min_spent: number
        accrual_percent: number
      } | null
      next_tier: {
        name: string
        min_spent: number
        accrual_percent: number
      } | null
      amount_to_next_tier: number
    } | null
  }
}

type CheckoutPreview = {
  subtotal: number
  discount_total: number
  loyalty_discount_total: number
  delivery_total: number
  total: number
  currency: string
  promo: {
    code: string | null
    is_applied: boolean
    message: string | null
  }
  loyalty: {
    is_enabled: boolean
    requested_points: number
    applied_points: number
    max_points_to_spend: number
    points_balance: number
    points_to_earn: number
    accrual_percent: number
    account: {
      points_balance: number
      total_spent: number
      accrual_percent: number
      current_tier: {
        name: string
        min_spent: number
        accrual_percent: number
      } | null
      next_tier: {
        name: string
        min_spent: number
        accrual_percent: number
      } | null
      amount_to_next_tier: number
    } | null
  }
}

type CheckoutResponse = {
  order_id: number
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
  subtotal: number
  discount_total: number
  loyalty_discount_total: number
  loyalty_points_spent: number
  loyalty_points_earned: number
  delivery_total: number
  total: number
  currency: string
  items_count: number
  loyalty_account: {
    points_balance: number
    total_spent: number
    accrual_percent: number
    current_tier: {
      name: string
      min_spent: number
      accrual_percent: number
    } | null
    next_tier: {
      name: string
      min_spent: number
      accrual_percent: number
    } | null
    amount_to_next_tier: number
  } | null
}

type OrderSummary = {
  order_number: string
  status: string
  order_status: string
  payment_status: string
  fulfillment_status: string
  refund_status: string
  payment_transaction_status: string | null
  delivery_method: string
  payment_method: string
  total: number
  currency: string
  placed_at: string
}

type OrdersPaginated = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  data: OrderSummary[]
}

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
  loyalty_discount_total: number
  loyalty_points_spent: number
  loyalty_points_earned: number
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

export const useCartStore = defineStore('cart', () => {
  const cart = ref<CartPayload | null>(null)
  const isLoading = ref(false)
  const lastOrder = ref<CheckoutResponse | null>(null)
  const orderHistory = ref<OrderSummary[]>([])
  const orderHistoryMeta = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
  })
  const checkoutOptions = ref<CheckoutOptions | null>(null)

  const sessionId = getAppSessionId()

  async function loadCart() {
    isLoading.value = true

    try {
      cart.value = await requestJson<CartPayload>(`/api/cart?session_id=${encodeURIComponent(sessionId)}`)
    } finally {
      isLoading.value = false
    }
  }

  async function addItemBySlug(productSlug: string, qty = 1, productVariantId?: number) {
    isLoading.value = true

    const payload: Record<string, string | number> = {
      session_id: sessionId,
      product_slug: productSlug,
      qty,
    }

    if (productVariantId) {
      payload.product_variant_id = productVariantId
    }

    try {
      cart.value = await requestJson<CartPayload>('/api/cart/items', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    } finally {
      isLoading.value = false
    }
  }

  async function updateQty(itemId: number, qty: number) {
    isLoading.value = true

    try {
      cart.value = await requestJson<CartPayload>(`/api/cart/items/${itemId}`, {
        method: 'PATCH',
        body: JSON.stringify({
          session_id: sessionId,
          qty,
        }),
      })
    } finally {
      isLoading.value = false
    }
  }

  async function removeItem(itemId: number) {
    isLoading.value = true

    try {
      cart.value = await requestJson<CartPayload>(`/api/cart/items/${itemId}?session_id=${encodeURIComponent(sessionId)}`, {
        method: 'DELETE',
      })
    } finally {
      isLoading.value = false
    }
  }

  async function checkout(payload: CheckoutPayload) {
    const order = await requestJson<CheckoutResponse>('/api/checkout', {
      method: 'POST',
      body: JSON.stringify({
        session_id: sessionId,
        attribution: captureFirstTouchAttribution(),
        ...payload,
      }),
    })

    lastOrder.value = order
    await loadCart()

    return order
  }

  async function loadCheckoutOptions() {
    checkoutOptions.value = await requestJson<CheckoutOptions>('/api/checkout/options')
  }

  async function previewCheckout(payload: {
    delivery_method: string
    promo_code?: string
    customer_email?: string
    loyalty_points_to_spend?: number
  }) {
    return requestJson<CheckoutPreview>('/api/checkout/preview', {
      method: 'POST',
      body: JSON.stringify({
        session_id: sessionId,
        attribution: captureFirstTouchAttribution(),
        ...payload,
      }),
    })
  }

  async function loadOrderHistory(params?: { page?: number; status?: string; perPage?: number }) {
    const query = new URLSearchParams()

    if (!getAuthToken()) {
      query.set('session_id', sessionId)
    }

    if (params?.page && params.page > 1) {
      query.set('page', String(params.page))
    }

    if (params?.status) {
      query.set('status', params.status)
    }

    if (params?.perPage) {
      query.set('per_page', String(params.perPage))
    }

    const suffix = query.toString()
    const response = await requestJson<OrdersPaginated>(`/api/orders${suffix ? `?${suffix}` : ''}`)

    orderHistory.value = response.data
    orderHistoryMeta.value = {
      currentPage: response.current_page,
      lastPage: response.last_page,
      total: response.total,
      perPage: response.per_page,
    }
  }

  async function loadOrderDetails(orderNumber: string) {
    const query = new URLSearchParams()

    if (!getAuthToken()) {
      query.set('session_id', sessionId)
    }

    const suffix = query.toString()

    return requestJson<OrderDetails>(`/api/orders/${encodeURIComponent(orderNumber)}${suffix ? `?${suffix}` : ''}`)
  }

  const items = computed(() => cart.value?.items ?? [])
  const totalItems = computed(() => cart.value?.total_items ?? 0)
  const subtotal = computed(() => cart.value?.subtotal ?? 0)
  const total = computed(() => cart.value?.total ?? 0)

  return {
    sessionId,
    cart,
    items,
    totalItems,
    subtotal,
    total,
    isLoading,
    lastOrder,
    orderHistory,
    orderHistoryMeta,
    checkoutOptions,
    loadCart,
    loadCheckoutOptions,
    previewCheckout,
    loadOrderHistory,
    loadOrderDetails,
    addItemBySlug,
    updateQty,
    removeItem,
    checkout,
  }
})
