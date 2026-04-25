<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { requestJson } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

type LoyaltyResponse = {
  program: {
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
  }
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
  history: Array<{
    id: number
    type: string
    points_delta: number
    balance_after: number
    description: string | null
    order_id: number | null
    created_at: string | null
  }>
}

const authStore = useAuthStore()
const isLoading = ref(false)
const payload = ref<LoyaltyResponse | null>(null)

const account = computed(() => payload.value?.account ?? authStore.user?.loyalty ?? null)
const tiers = computed(() => payload.value?.program.tiers ?? [])
const history = computed(() => payload.value?.history ?? [])

function formatMoney(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

function formatDate(value: string | null) {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(async () => {
  isLoading.value = true
  try {
    await authStore.loadMe()
    payload.value = await requestJson<LoyaltyResponse>('/api/loyalty/me')
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <section class="account-panel">
    <div class="account-panel__header">
      <div>
        <h2>Лояльность</h2>
        <p>Баланс баллов, текущий уровень и история начислений.</p>
      </div>
    </div>

    <div v-if="isLoading" class="card card--skeleton">
      <AppSkeleton width="180px" height="24px" />
      <AppSkeleton width="60%" height="16px" />
      <AppSkeleton width="48%" height="16px" />
    </div>

    <template v-else>
      <article class="card">
        <div class="grid">
          <div>
            <p class="muted">Баланс</p>
            <strong class="big">{{ account?.points_balance ?? 0 }} баллов</strong>
            <p class="muted">1 балл = {{ formatMoney(payload?.program.point_value ?? 1) }}</p>
          </div>
          <div>
            <p class="muted">Текущий уровень</p>
            <strong>{{ account?.current_tier?.name ?? 'Base' }}</strong>
            <p class="muted">Начисление: {{ (account?.accrual_percent ?? 0).toFixed(2) }}%</p>
          </div>
          <div>
            <p class="muted">До следующего уровня</p>
            <strong v-if="account?.next_tier">{{ formatMoney(account.amount_to_next_tier) }}</strong>
            <strong v-else>Максимальный</strong>
            <p class="muted" v-if="account?.next_tier">Следующий: {{ account.next_tier.name }}</p>
          </div>
        </div>
      </article>

      <article class="card">
        <h3>Уровни программы</h3>
        <div class="tiers">
          <div v-for="tier in tiers" :key="tier.name" class="tier">
            <strong>{{ tier.name }}</strong>
            <span>От {{ formatMoney(tier.min_spent) }}</span>
            <span>{{ tier.accrual_percent.toFixed(2) }}% начисления</span>
          </div>
        </div>
      </article>

      <article class="card">
        <h3>История операций</h3>
        <div v-if="history.length" class="history">
          <div v-for="item in history" :key="item.id" class="history__row">
            <div>
              <strong>{{ item.description ?? 'Операция по баллам' }}</strong>
              <p class="muted">{{ formatDate(item.created_at) }}</p>
            </div>
            <div class="history__delta" :class="{ 'history__delta--plus': item.points_delta > 0 }">
              {{ item.points_delta > 0 ? '+' : '' }}{{ item.points_delta }} баллов
            </div>
          </div>
        </div>
        <p v-else class="muted">Пока нет операций по баллам.</p>
      </article>
    </template>
  </section>
</template>

<style scoped>
.account-panel {
  display: grid;
  gap: 16px;
}

.account-panel__header h2 {
  font-size: 34px;
}

.account-panel__header p {
  color: var(--color-text-soft);
}

.card {
  padding: 20px;
  border-radius: 24px;
  background: rgb(255 255 255 / 90%);
  border: 1px solid #eadfcf;
  box-shadow: 0 16px 34px rgb(16 24 40 / 8%);
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.muted {
  color: var(--color-text-soft);
}

.big {
  display: block;
  margin-top: 8px;
  font-size: 34px;
}

.tiers {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}

.tier {
  display: grid;
  gap: 6px;
  border-radius: 16px;
  border: 1px solid #eee3d7;
  background: #fff8f1;
  padding: 14px;
}

.history {
  margin-top: 12px;
  display: grid;
  gap: 10px;
}

.history__row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid #eee3d7;
}

.history__delta {
  align-self: center;
  color: #ab3f0b;
  font-weight: 700;
}

.history__delta--plus {
  color: #1f7a44;
}

@media (max-width: 980px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
