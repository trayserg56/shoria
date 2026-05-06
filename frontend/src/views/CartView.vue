<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Check, Heart, Trash2, X } from 'lucide-vue-next'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { toProductRoute } from '@/lib/product-route'
import { applyImageFallback, resolveImageSrc } from '@/lib/image-fallback'
import { fetchJson } from '@/lib/api'
import UnifiedProductCard from '@/components/UnifiedProductCard.vue'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useCheckoutPreview } from '@/composables/useCheckoutPreview'
import { saveCheckoutIncentives } from '@/lib/checkout-context'

const authStore = useAuthStore()
const cartStore = useCartStore()
const wishlistStore = useWishlistStore()
const router = useRouter()
const { items, checkoutOptions, isLoading: isCartLoading, subtotal: cartSubtotal } =
  storeToRefs(cartStore)
const { user } = storeToRefs(authStore)

const promoCode = ref('')
const loyaltyPointsToSpend = ref(0)
const deliveryMethod = ref('')

const {
  previewLoading,
  promoStatusMessage,
  promoStatusApplied,
  loyaltyEnabled,
  displayedDiscount,
  displayedLoyaltyDiscount,
  loyaltyMaxPoints,
  loyaltyPointsBalance,
  refreshPreview,
  schedulePreviewRefresh,
} = useCheckoutPreview({
  deliveryMethod,
  promoCode,
  loyaltyPointsToSpend,
})

const deliveryMethods = computed(() => checkoutOptions.value?.delivery_methods ?? [])

type RecommendedProduct = {
  id: number
  name: string
  brand: string | null
  slug: string
  price: number
  old_price: number | null
  currency: string
  stock: number
  tags: Array<{ code: string; label: string }>
  reviews_summary: { count: number; average: number | null }
  category: { name: string; slug: string } | null
  image_url: string | null
}

const crossSells = ref<RecommendedProduct[]>([])
const crossSellsSlider = ref<HTMLElement | null>(null)
const isLoadingCrossSells = ref(false)

watch(
  () => items.value.map((i) => i.product_id).join(','),
  async (newIds) => {
    if (!newIds) {
      crossSells.value = []
      return
    }
    isLoadingCrossSells.value = true
    try {
      const response = await fetchJson<{ data: RecommendedProduct[] }>(`/api/recommendations/cart?ids=${newIds}`)
      crossSells.value = response.data
    } catch {
      crossSells.value = []
    } finally {
      isLoadingCrossSells.value = false
    }
  },
  { immediate: true }
)

function scrollSlider(el: HTMLElement | null, direction: 'prev' | 'next') {
  if (!el) return
  const cardWidth = el.firstElementChild ? (el.firstElementChild as HTMLElement).offsetWidth + 20 : 300
  el.scrollBy({ left: direction === 'next' ? cardWidth : -cardWidth, behavior: 'smooth' })
}

async function applyPromo() {
  await refreshPreview()
}

const selectedIds = ref<Set<number>>(new Set())
const previousIdSignature = ref<string | null>(null)
const selectAllInput = ref<HTMLInputElement | null>(null)

const hasUnavailableItems = computed(() => items.value.some((item) => !item.available))
const showCartSkeleton = computed(() => isCartLoading.value && items.value.length === 0)
const showSummarySkeleton = computed(
  () => showCartSkeleton.value || (!checkoutOptions.value && (isCartLoading.value || previewLoading.value)),
)
const showSummaryBusy = computed(() => !showSummarySkeleton.value && (isCartLoading.value || previewLoading.value))

const allSelected = computed(
  () => items.value.length > 0 && selectedIds.value.size === items.value.length,
)
const someSelected = computed(
  () => selectedIds.value.size > 0 && selectedIds.value.size < items.value.length,
)

