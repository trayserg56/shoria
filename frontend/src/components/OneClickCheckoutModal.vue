<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { trackEvent } from '@/lib/analytics'
import { toast } from '@/lib/toast'
import { useCartStore } from '@/stores/cart'
import { useOneClickCheckoutModalStore } from '@/stores/one-click-checkout-modal'

type ModalPhase = 'form' | 'success'

const modalStore = useOneClickCheckoutModalStore()
const cartStore = useCartStore()
const { isOpen, context } = storeToRefs(modalStore)

const phase = ref<ModalPhase>('form')
const successOrderNumber = ref<string | null>(null)
const deliveryMethod = ref('')
const paymentMethod = ref('')
const isHydrating = ref(false)
const isSubmitting = ref(false)
const loadError = ref('')

const deliveryMethods = computed(() => cartStore.checkoutOptions?.delivery_methods ?? [])
const paymentMethods = computed(() => cartStore.checkoutOptions?.payment_methods ?? [])

function formatPrice(value: number, currency = 'RUB') {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}

function coerceCode<T extends { code: string }>(list: readonly T[], preferred?: string): string {
  const p = preferred?.trim() ?? ''

  if (p && list.some((x) => x.code === p)) {
    return p
  }

  return String(list[0]?.code ?? '')
}

const selectedDeliveryFee = computed(() => {
  const row = deliveryMethods.value.find((m) => String(m.code) === deliveryMethod.value)

  return row?.fee ?? 0
})

const qtyLabel = computed(() => context.value?.qty ?? 1)

watch(
  isOpen,
  async (open) => {
    if (!open) {
      loadError.value = ''
      deliveryMethod.value = ''
      paymentMethod.value = ''
      phase.value = 'form'
      successOrderNumber.value = null
      return
    }

    if (!context.value) {
      return
    }

    phase.value = 'form'
    successOrderNumber.value = null
    loadError.value = ''
    isHydrating.value = true

    try {
      await cartStore.loadCheckoutOptions()
      const suggestions = await cartStore.fetchOneClickSuggestions()

      deliveryMethod.value = coerceCode(deliveryMethods.value, suggestions.delivery_method)
      paymentMethod.value = coerceCode(paymentMethods.value, suggestions.payment_method)

      if (!deliveryMethod.value || !paymentMethod.value) {
        loadError.value =
          'Не удалось подобрать доставку и оплату. Зайдите в корзину или попробуйте позже.'
      }
    } catch (error) {
      console.error(error)
      loadError.value = 'Не удалось загрузить способы доставки.'
    } finally {
      isHydrating.value = false
    }
  },
)

function closeModal() {
  modalStore.close()
}

