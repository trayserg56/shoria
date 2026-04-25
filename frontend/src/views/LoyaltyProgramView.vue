<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppSkeleton from '@/components/AppSkeleton.vue'
import { requestJson } from '@/lib/api'
import { setSeoMeta } from '@/lib/seo'

type LoyaltyInfo = {
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

const info = ref<LoyaltyInfo | null>(null)
const isLoading = ref(false)

function formatMoney(value: number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(value)
}

onMounted(async () => {
  isLoading.value = true
  try {
    info.value = await requestJson<LoyaltyInfo>('/api/loyalty/info')
    setSeoMeta({
      title: 'Программа лояльности — Shoria',
      description: 'Условия программы лояльности: уровни, начисления и списание баллов.',
      robots: 'index,follow',
      canonical: `${window.location.origin}/loyalty-program`,
    })
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <main class="page">
    <header class="hero">
      <h1>Программа лояльности</h1>
      <p>Копите баллы за покупки и оплачивайте часть следующих заказов.</p>
    </header>

    <section v-if="isLoading" class="card">
      <AppSkeleton width="220px" height="28px" />
      <AppSkeleton width="100%" height="14px" />
      <AppSkeleton width="100%" height="14px" />
    </section>

    <template v-else-if="info">
      <section class="card highlights" :class="{ 'highlights--disabled': !info.is_enabled }">
        <article>
          <span>Начисление</span>
          <strong>от {{ info.base_accrual_percent.toFixed(2) }}%</strong>
        </article>
        <article>
          <span>Макс. списание</span>
          <strong>{{ info.max_redeem_percent.toFixed(2) }}%</strong>
        </article>
        <article>
          <span>Стоимость балла</span>
          <strong>{{ formatMoney(info.point_value) }}</strong>
        </article>
      </section>

      <section class="card">
        <h2>Уровни лояльности</h2>
        <div class="tiers">
          <article v-for="tier in info.tiers" :key="tier.name" class="tier">
            <h3>{{ tier.name }}</h3>
            <p>Порог: от {{ formatMoney(tier.min_spent) }}</p>
            <p>Начисление: {{ tier.accrual_percent.toFixed(2) }}%</p>
          </article>
        </div>
      </section>

      <section class="card prose">
        <h2>Условия программы</h2>
        <div v-if="info.terms_content" v-html="info.terms_content" />
        <div v-else>
          <p>Баллы начисляются после оформления заказа и доступны для списания в следующих покупках.</p>
          <p>
            Максимальный размер списания: {{ info.max_redeem_percent.toFixed(2) }}% от суммы товаров.
            Списывать баллы можно при заказе от {{ formatMoney(info.min_order_total_for_redeem) }}.
          </p>
        </div>
      </section>
    </template>
  </main>
</template>

<style scoped>
.page {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 24px 0 64px;
  display: grid;
  gap: 18px;
}

.hero h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 90px);
  line-height: 0.9;
}

.hero p {
  margin-top: 12px;
  color: var(--color-text-soft);
}

.card {
  border-radius: 24px;
  border: 1px solid #eadfcf;
  background: rgb(255 255 255 / 90%);
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
  padding: 20px;
}

.highlights {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.highlights article {
  border-radius: 18px;
  padding: 14px;
  background: #fff8f1;
  border: 1px solid #eee3d7;
}

.highlights span {
  color: var(--color-text-soft);
}

.highlights strong {
  display: block;
  margin-top: 8px;
  font-size: 26px;
}

.highlights--disabled {
  opacity: 0.7;
}

.tiers {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-top: 12px;
}

.tier {
  border-radius: 18px;
  border: 1px solid #eee3d7;
  background: #fff;
  padding: 14px;
}

.tier h3 {
  font-size: 22px;
}

.tier p {
  margin-top: 6px;
}

.prose :deep(p + p) {
  margin-top: 8px;
}

@media (max-width: 900px) {
  .highlights {
    grid-template-columns: 1fr;
  }
}
</style>