const selectionSubtotal = computed(() =>
  items.value.filter((i) => selectedIds.value.has(i.id)).reduce((s, i) => s + i.total_price, 0),
)
const selectionPieces = computed(() =>
  items.value.filter((i) => selectedIds.value.has(i.id)).reduce((s, i) => s + i.qty, 0),
)
/** Доля выбранных товаров в сумме корзины — для масштабирования скидок из preview */
const selectionRatio = computed(() => {
  const full = cartSubtotal.value
  if (full <= 0) {
    return 0
  }

  return Math.min(1, Math.max(0, selectionSubtotal.value / full))
})
const sidebarDiscount = computed(() => selectionRatio.value * displayedDiscount.value)
const sidebarLoyaltyDiscount = computed(() => selectionRatio.value * displayedLoyaltyDiscount.value)
const sidebarTotal = computed(
  () => selectionSubtotal.value - sidebarDiscount.value - sidebarLoyaltyDiscount.value,
)
const hasSelection = computed(() => selectedIds.value.size > 0)

const canProceedToCheckout = computed(
  () =>
    items.value.length > 0
    && hasSelection.value
    && !hasUnavailableItems.value
    && !isCartLoading.value,
)

function formatPrice(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

function isSelected(id: number) {
  return selectedIds.value.has(id)
}

function toggleItem(id: number) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  selectedIds.value = next
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
    return
  }
  selectedIds.value = new Set(items.value.map((i) => i.id))
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

async function removeLine(itemId: number) {
  await cartStore.removeItem(itemId)
}

function canIncreaseQty(item: (typeof items.value)[number]) {
  return item.available
}

function canDecreaseQty(item: (typeof items.value)[number]) {
  return item.qty > 1 || item.available_stock > 0
}

async function removeSelected() {
  const ids = [...selectedIds.value]
  if (ids.length === 0) {
    return
  }

  for (const id of ids) {
    await cartStore.removeItem(id, { quiet: true })
  }

  selectedIds.value = new Set()
}

async function clearEntireCart() {
  if (items.value.length === 0) {
    return
  }

  const ok = window.confirm('Удалить все товары из корзины?')
  if (!ok) {
    return
  }

  await cartStore.clearCart()
  selectedIds.value = new Set()
}

function goCheckout() {
  saveCheckoutIncentives({
    promoCode: promoCode.value,
    loyaltyPointsToSpend: loyaltyPointsToSpend.value,
  })
  const code = promoCode.value.trim()
  void router.push({
    name: 'checkout',
    query: code ? { promo: code } : {},
  })
}

function wishlistToggle(item: (typeof items.value)[number]) {
  wishlistStore.hydrate()
  wishlistStore.toggle({
    id: item.product_id,
    slug: item.product_slug,
    name: item.product_name,
    price: item.unit_price,
    old_price: null,
    stock: item.available_stock,
    currency: 'RUB',
    image_url: item.image_url,
    category: null,
  })
}

watch(
  () =>
    items.value
      .map((i) => i.id)
      .slice()
      .sort((a, b) => a - b)
      .join(','),
  (sig) => {
    const ids = items.value.map((i) => i.id)
    if (ids.length === 0) {
      selectedIds.value = new Set()
      previousIdSignature.value = sig
      return
    }

    const prevSig = previousIdSignature.value
    const prevList = prevSig ? prevSig.split(',').map(Number).filter(Boolean) : []
    const next = new Set<number>()

    for (const id of ids) {
      if (selectedIds.value.has(id)) {
        next.add(id)
      }
      if (!prevList.includes(id)) {
        next.add(id)
      }
    }

    if (next.size === 0) {
      ids.forEach((id) => next.add(id))
    }

    selectedIds.value = next
    previousIdSignature.value = sig
  },
  { immediate: true },
)

watch([allSelected, someSelected], async () => {
  await nextTick()
  if (selectAllInput.value) {
    selectAllInput.value.indeterminate = someSelected.value
  }
})

watch(
  () => [deliveryMethod.value, promoCode.value, loyaltyPointsToSpend.value, cartSubtotal.value],
  () => {
    if (loyaltyPointsToSpend.value < 0) {
      loyaltyPointsToSpend.value = 0
      return
    }
    schedulePreviewRefresh()
  },
)

onMounted(async () => {
  wishlistStore.hydrate()
  if (!user.value) {
    await authStore.loadMe()
  }
  await cartStore.loadCheckoutOptions()
  deliveryMethod.value = deliveryMethods.value[0]?.code ?? ''
  await cartStore.loadCart()
  await refreshPreview()
})
</script>