async function submitQuickOrder() {
  const ctx = context.value

  if (!ctx || !deliveryMethod.value || !paymentMethod.value || isSubmitting.value) {
    return
  }

  isSubmitting.value = true

  try {
    const order = await cartStore.oneClickCheckout({
      product_slug: ctx.productSlug,
      product_variant_id: ctx.productVariantId ?? undefined,
      qty: ctx.qty,
      delivery_method: deliveryMethod.value,
      payment_method: paymentMethod.value,
    })

    void trackEvent('one_click_order', {
      source: ctx.source,
      slug: ctx.productSlug,
      order_number: order.order_number,
      variant_id: ctx.productVariantId ?? undefined,
    })

    successOrderNumber.value = order.order_number
    phase.value = 'success'
  } catch (error) {
    console.error(error)
    toast.error(
      'Не удалось оформить быстрый заказ. Проверьте телефон в профиле или оформите через корзину.',
    )
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div v-if="isOpen && context" class="modal-backdrop" @click.self="closeModal">
    <section
      class="modal-card"
      :class="{ 'modal-card--success': phase === 'success' }"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="phase === 'success' ? 'one-click-success-title' : 'one-click-title'"
    >
      <button type="button" class="close-btn" aria-label="Закрыть" @click="closeModal">×</button>

      <template v-if="phase === 'success' && successOrderNumber">
        <div class="success-body">
          <div class="success-icon" aria-hidden="true">
            <svg class="success-icon__svg" viewBox="0 0 88 88" fill="none">
              <circle cx="44" cy="44" r="38" stroke="#dbeafe" stroke-width="3" />
              <circle cx="44" cy="44" r="30" stroke="#3b82f6" stroke-width="2" />
              <path
                d="M30 46.5l10.2 10.2L58 34"
                stroke="#60a5fa"
                stroke-width="3.2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <h2 id="one-click-success-title" class="success-title">Спасибо за заказ!</h2>
          <p class="success-order">
            Ваш заказ <strong>№{{ successOrderNumber }}</strong>
          </p>
          <p class="success-hint">Наш менеджер свяжется с вами в ближайшее время.</p>
          <button type="button" class="btn-success-close" @click="closeModal">Закрыть</button>
        </div>
      </template>

      <template v-else>
        <header class="modal-header">
          <h2 id="one-click-title">Быстрый заказ</h2>
          <p class="modal-lede">
            Проверьте доставку и оплату. Контакты берутся из вашего аккаунта и прошлых заказов.
          </p>
        </header>

        <div v-if="isHydrating" class="hydrate-stack">
          <AppSkeleton height="72px" width="100%" radius="12px" />
          <AppSkeleton height="50px" width="100%" radius="12px" />
          <AppSkeleton height="50px" width="100%" radius="12px" />
        </div>

        <template v-else-if="context">
          <div class="product-snippet">
            <p class="product-snippet__name">{{ context.productName }}</p>
            <p v-if="context.productPrice != null" class="product-snippet__price">
              {{ formatPrice(context.productPrice, context.currency ?? 'RUB') }}
              · {{ qtyLabel }} шт.
            </p>
            <p v-else class="product-snippet__price">{{ qtyLabel }} шт.</p>
          </div>

          <p v-if="loadError" class="field-error">{{ loadError }}</p>

          <div v-if="!loadError" class="fields">
            <div class="field">
              <label class="label" for="one-click-delivery">Доставка</label>
              <select id="one-click-delivery" v-model="deliveryMethod" class="oc-select">
                <option v-for="m in deliveryMethods" :key="m.code" :value="String(m.code)">
                  {{ m.name }}{{ m.is_test_mode ? ' · тест' : '' }} ({{ formatPrice(m.fee) }})
                </option>
              </select>
              <p v-if="deliveryMethod" class="hint">
                Выбрано: стоимость доставки {{ formatPrice(selectedDeliveryFee) }}
              </p>
            </div>

            <div class="field">
              <label class="label" for="one-click-payment">Оплата</label>
              <select id="one-click-payment" v-model="paymentMethod" class="oc-select">
                <option v-for="m in paymentMethods" :key="m.code" :value="String(m.code)">
                  {{ m.name }}{{ m.is_test_mode ? ' · тест' : '' }}
                </option>
              </select>
            </div>
          </div>

          <div class="actions">
            <button
              type="button"
              class="btn-cancel"
              :disabled="isSubmitting"
              @click="closeModal"
            >
              Отмена
            </button>
            <button
              type="button"
              class="btn-submit"
              :disabled="!!loadError || !deliveryMethod || !paymentMethod || isSubmitting"
              @click="submitQuickOrder"
            >
              {{ isSubmitting ? 'Оформляем…' : 'Подтвердить заказ' }}
            </button>
          </div>
        </template>
      </template>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  /* Выше быстрого просмотра (130), иначе форма скрыта за модалкой каталога */
  z-index: 140;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(17 21 31 / 50%);
}

.modal-card {
  position: relative;
  width: min(440px, 100%);
  border-radius: 18px;
  background: #fff;
  padding: 20px 18px 18px;
  box-shadow: 0 20px 48px rgb(16 24 40 / 22%);
}

.modal-card--success {
  padding: 28px 24px 22px;
  text-align: center;
}

.close-btn {
  position: absolute;
  right: 10px;
  top: 8px;
  border: none;
  background: transparent;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
  color: #94a3b8;
}

.modal-header h2 {
  margin: 0 0 8px;
  font-size: clamp(22px, 4vw, 28px);
  font-family: var(--font-display);
  line-height: 1.15;
}

.modal-lede {
  margin: 0 0 16px;
  font-size: 14px;
  line-height: 1.45;
  color: #64748b;
}

.hydrate-stack {
  display: grid;
  gap: 12px;
  margin-bottom: 8px;
}

.product-snippet {
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid var(--border);
  margin-bottom: 16px;
}

.product-snippet__name {
  margin: 0 0 4px;
  font-weight: 600;
  font-size: 15px;
  color: var(--foreground);
  line-height: 1.35;
}

.product-snippet__price {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.fields {
  display: grid;
  gap: 14px;
  margin-bottom: 18px;
}

.field {
  display: grid;
  gap: 6px;
}

.label {
  font-size: 14px;
  font-weight: 600;
  color: #475569;
}

.oc-select {
  width: 100%;
  box-sizing: border-box;
  min-height: 50px;
  padding: 10px 36px 10px 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background-color: var(--background);
  font-family: inherit;
  font-size: 16px;
  line-height: 1.4;
  color: var(--foreground);
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
}

.oc-select:focus {
  outline: none;
  border-color: #000;
}

.hint {
  margin: 0;
  font-size: 12px;
  color: #64748b;
}

.field-error {
  margin: 0 0 12px;
  font-size: 14px;
  color: #a83a0f;
}

.actions {
  display: grid;
  grid-template-columns: 1fr 1.35fr;
  gap: 10px;
}

.btn-cancel {
  min-height: 46px;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--background);
  font: inherit;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  color: var(--foreground);
}

.btn-cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-submit {
  min-height: 46px;
  border-radius: 12px;
  border: none;
  background: var(--primary);
  color: var(--primary-foreground);
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 1px 2px rgb(37 99 235 / 25%);
}

.btn-submit:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.success-body {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding-top: 8px;
}

.success-icon {
  margin-bottom: 4px;
}

.success-icon__svg {
  width: 88px;
  height: 88px;
  display: block;
}

.success-title {
  margin: 0;
  font-size: clamp(22px, 4vw, 26px);
  font-family: var(--font-display);
  font-weight: 700;
  color: #0f172a;
  line-height: 1.25;
}

.success-order {
  margin: 0;
  font-size: 16px;
  color: #334155;
  line-height: 1.45;
}

.success-order strong {
  font-weight: 700;
  color: #0f172a;
}

.success-hint {
  margin: 4px 0 8px;
  font-size: 14px;
  line-height: 1.45;
  color: #64748b;
  max-width: 28rem;
}

.btn-success-close {
  width: 100%;
  margin-top: 8px;
  min-height: 48px;
  border-radius: 12px;
  border: none;
  background: #f1f5f9;
  color: #0f172a;
  font: inherit;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-success-close:hover {
  background: #e2e8f0;
}

@media (max-width: 480px) {
  .actions {
    grid-template-columns: 1fr;
  }

  .btn-cancel {
    order: 2;
  }

  .btn-submit {
    order: 1;
  }
}
</style>
