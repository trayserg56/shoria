<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Copy } from 'lucide-vue-next'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { toast } from '@/lib/toast'
import { requestJson } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const authStore = useAuthStore()
const cartStore = useCartStore()
const router = useRouter()

const isLoading = ref(false)
const isBuying = ref(false)
const isPayingOrder = ref<string | null>(null)  // order_number заказа, по которому идёт редирект

const myCertificates = ref<
  Array<{
    id: number
    code: string
    balance_remaining: number
    initial_amount: number
    currency: string
    status: string
    expires_at: string | null
    created_at: string | null
  }>
>([])

const pendingCertOrders = ref<
  Array<{
    order_number: string
    total: number
    currency: string
    payment_status: string
    placed_at: string | null
  }>
>([])

/** Показ кода на экране (копирование доступно и при скрытом коде). */
const codeVisible = ref<Record<number, boolean>>({})

const selectedPreset = ref<number | 'custom' | null>(500)
const customAmount = ref<string>('')

const paymentMethod = ref('')
const paymentMethodOptions = computed(() =>
  (cartStore.checkoutOptions?.payment_methods ?? []).map((m) => ({
    label: `${m.name}${m.is_test_mode ? ' · тест' : ''}`,
    value: String(m.code),
  })),
)

const presets = [500, 1000, 5000] as const