<template>
  <main class="cart-page">
    <nav class="breadcrumbs" aria-label="Breadcrumbs">
      <RouterLink to="/">Главная</RouterLink>
      <span>/</span>
      <span>Корзина</span>
    </nav>

    <header class="cart-page__header">
      <h1>Корзина</h1>
    </header>

    <section v-if="showCartSkeleton || items.length > 0" class="cart-layout">
      <div class="cart-items-card" :class="{ 'cart-items-card--busy': isCartLoading && items.length > 0 }">
        <div v-if="showCartSkeleton" class="cart-items-skeleton">
          <article v-for="index in 2" :key="`cart-skeleton-${index}`" class="cart-row cart-row--skeleton">
            <AppSkeleton width="20px" height="20px" radius="4px" />
            <AppSkeleton width="88px" height="88px" radius="10px" />
            <div class="cart-row__main">
              <AppSkeleton width="66%" height="18px" />
              <AppSkeleton width="38%" height="14px" />
              <AppSkeleton width="30%" height="14px" />
            </div>
            <div class="cart-row__aside-skeleton">
              <AppSkeleton width="72px" height="22px" />
              <AppSkeleton width="120px" height="40px" radius="999px" />
            </div>
          </article>
        </div>

        <template v-else>
          <div class="cart-toolbar">
            <label class="cart-toolbar__select">
              <input
                ref="selectAllInput"
                type="checkbox"
                class="cart-checkbox"
                :checked="allSelected"
                @change="toggleSelectAll"
              />
              <span>Выбрать все</span>
            </label>
            <button type="button" class="cart-toolbar__link" :disabled="selectedIds.size === 0" @click="removeSelected">
              Удалить выбранные
            </button>
            <button type="button" class="cart-toolbar__clear" @click="clearEntireCart">
              <X class="cart-toolbar__clear-icon" :size="16" stroke-width="2" aria-hidden="true" />
              Очистить корзину
            </button>
          </div>

          <div class="cart-rows">
            <article
              v-for="item in items"
              :key="item.id"
              class="cart-row"
              :class="{ 'cart-row--unavailable': !item.available }"
            >
              <label class="cart-row__check">
                <input
                  type="checkbox"
                  class="cart-checkbox"
                  :checked="isSelected(item.id)"
                  @change="toggleItem(item.id)"
                />
                <span class="sr-only">Выбрать позицию</span>
              </label>

              <RouterLink :to="toProductRoute({ slug: item.product_slug })" class="cart-row__thumb" tabindex="-1">
                <img :src="resolveImageSrc(item.image_url)" :alt="item.product_name" @error="applyImageFallback" />
              </RouterLink>

              <div class="cart-row__main">
                <RouterLink :to="toProductRoute({ slug: item.product_slug })" class="cart-row__title">
                  {{ item.product_name }}
                </RouterLink>
                <p v-if="item.variant_label" class="cart-row__meta">
                  Вариант: {{ item.variant_label }}
                </p>
                <p v-if="item.brand" class="cart-row__meta">
                  Производитель: {{ item.brand }}
                </p>
                <p v-if="item.available" class="cart-row__stock cart-row__stock--ok">
                  <Check class="cart-row__stock-icon" :size="16" stroke-width="2.5" aria-hidden="true" />
                  В наличии
                </p>
                <p v-else class="cart-row__stock cart-row__stock--bad">
                  {{ item.availability_message ?? 'Нет в наличии.' }}
                </p>
              </div>

              <div class="cart-row__aside">
                <p class="cart-row__unit">{{ formatPrice(item.unit_price) }}</p>
                <div class="cart-row__controls">
                  <div class="qty-pill">
                    <button type="button" :disabled="!canDecreaseQty(item)" @click="decreaseQty(item.id, item.qty)">
                      −
                    </button>
                    <span>{{ item.qty }}</span>
                    <button type="button" :disabled="!canIncreaseQty(item)" @click="increaseQty(item.id, item.qty)">
                      +
                    </button>
                  </div>
                  <div class="cart-row__icon-actions">
                    <button
                      type="button"
                      class="icon-round"
                      :class="{ 'icon-round--active': wishlistStore.has(item.product_id) }"
                      :title="
                        wishlistStore.has(item.product_id) ? 'Убрать из избранного' : 'В избранное'
                      "
                      @click="wishlistToggle(item)"
                    >
                      <Heart
                        class="icon-round__svg"
                        :size="18"
                        stroke-width="2"
                        :fill="wishlistStore.has(item.product_id) ? 'currentColor' : 'none'"
                      />
                    </button>
                    <button type="button" class="icon-round" title="Удалить" @click="removeLine(item.id)">
                      <Trash2 class="icon-round__svg" :size="18" stroke-width="2" />
                    </button>
                  </div>
                </div>
                <p class="cart-row__line-total">{{ formatPrice(item.total_price) }}</p>
              </div>
            </article>
          </div>
        </template>

        <div v-if="isCartLoading && items.length > 0" class="cart-items-overlay" aria-hidden="true" />
      </div>

      <aside class="order-summary" :class="{ 'order-summary--busy': showSummaryBusy }">
        <template v-if="showSummarySkeleton">
          <div class="order-summary__inner order-summary__inner--skeleton">
            <AppSkeleton width="140px" height="22px" />
            <AppSkeleton width="100%" height="16px" />
            <AppSkeleton width="100%" height="44px" radius="10px" />
            <AppSkeleton width="100%" height="44px" radius="10px" />
          </div>
        </template>
        <template v-else>
          <div class="order-summary__inner">
            <h2 class="order-summary__title">Ваш заказ</h2>
            <dl class="order-summary__lines">
              <div class="order-summary__line">
                <dt>Товары, {{ selectionPieces }} шт.</dt>
                <dd>{{ formatPrice(selectionSubtotal) }}</dd>
              </div>
              <div v-if="sidebarDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Скидка</dt>
                <dd>−{{ formatPrice(sidebarDiscount) }}</dd>
              </div>
              <div v-if="loyaltyEnabled && sidebarLoyaltyDiscount > 0" class="order-summary__line order-summary__line--muted">
                <dt>Баллы</dt>
                <dd>−{{ formatPrice(sidebarLoyaltyDiscount) }}</dd>
              </div>
              <div class="order-summary__line order-summary__total">
                <dt>Итого</dt>
                <dd>{{ formatPrice(sidebarTotal) }}</dd>
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

            <div v-if="loyaltyEnabled" class="order-summary__loyalty">
              <label class="order-summary__loyalty-label" for="cart-loyalty-points">Списать баллы</label>
              <input
                id="cart-loyalty-points"
                v-model.number="loyaltyPointsToSpend"
                class="order-summary__input"
                type="number"
                min="0"
                :max="loyaltyMaxPoints > 0 ? loyaltyMaxPoints : undefined"
                inputmode="numeric"
                placeholder="0"
              />
              <p class="order-summary__loyalty-hint">
                Доступно: {{ loyaltyPointsBalance }} · максимум к списанию: {{ loyaltyMaxPoints }}
              </p>
            </div>

            <div class="order-summary__promo">
              <input
                v-model="promoCode"
                class="order-summary__input"
                type="text"
                name="cart_promo_code"
                autocomplete="off"
                placeholder="Введите промокод"
              />
              <button type="button" class="order-summary__promo-btn" @click="applyPromo">Применить</button>
            </div>
            <p v-if="promoStatusMessage" class="order-summary__promo-msg" :class="{ 'order-summary__promo-msg--ok': promoStatusApplied }">
              {{ promoStatusMessage }}
            </p>

            <div class="order-summary__cta-wrap">
              <button
                type="button"
                class="order-summary__checkout"
                :disabled="!canProceedToCheckout"
                @click="goCheckout"
              >
                Оформить заказ
              </button>
            </div>
            <p v-if="hasUnavailableItems" class="order-summary__warn">
              Удалите или замените недоступные позиции, чтобы продолжить.
            </p>
            <p v-else-if="items.length > 0 && !hasSelection" class="order-summary__hint">
              Отметьте товары для оформления заказа.
            </p>
          </div>
        </template>
      </aside>
    </section>

    <section v-else class="cart-empty">
      <p>Корзина пока пустая.</p>
      <RouterLink to="/catalog" class="cart-empty__link">Перейти в каталог</RouterLink>
    </section>

    <section v-if="crossSells.length" class="cart-cross-sells">
      <header class="cart-cross-sells__header">
        <h2>Рекомендуем добавить</h2>
      </header>
      <div class="cart-cross-sells__actions">
        <button type="button" class="slider-nav" @click="scrollSlider(crossSellsSlider, 'prev')">←</button>
        <button type="button" class="slider-nav" @click="scrollSlider(crossSellsSlider, 'next')">→</button>
      </div>
      <div ref="crossSellsSlider" class="cart-cross-sells__slider">
        <UnifiedProductCard
          v-for="item in crossSells"
          :key="item.id"
          :product="item"
          source="cart_cross_sells"
          class="slider-card"
        />
      </div>
    </section>

  </main>
