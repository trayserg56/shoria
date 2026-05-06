<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const phase = ref<'working' | 'error' | 'done'>('working')
const errorText = ref('')

const oauthErrorLabels: Record<string, string> = {
  not_configured: 'Вход через ВКонтакте пока не настроен на сервере.',
  vk_error: 'ВКонтакте вернул ошибку. Попробуйте позже.',
  state: 'Сессия входа прервалась. Откройте «Войти через ВКонтакте» снова.',
}

const headline = computed(() => {
  if (phase.value === 'working') {
    return 'Завершение входа…'
  }
  if (phase.value === 'done') {
    return 'Готово'
  }

  return 'Не удалось войти'
})

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code.trim() : ''
  const oauthError =
    typeof route.query.error === 'string' ? route.query.error.trim() : ''

  if (oauthError) {
    phase.value = 'error'
    errorText.value =
      oauthErrorLabels[oauthError] ?? 'Вход через ВКонтакте не выполнен. Попробуйте ещё раз.'
    return
  }

  if (!code) {
    phase.value = 'error'
    errorText.value = 'Ссылка входа недействительна.'
    return
  }

  try {
    await authStore.exchangeOAuthCode(code)
    phase.value = 'done'
    await router.replace('/account')
  } catch {
    phase.value = 'error'
    errorText.value =
      'Одноразовая ссылка устарела или уже использована. Откройте вход снова из магазина.'
  }
})
</script>

<template>
  <main class="wrap">
    <h1>{{ headline }}</h1>
    <p v-if="phase === 'working'" class="muted">Перенаправляем в личный кабинет…</p>
    <p v-else-if="phase === 'error'" class="error">{{ errorText }}</p>
    <nav v-if="phase === 'error'" class="nav">
      <RouterLink to="/">На главную</RouterLink>
    </nav>
  </main>
</template>

<style scoped>
.wrap {
  max-width: 420px;
  margin: 48px auto;
  padding: 0 16px;
  font-family: var(--font-sans, system-ui, sans-serif);
}

h1 {
  font-family: var(--font-display, var(--font-sans, inherit));
  font-size: 1.35rem;
  margin-bottom: 10px;
}

.muted {
  color: rgb(107 114 128);
}

.error {
  color: rgb(169 61 39);
}

.nav {
  margin-top: 16px;
}

.nav a {
  color: inherit;
}
</style>