function formatPrice(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

function resolvePurchaseAmount(): number | null {
  if (selectedPreset.value === 'custom') {
    const raw = Number(String(customAmount.value).replace(',', '.').trim())
    if (!Number.isFinite(raw)) {
      return null
    }

    return Math.round(raw * 100) / 100
  }

  if (typeof selectedPreset.value === 'number') {
    return selectedPreset.value
  }

  return null
}

function certStatusLabel(status: string) {
  const map: Record<string, string> = {
    active: 'Активен',
    depleted: 'Использован',
    cancelled: 'Аннулирован',
  }
  return map[status] ?? status
}

function toggleCodeVisible(id: number) {
  codeVisible.value = {
    ...codeVisible.value,
    [id]: !codeVisible.value[id],
  }
}

async function copyCertificateCode(code: string) {
  try {
    await navigator.clipboard.writeText(code)
    toast.success('Код скопирован в буфер')
  } catch {
    toast.error('Не удалось скопировать')
  }
}

async function loadCertificates() {
  isLoading.value = true
  try {
    await authStore.loadMe()
    await cartStore.loadCheckoutOptions()
    paymentMethod.value =
      paymentMethodOptions.value[0]?.value != null ? String(paymentMethodOptions.value[0].value) : ''

    const response = await requestJson<{
      data: typeof myCertificates.value
      pending_orders: typeof pendingCertOrders.value
    }>('/api/me/gift-certificates?include_used=1')
    myCertificates.value = response.data
    pendingCertOrders.value = response.pending_orders ?? []
  } catch (error) {
    console.error(error)
    toast.error('Не удалось загрузить сертификаты')
  } finally {
    isLoading.value = false
  }
}

async function buyCertificate() {
  const amount = resolvePurchaseAmount()

  if (amount === null) {
    toast.error('Укажите сумму')
    return
  }

  const presetsSet = new Set<number>(presets)
  const ok =
    presetsSet.has(amount) || (amount >= 100 && amount <= 500_000)

  if (!ok) {
    toast.error('Доступны 500, 1000, 5000 ₽ или своя сумма от 100 до 500 000 ₽')
    return
  }

  if (!paymentMethod.value) {
    toast.error('Выберите способ оплаты')
    return
  }

  isBuying.value = true
  try {
    const order = await cartStore.purchaseGiftCertificate({
      amount,
      payment_method: paymentMethod.value,
    })

    toast.success(`Заказ ${order.order_number} оформлен`)
    await router.push({
      name: 'order-success',
      params: { orderNumber: order.order_number },
    })
  } catch (error) {
    const msg = error instanceof Error ? error.message : 'Не удалось оформить покупку сертификата'
    toast.error(msg)
  } finally {
    isBuying.value = false
  }
}

async function payOrder(orderNumber: string) {
  isPayingOrder.value = orderNumber
  try {
    const result = await requestJson<{ payment_url: string | null; order_number: string }>(
      `/api/orders/${orderNumber}/payment-url`,
    )
    if (result.payment_url) {
      window.location.href = result.payment_url
    } else {
      toast.error('Ссылка на оплату недоступна. Свяжитесь с поддержкой.')
    }
  } catch {
    toast.error('Не удалось получить ссылку на оплату')
  } finally {
    isPayingOrder.value = null
  }
}

onMounted(() => {
  void loadCertificates()
})
</script>

<template>
  <section class="account-panel">
    <div class="account-panel__header">
      <div>
        <h2>Подарочные сертификаты</h2>
        <p>Купить номинал или использовать уже привязанные к аккаунту в корзине.</p>
      </div>
    </div>

    <!-- Ожидающие оплаты заказы на сертификаты -->
    <article v-if="pendingCertOrders.length > 0" class="card card--pending">
      <h3 class="card__title">Ожидают оплаты</h3>
      <p class="card__hint">После оплаты сертификат появится в разделе «Ваши сертификаты».</p>
      <ul class="pending-list">
        <li v-for="order in pendingCertOrders" :key="order.order_number" class="pending-list__item">
          <div class="pending-list__info">
            <span class="pending-list__amount">{{ formatPrice(order.total) }}</span>
            <span class="pending-list__meta">
              Заказ {{ order.order_number }}
              <template v-if="order.placed_at">
                · {{ new Date(order.placed_at).toLocaleDateString('ru-RU') }}
              </template>
            </span>
          </div>
          <button
            type="button"
            class="pay-btn"
            :disabled="isPayingOrder === order.order_number"
            @click="payOrder(order.order_number)"
          >
            {{ isPayingOrder === order.order_number ? 'Переходим…' : 'Оплатить' }}
          </button>
        </li>
      </ul>
    </article>

    <div v-if="isLoading" class="card card--skeleton">
      <AppSkeleton width="200px" height="24px" />
      <AppSkeleton width="70%" height="16px" />
    </div>

    <template v-else>
      <article class="card">
        <h3 class="card__title">Ваши сертификаты</h3>
        <p v-if="!myCertificates.length" class="card__hint card__hint--compact">
          Здесь появятся купленные и начисленные вам сертификаты. Код можно скопировать и передать получателю.
        </p>
        <ul v-else class="cert-list">
          <li v-for="c in myCertificates" :key="c.id" class="cert-list__item">
            <div class="cert-list__main">
              <div class="cert-list__row">
                <p class="cert-list__amount">{{ formatPrice(c.balance_remaining) }}</p>
                <span class="cert-list__badge" :data-status="c.status">{{ certStatusLabel(c.status) }}</span>
              </div>
              <p class="cert-list__meta">Номинал {{ formatPrice(c.initial_amount) }}</p>
              <p v-if="c.expires_at" class="cert-list__meta">
                Действителен до {{ new Date(c.expires_at).toLocaleDateString('ru-RU') }}
              </p>
              <div class="cert-list__code-row">
                <code v-if="codeVisible[c.id]" class="cert-list__code">{{ c.code }}</code>
                <code v-else class="cert-list__code cert-list__code--hidden">••••••••</code>
                <div class="cert-list__actions">
                  <button type="button" class="cert-list__btn" @click="toggleCodeVisible(c.id)">
                    {{ codeVisible[c.id] ? 'Скрыть' : 'Показать код' }}
                  </button>
                  <button type="button" class="cert-list__btn cert-list__btn--primary" @click="copyCertificateCode(c.code)">
                    <Copy :size="14" aria-hidden="true" />
                    Копировать
                  </button>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </article>

      <article class="card">
        <h3 class="card__title">Купить сертификат</h3>
        <p class="card__hint">Оплата через выбранный на сайте способ. После оплаты код появится в этом разделе.</p>

        <div class="preset-grid">
          <button
            v-for="n in presets"
            :key="n"
            type="button"
            class="preset-btn"
            :class="{ 'preset-btn--active': selectedPreset === n }"
            @click="selectedPreset = n"
          >
            {{ formatPrice(n) }}
          </button>
          <button
            type="button"
            class="preset-btn"
            :class="{ 'preset-btn--active': selectedPreset === 'custom' }"
            @click="selectedPreset = 'custom'"
          >
            Своя сумма
          </button>
        </div>

        <div v-if="selectedPreset === 'custom'" class="field">
          <label class="field__label" for="gc-custom-amt">Сумма, ₽</label>
          <input
            id="gc-custom-amt"
            v-model="customAmount"
            class="field__input"
            type="number"
            inputmode="decimal"
            min="100"
            max="500000"
            placeholder="Например, 2500"
          />
        </div>

        <div class="field">
          <label class="field__label" for="gc-pay">Оплата</label>
          <select id="gc-pay" v-model="paymentMethod" class="field__input">
            <option v-for="opt in paymentMethodOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <button type="button" class="buy-btn" :disabled="isBuying" @click="buyCertificate">
          {{ isBuying ? 'Переходим к оплате…' : 'Оформить и оплатить' }}
        </button>
      </article>
    </template>
  </section>
</template>

<style scoped>
.account-panel__header h2 {
  margin: 0;
  font-size: 28px;
  font-weight: 700;
}

.account-panel__header p {
  margin: 8px 0 0;
  color: var(--color-text-soft);
}

.card {
  margin-top: 20px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--background);
}