</template>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.cart-page {
  --cart-accent: #000;
  --cart-accent-soft: color-mix(in srgb, #000 6%, var(--card));
  --cart-stock-ok: #166534;
  --cart-promo: #db2777;
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

.cart-page__header h1 {
  font-size: clamp(26px, 4vw, 34px);
  font-weight: 700;
  letter-spacing: -0.02em;
  margin: 0;
  line-height: 1.2;
}

.cart-layout {
  margin-top: 22px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  align-items: start;
}

.cart-items-card {
  position: relative;
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
  background: var(--card);
  box-shadow: 0 1px 3px rgb(15 23 42 / 6%);
  overflow: hidden;
}

.cart-items-card--busy {
  opacity: 0.72;
}

.cart-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 18px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--muted) 35%, var(--card));
}

.cart-toolbar__select {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--foreground);
  cursor: pointer;
}

.cart-toolbar__link {
  border: none;
  background: none;
  padding: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--muted-foreground);
  cursor: pointer;
}

.cart-toolbar__link:hover:not(:disabled) {
  color: #000;
}

.cart-toolbar__link:disabled {
  opacity: 0.45;
  cursor: default;
}

.cart-toolbar__clear {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: none;
  padding: 0;
  font-size: 14px;
  color: var(--muted-foreground);
  cursor: pointer;
}

