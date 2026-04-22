<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AuthModal from '@/components/AuthModal.vue'
import { fetchJson } from '@/lib/api'
import { toProductRoute } from '@/lib/product-route'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { useCompareStore } from '@/stores/compare'

const cartStore = useCartStore()
const { totalItems } = storeToRefs(cartStore)
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()
const compareStore = useCompareStore()
const { totalItems: wishlistTotalItems } = storeToRefs(wishlistStore)
const { totalItems: compareTotalItems } = storeToRefs(compareStore)
const { isAuthenticated, user } = storeToRefs(authStore)
const route = useRoute()
const router = useRouter()
const authModalOpen = ref(false)
const currentYear = new Date().getFullYear()
const headerSearchInput = ref((route.query.q as string | undefined) ?? '')
const searchSuggestions = ref<SearchSuggestion[]>([])
const isSearchFocused = ref(false)
let suggestDebounce: number | null = null
let suggestRequestId = 0

type SearchSuggestion = {
  id: number
  name: string
  slug: string
  price: number
  currency: string
  image_url: string | null
  category: {
    name: string
    slug: string
  } | null
}

type SearchSuggestResponse = {
  query: string
  suggestions: SearchSuggestion[]
}

onMounted(async () => {
  wishlistStore.hydrate()
  compareStore.hydrate()
  await authStore.loadMe()
  await cartStore.loadCart()
})

watch(
  () => route.query.auth,
  (value) => {
    if (value === '1' && !isAuthenticated.value) {
      authModalOpen.value = true
    }
  },
  { immediate: true },
)

async function onAuthenticated() {
  await cartStore.loadCart()
  await cartStore.loadOrderHistory()
}

async function closeAuthModal() {
  authModalOpen.value = false

  if (route.query.auth === '1') {
    const query = { ...route.query }
    delete query.auth

    await router.replace({ query })
  }
}

function normalizeSearchInput(value: string) {
  return value.trim()
}

async function submitHeaderSearch() {
  const query = normalizeSearchInput(headerSearchInput.value)

  await router.push({
    path: '/catalog',
    query: query ? { q: query } : {},
  })
}

async function loadSuggestions(query: string) {
  const normalizedQuery = query.trim()

  if (normalizedQuery.length < 2) {
    searchSuggestions.value = []
    return
  }

  const requestId = ++suggestRequestId

  try {
    const response = await fetchJson<SearchSuggestResponse>(
      `/api/search/suggest?q=${encodeURIComponent(normalizedQuery)}`,
    )

    if (requestId !== suggestRequestId) {
      return
    }

    searchSuggestions.value = response.suggestions
  } catch (error) {
    console.error(error)
    searchSuggestions.value = []
  }
}

function onSearchFocus() {
  isSearchFocused.value = true
}

function onSearchBlur() {
  window.setTimeout(() => {
    isSearchFocused.value = false
  }, 120)
}

async function openSuggestion(item: SearchSuggestion) {
  searchSuggestions.value = []
  isSearchFocused.value = false
  headerSearchInput.value = item.name
  await router.push(toProductRoute(item))
}

watch(
  () => route.fullPath,
  () => {
    headerSearchInput.value = (route.query.q as string | undefined) ?? ''
    searchSuggestions.value = []
  },
)