.card__title {
  margin: 0 0 8px;
  font-size: 17px;
  font-weight: 700;
}

.card__hint {
  margin: 0 0 16px;
  font-size: 14px;
  color: var(--muted-foreground);
  line-height: 1.45;
}

.card__hint--compact {
  margin-bottom: 0;
}

.cert-list {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cert-list__item {
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--border);
}

.cert-list__main {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cert-list__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.cert-list__amount {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.cert-list__badge {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--muted);
  color: var(--muted-foreground);
}

.cert-list__badge[data-status='active'] {
  background: color-mix(in srgb, var(--primary), transparent 88%);
  color: var(--primary);
}

.cert-list__badge[data-status='depleted'] {
  opacity: 0.85;
}

.cert-list__badge[data-status='cancelled'] {
  background: #fef2f2;
  color: #b42318;
}

.cert-list__meta {
  margin: 0;
  font-size: 12px;
  color: var(--muted-foreground);
}

.cert-list__code-row {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cert-list__code {
  display: block;
  margin: 0;
  padding: 8px 10px;
  border-radius: 8px;
  background: var(--muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 14px;
  word-break: break-all;
}

.cert-list__code--hidden {
  letter-spacing: 0.08em;
}

.cert-list__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.cert-list__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--background);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.cert-list__btn:hover {
  border-color: #000;
}

.cert-list__btn--primary {
  border-color: #000;
  background: #000;
  color: #fff;
}

.cert-list__btn--primary:hover {
  opacity: 0.9;
}

.preset-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.preset-btn {
  min-height: 44px;
  padding: 0 16px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--muted);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.preset-btn--active {
  border-color: #000;
  background: #000;
  color: #fff;
}

.field {
  margin-bottom: 14px;
}

.field__label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
}

.field__input {
  width: 100%;
  max-width: 320px;
  box-sizing: border-box;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  font: inherit;
  font-size: 15px;
}

.buy-btn {
  margin-top: 8px;
  min-height: 48px;
  padding: 0 22px;
  border-radius: 12px;
  border: none;
  background: #000;
  color: #fff;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.buy-btn:disabled {
  opacity: 0.55;
  cursor: default;
}

.card--skeleton {
  min-height: 120px;
}

/* ── Ожидающие оплаты ─────────────────────────────────────────────── */
.card--pending {
  border-color: color-mix(in srgb, #f59e0b, transparent 60%);
  background: color-mix(in srgb, #fef3c7, transparent 30%);
}

.pending-list {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pending-list__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid color-mix(in srgb, #f59e0b, transparent 55%);
  flex-wrap: wrap;
}

.pending-list__info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pending-list__amount {
  font-size: 18px;
  font-weight: 800;
}

.pending-list__meta {
  font-size: 12px;
  color: var(--muted-foreground);
}

.pay-btn {
  min-height: 40px;
  padding: 0 18px;
  border-radius: 10px;
  border: none;
  background: #f59e0b;
  color: #fff;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: opacity 0.15s;
}

.pay-btn:hover { opacity: 0.88; }
.pay-btn:disabled { opacity: 0.55; cursor: default; }
</style>