.cart-toolbar__clear:hover {
  color: var(--foreground);
}

.cart-toolbar__clear-icon {
  flex-shrink: 0;
}

.cart-checkbox {
  width: 18px;
  height: 18px;
  accent-color: var(--cart-accent);
  cursor: pointer;
}

.cart-rows {
  display: flex;
  flex-direction: column;
}

.cart-row {
  display: grid;
  grid-template-columns: auto 88px minmax(0, 1fr) minmax(140px, 200px);
  gap: 14px 16px;
  padding: 16px;
  border-bottom: 1px solid var(--border);
  align-items: start;
}

.cart-row:last-child {
  border-bottom: none;
}

.cart-row--unavailable {
  background: color-mix(in srgb, #f97316 6%, var(--card));
}

.cart-row__check {
  padding-top: 36px;
}

.cart-row__thumb img {
  width: 88px;
  height: 88px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--border);
  display: block;
}

.cart-row__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--foreground);
  text-decoration: none;
  line-height: 1.35;
}

.cart-row__title:hover {
  color: var(--cart-accent);
}

.cart-row__meta {
  margin-top: 4px;
  font-size: 13px;
  color: var(--muted-foreground);
}

.cart-row__stock {
  margin-top: 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}

.cart-row__stock--ok {
  color: var(--cart-stock-ok);
}

.cart-row__stock-icon {
  flex-shrink: 0;
}

.cart-row__stock--bad {
  color: #c2410c;
}

.cart-row__aside {
  justify-self: end;
  text-align: right;
  min-width: 0;
}

.cart-row__unit {
  font-size: 15px;
  font-weight: 700;
  color: var(--foreground);
  margin: 0;
}

.cart-row__line-total {
  margin-top: 10px;
  font-size: 15px;
  font-weight: 700;
}

.cart-row__controls {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}

.qty-pill {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 999px;
  overflow: hidden;
  background: var(--background);
}

.qty-pill button {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  color: var(--foreground);
}

.qty-pill button:disabled {
  opacity: 0.35;
  cursor: default;
}

.qty-pill span {
  min-width: 28px;
  text-align: center;
  font-size: 14px;
  font-weight: 600;
}

.cart-row__icon-actions {
  display: flex;
  gap: 8px;
}

.icon-round {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--background);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--muted-foreground);
}

.icon-round:hover {
  border-color: color-mix(in srgb, var(--cart-accent) 45%, var(--border));
  color: var(--cart-accent);
}