watch(headerSearchInput, (value) => {
  if (suggestDebounce !== null) {
    window.clearTimeout(suggestDebounce)
  }

  suggestDebounce = window.setTimeout(() => {
    void loadSuggestions(value)
  }, 220)
})
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <RouterLink to="/" class="brand">Shoria</RouterLink>
      <nav class="topbar__nav">
        <RouterLink to="/">Главная</RouterLink>
        <RouterLink to="/catalog">Каталог</RouterLink>
        <RouterLink to="/news">Новости</RouterLink>
        <RouterLink v-if="isAuthenticated" to="/account">Профиль</RouterLink>
        <button v-else type="button" class="auth-btn" @click="authModalOpen = true">Вход / Регистрация</button>
      </nav>
      <form class="topbar__search" @submit.prevent="submitHeaderSearch">
        <input
          v-model="headerSearchInput"
          type="search"
          placeholder="Поиск по каталогу"
          @focus="onSearchFocus"
          @blur="onSearchBlur"
        />
        <button type="submit" aria-label="Искать">
          <span>🔍</span>
        </button>
        <div v-if="isSearchFocused && searchSuggestions.length" class="topbar__suggestions">
          <button
            v-for="item in searchSuggestions"
            :key="item.id"
            type="button"
            class="topbar__suggestion"
            @mousedown.prevent="openSuggestion(item)"
          >
            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" />
            <span class="topbar__suggestion-body">
              <strong>{{ item.name }}</strong>
              <small>{{ item.category?.name ?? 'Sneakers' }}</small>
            </span>
          </button>
        </div>
      </form>
      <div class="topbar__actions">
        <RouterLink class="icon-pill icon-pill--wishlist" to="/wishlist" aria-label="Избранное">
          <span class="icon-pill__icon">❤</span>
          <span class="icon-pill__count">{{ wishlistTotalItems }}</span>
        </RouterLink>
        <RouterLink class="icon-pill icon-pill--compare" to="/compare" aria-label="Сравнение">
          <span class="icon-pill__icon">⚖</span>
          <span class="icon-pill__count">{{ compareTotalItems }}</span>
        </RouterLink>
        <RouterLink class="icon-pill icon-pill--cart" to="/cart" aria-label="Корзина">
          <span class="icon-pill__icon">🛒</span>
          <span class="icon-pill__count">{{ totalItems }}</span>
        </RouterLink>
        <span v-if="isAuthenticated && user" class="user-pill">{{ user.name }}</span>
      </div>
    </header>
    <RouterView />
    <footer class="footer">
      <div class="footer__grid">
        <div>
          <p class="footer__brand">Shoria Store</p>
          <p class="footer__text">Шаблонный e-commerce проект для быстрого запуска витрины.</p>
        </div>
        <div>
          <p class="footer__title">Покупателям</p>
          <nav class="footer__links">
            <RouterLink to="/catalog">Каталог</RouterLink>
            <RouterLink to="/news">Новости</RouterLink>
            <RouterLink to="/wishlist">Избранное</RouterLink>
            <RouterLink to="/cart">Корзина</RouterLink>
          </nav>
        </div>
        <div>
          <p class="footer__title">Аккаунт</p>
          <nav class="footer__links">
            <RouterLink v-if="isAuthenticated" to="/account">Профиль</RouterLink>
            <button v-else type="button" class="footer__link-btn" @click="authModalOpen = true">Вход / Регистрация</button>
            <RouterLink to="/compare">Сравнение</RouterLink>
          </nav>
        </div>
        <div>
          <p class="footer__title">Контакты</p>
          <p class="footer__text">Email: support@shoria.store</p>
          <p class="footer__text">Телефон: +7 (900) 000-00-00</p>
        </div>
      </div>
      <p class="footer__copy">© {{ currentYear }} Shoria. Все права защищены.</p>
    </footer>
    <AuthModal :open="authModalOpen" @close="closeAuthModal" @authenticated="onAuthenticated" />
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
}

.topbar {
  width: min(1240px, 92vw);
  margin: 0 auto;
  padding: 16px 0 6px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  grid-template-areas:
    'brand nav actions'
    'search search search';
  align-items: center;
  gap: 10px 14px;
}

.brand {
  grid-area: brand;
  color: #1f2233;
  text-decoration: none;
  font-family: var(--font-display);
  font-size: 42px;
  line-height: 1;
}

.topbar__nav {
  grid-area: nav;
  display: flex;
  align-items: center;
  justify-self: center;
  gap: 8px;
  flex-wrap: wrap;
}

