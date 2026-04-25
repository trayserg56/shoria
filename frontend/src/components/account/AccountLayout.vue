<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const { user } = storeToRefs(authStore)

const navItems = [
  { to: { name: 'account-overview' }, label: 'Обзор' },
  { to: { name: 'account-settings' }, label: 'Настройки профиля' },
  { to: { name: 'account-orders' }, label: 'Заказы' },
  { to: { name: 'account-loyalty' }, label: 'Лояльность' },
  { to: { name: 'account-saved' }, label: 'Избранное и сравнение' },
]

const activeSectionLabel = computed(() => {
  const matched = navItems.find((item) => route.name === item.to.name)
  return matched?.label ?? 'Кабинет'
})

onMounted(async () => {
  await authStore.loadMe()
})
</script>

<template>
  <main class="account-shell">
    <header class="account-shell__header">
      <nav class="account-shell__breadcrumbs" aria-label="Breadcrumbs">
        <RouterLink to="/">Главная</RouterLink>
        <span>/</span>
        <span>Кабинет</span>
        <span>/</span>
        <span>{{ activeSectionLabel }}</span>
      </nav>

      <div class="account-shell__hero">
        <div>
          <h1>Личный кабинет</h1>
          <p>Настройки профиля, заказы и быстрый доступ к сохранённым товарам.</p>
        </div>
        <div v-if="user" class="account-shell__identity">
          <strong>{{ user.name }}</strong>
          <span>{{ user.email }}</span>
        </div>
      </div>
    </header>

    <section class="account-shell__body">
      <aside class="account-shell__sidebar">
        <nav class="account-shell__nav" aria-label="Навигация кабинета">
          <RouterLink
            v-for="item in navItems"
            :key="item.label"
            :to="item.to"
            class="account-shell__nav-link"
            :class="{ 'account-shell__nav-link--active': route.name === item.to.name }"
          >
            {{ item.label }}
          </RouterLink>
        </nav>
      </aside>

      <div class="account-shell__content">
        <RouterView />
      </div>
    </section>
  </main>
</template>

<style scoped>
.account-shell {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 24px 0 60px;
}

.account-shell__breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  color: var(--color-text-soft);
}

.account-shell__breadcrumbs a {
  color: inherit;
}

.account-shell__hero {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  margin-top: 12px;
  align-items: end;
}

.account-shell__hero h1 {
  font-family: var(--font-display);
  font-size: clamp(42px, 7vw, 82px);
  line-height: 0.9;
}

.account-shell__hero p {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.account-shell__identity {
  min-width: 240px;
  padding: 18px 20px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.86);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.account-shell__identity strong {
  display: block;
  font-size: 22px;
}

.account-shell__identity span {
  display: block;
  margin-top: 4px;
  color: #6f7b95;
}

.account-shell__body {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 18px;
  margin-top: 22px;
  align-items: start;
}

.account-shell__sidebar {
  position: sticky;
  top: 18px;
}

.account-shell__nav {
  display: grid;
  gap: 10px;
  padding: 16px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.82);
  border: 1px solid #eadfcf;
  box-shadow: 0 16px 36px rgb(16 24 40 / 8%);
}

.account-shell__nav-link {
  display: block;
  padding: 14px 16px;
  border-radius: 18px;
  color: var(--color-text);
  text-decoration: none;
  background: #fff;
  border: 1px solid #eee3d7;
}

.account-shell__nav-link--active {
  border-color: #f26a21;
  background: #fff2e8;
  color: #c74803;
  box-shadow: 0 12px 26px rgba(242, 106, 33, 0.14);
}

@media (max-width: 980px) {
  .account-shell__hero,
  .account-shell__body {
    grid-template-columns: 1fr;
    display: grid;
  }

  .account-shell__sidebar {
    position: static;
  }

  .account-shell__nav {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }
}
</style>