.icon-round--active {
  color: #e11d48;
  border-color: color-mix(in srgb, #e11d48 35%, var(--border));
}

.icon-round__svg {
  display: block;
}

.cart-items-overlay {
  position: absolute;
  inset: 0;
  background: rgb(255 255 255 / 35%);
  backdrop-filter: blur(1px);
  pointer-events: none;
}

.cart-items-skeleton {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cart-row--skeleton {
  grid-template-columns: auto 88px 1fr 120px;
}

.cart-row__aside-skeleton {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}

.order-summary {
  position: sticky;
  top: calc(var(--site-header-sticky-offset) + 12px);
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
  background: var(--card);
  box-shadow: 0 8px 30px rgb(15 23 42 / 7%);
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

.order-summary__loyalty {
  margin-top: 14px;
  display: grid;
  gap: 6px;
}

.order-summary__loyalty-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted-foreground);
}

.order-summary__cta-wrap {
  margin-top: 16px;
}

.order-summary__promo {
  margin-top: 14px;
  display: flex;
  gap: 8px;
  align-items: stretch;
}

.order-summary__input {
  flex: 1;
  min-width: 0;
  min-height: 44px;
  box-sizing: border-box;
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
  font-family: inherit;
  line-height: 1.4;
  color: var(--foreground);
  border: 1px solid var(--border);
  background: var(--background);
}

.order-summary__input:focus {
  outline: none;
  border-color: #000;
}

.order-summary__input:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.order-summary__promo-btn {
  flex-shrink: 0;
  padding: 0 18px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  color: #fff;
  background: var(--cart-promo);
  cursor: pointer;
}

.order-summary__promo-btn:hover {
  filter: brightness(1.05);
}

.order-summary__promo-msg {
  margin-top: 8px;
  font-size: 13px;
  color: #8f5014;
}

.order-summary__promo-msg--ok {
  color: var(--foreground);
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

.order-summary__warn {
  margin-top: 12px;
  font-size: 13px;
  color: #c2410c;
}

.order-summary__hint {
  margin-top: 12px;
  font-size: 13px;
  color: var(--muted-foreground);
}

.order-summary__loyalty-hint {
  margin-top: 6px;
  font-size: 12px;
  line-height: 1.4;
  color: var(--muted-foreground);
}

.cart-empty {
  margin-top: 32px;
  text-align: center;
  padding: 40px 16px;
  border-radius: 16px;
  border: 1px dashed var(--border);
}

.cart-empty__link {
  display: inline-block;
  margin-top: 10px;
  font-weight: 600;
  color: var(--cart-accent);
  text-decoration: none;
}

.cart-empty__link:hover {
  text-decoration: underline;
}

@media (max-width: 1020px) {
  .cart-layout {
    grid-template-columns: 1fr;
  }

  .order-summary {
    position: static;
  }

  .cart-row {
    grid-template-columns: auto 72px minmax(0, 1fr);
    grid-template-areas:
      'check thumb main'
      'check thumb aside';
  }

  .cart-row__check {
    grid-area: check;
    padding-top: 28px;
  }

  .cart-row__thumb {
    grid-area: thumb;
  }

  .cart-row__main {
    grid-area: main;
  }

  .cart-row__aside {
    grid-area: aside;
    justify-self: stretch;
    text-align: left;
  }

  .cart-row__controls {
    align-items: flex-start;
  }

  .cart-row__unit {
    margin-bottom: 4px;
  }

  .cart-row__line-total {
    margin-top: 8px;
  }
}
.cart-cross-sells {
  margin-top: 48px;
  grid-column: 1 / -1;
}

.cart-cross-sells__header h2 {
  font-family: var(--font-display, inherit);
  font-size: clamp(24px, 3vw, 32px);
  margin: 0 0 16px;
}

.cart-cross-sells__actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.cart-cross-sells__actions .slider-nav {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 1px solid var(--border);
  background: var(--background);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-cross-sells__slider {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 16px;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.cart-cross-sells__slider::-webkit-scrollbar {
  display: none;
}

.cart-cross-sells__slider > * {
  flex: 0 0 clamp(240px, 30%, 320px);
  scroll-snap-align: start;
}
</style>