.topbar__nav a {
  padding: 8px 12px;
  border-radius: 999px;
  color: #1f2233;
  text-decoration: none;
}

.auth-btn {
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid #e0ddd6;
  background: #fff;
  color: #1f2233;
  font: inherit;
  cursor: pointer;
}

.topbar__nav a.router-link-active {
  background: #1f2233;
  color: #fff;
}

.topbar__actions {
  grid-area: actions;
  display: flex;
  align-items: center;
  justify-self: end;
  gap: 8px;
}

.topbar__search {
  grid-area: search;
  position: relative;
  width: min(760px, 100%);
  justify-self: center;
}

.topbar__search input {
  width: 100%;
  padding: 9px 44px 9px 11px;
  border: 1px solid #d6d3cc;
  border-radius: 10px;
  background: #fff;
  font: inherit;
}

.topbar__search > button {
  position: absolute;
  top: 50%;
  right: 6px;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: #f2f4f8;
  display: grid;
  place-items: center;
  font: inherit;
  cursor: pointer;
}

.topbar__suggestions {
  position: absolute;
  z-index: 12;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  padding: 8px;
  border: 1px solid #d6d3cc;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 32px rgb(16 24 40 / 14%);
}

.topbar__suggestion {
  position: static;
  transform: none;
  height: auto;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  gap: 10px;
  width: 100%;
  padding: 8px;
  border: none;
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  overflow: hidden;
}

.topbar__suggestion:hover {
  background: #fff2e8;
}

.topbar__suggestion img {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  object-fit: cover;
}

.topbar__suggestion-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.topbar__suggestion-body strong {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar__suggestion-body small {
  color: #6f7b95;
}

.icon-pill {
  position: relative;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid #e0ddd6;
  background: #fff;
  color: #1f2233;
  text-decoration: none;
  display: grid;
  place-items: center;
}

.icon-pill__icon {
  font-size: 17px;
  line-height: 1;
}

.icon-pill__count {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  padding: 0 5px;
  display: grid;
  place-items: center;
  background: #1f2233;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}

.icon-pill--wishlist {
  background: #fff7ef;
  border: 1px solid #f0dbc3;
  color: #8a3d0b;
}

.icon-pill--compare {
  background: #eef2fb;
  border: 1px solid #d7deee;
  color: #3a4d73;
}

.user-pill {
  padding: 8px 12px;
  border-radius: 999px;
  background: #fff7ef;
  border: 1px solid #f0dbc3;
  color: #8a3d0b;
  font-size: 14px;
}

.footer {
  width: min(1240px, 92vw);
  margin: 30px auto 24px;
  padding: 22px 20px 16px;
  border: 1px solid #e9dfd0;
  border-radius: 18px;
  background: linear-gradient(145deg, #fff9f1, #fff5e9);
}

.footer__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.footer__brand {
  font-family: var(--font-display);
  font-size: 28px;
  line-height: 1;
}

.footer__title {
  font-weight: 700;
}

.footer__links {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.footer__links a {
  color: #1f2233;
  text-decoration: none;
}

.footer__link-btn {
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  color: #1f2233;
  font: inherit;
  cursor: pointer;
}

.footer__text {
  margin-top: 8px;
  color: var(--color-text-soft);
}

.footer__copy {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #ebdfcf;
  color: #6f7b95;
  font-size: 14px;
}

@media (max-width: 1100px) {
  .topbar {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'brand actions'
      'nav nav'
      'search search';
  }

  .topbar__nav {
    justify-self: start;
  }
}

@media (max-width: 760px) {
  .topbar {
    gap: 8px 10px;
  }

  .brand {
    width: auto;
  }

  .topbar__nav {
    justify-self: start;
    width: 100%;
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 4px;
  }

  .topbar__search {
    width: 100%;
    justify-self: stretch;
  }

  .footer__grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 560px) {
  .footer__grid {
    grid-template-columns: 1fr;
  }
}
</style>
