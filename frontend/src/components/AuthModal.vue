<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { getOAuthBackendBaseUrl } from '@/lib/api'

type Mode = 'register' | 'login' | 'forgot'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  (event: 'close'): void
  (event: 'authenticated'): void
}>()

const authStore = useAuthStore()

const vkOAuthHref = computed(() => {
  const base = getOAuthBackendBaseUrl().replace(/\/$/, '')
  return base ? `${base}/oauth/vk/redirect` : ''
})

const yandexOAuthHref = computed(() => {
  const base = getOAuthBackendBaseUrl().replace(/\/$/, '')
  return base ? `${base}/oauth/yandex/redirect` : ''
})

const mode = ref<Mode>('register')
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const regName = ref('')
const regEmail = ref('')
const regPassword = ref('')

const loginEmail = ref('')
const loginPassword = ref('')

const resetEmail = ref('')
const resetToken = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return
    }

    errorMessage.value = ''
    successMessage.value = ''
  },
)

function setMode(nextMode: Mode) {
  mode.value = nextMode
  errorMessage.value = ''
  successMessage.value = ''
}

async function submitRegister() {
  errorMessage.value = ''
  successMessage.value = ''
  isSubmitting.value = true

  try {
    await authStore.register({
      name: regName.value,
      email: regEmail.value,
      password: regPassword.value,
    })

    emit('authenticated')
    emit('close')
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось зарегистрироваться. Проверьте данные.'
  } finally {
    isSubmitting.value = false
  }
}

async function submitLogin() {
  errorMessage.value = ''
  successMessage.value = ''
  isSubmitting.value = true

  try {
    await authStore.login({
      email: loginEmail.value,
      password: loginPassword.value,
    })

    emit('authenticated')
    emit('close')
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Неверный логин или пароль.'
  } finally {
    isSubmitting.value = false
  }
}

async function submitForgotPassword() {
  errorMessage.value = ''
  successMessage.value = ''
  isSubmitting.value = true

  try {
    if (!resetToken.value.trim()) {
      const response = await authStore.forgotPassword({
        email: resetEmail.value,
      })
      successMessage.value = response.status
    } else {
      const response = await authStore.resetPassword({
        email: resetEmail.value,
        token: resetToken.value,
        password: newPassword.value,
        password_confirmation: newPasswordConfirmation.value,
      })

      successMessage.value = response.status
      resetToken.value = ''
      newPassword.value = ''
      newPasswordConfirmation.value = ''
    }
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось выполнить восстановление пароля.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click.self="emit('close')">
    <section class="modal-card">
      <button type="button" class="close-btn" @click="emit('close')">×</button>

      <header>
        <h2 v-if="mode === 'register'">Регистрация</h2>
        <h2 v-else-if="mode === 'login'">Вход</h2>
        <h2 v-else>Восстановление пароля</h2>
      </header>

      <template v-if="mode === 'register'">
        <form class="form" @submit.prevent="submitRegister">
          <label>
            Имя
            <input v-model="regName" type="text" required />
          </label>
          <label>
            Email
            <input v-model="regEmail" type="email" required />
          </label>
          <label>
            Пароль
            <input v-model="regPassword" type="password" minlength="8" required />
          </label>
          <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Создаем...' : 'Зарегистрироваться' }}
          </button>
        </form>
        <div v-if="vkOAuthHref || yandexOAuthHref" class="social-login">
          <span class="social-divider">или войти через</span>
          <div class="social-buttons">
            <a v-if="yandexOAuthHref" class="social-btn yandex-btn" :href="yandexOAuthHref">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M13.31 11.53H11.7V6.34h1.68c2.01 0 3.04.94 3.04 2.57 0 1.7-1.08 2.62-3.11 2.62ZM21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9ZM14.42 14.1c1.8-.56 2.88-2.01 2.88-3.86 0-2.56-1.76-4.24-4.76-4.24H9.7V18h2V13.1h1.42l2.74 4.9h2.22l-3.06-4.9h.4Z"/>
              </svg>
              Яндекс
            </a>
            <a v-if="vkOAuthHref" class="social-btn vk-btn" :href="vkOAuthHref">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M21.547 7h-3.29a.743.743 0 0 0-.655.392s-1.312 2.416-1.734 3.23C14.734 12.813 14 12.126 14 11.11V7.603A1.104 1.104 0 0 0 12.896 6.5h-2.474a1.982 1.982 0 0 0-1.75.813s1.255-.204 1.255 1.49c0 .42.022 1.626.04 2.64a.73.73 0 0 1-1.272.503 21.54 21.54 0 0 1-2.498-4.543.693.693 0 0 0-.63-.403h-2.99a.508.508 0 0 0-.48.685C3.005 10.175 6.918 18 11.38 18h1.878a.742.742 0 0 0 .742-.742v-1.135a.73.73 0 0 1 1.23-.53l2.247 2.112a1.09 1.09 0 0 0 .746.295h2.953c1.424 0 1.424-.988.647-1.753-.546-.538-2.518-2.617-2.518-2.617a1.02 1.02 0 0 1-.078-1.323c.637-.84 1.68-2.212 2.122-2.8.603-.804 1.697-2.507.197-2.507z"/>
              </svg>
              ВКонтакте
            </a>
          </div>
        </div>
      </template>

      <template v-else-if="mode === 'login'">
        <form class="form" @submit.prevent="submitLogin">
          <label>
            Email
            <input v-model="loginEmail" type="email" required />
          </label>
          <label>
            Пароль
            <input v-model="loginPassword" type="password" required />
          </label>
          <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Входим...' : 'Войти' }}
          </button>
        </form>

        <div v-if="vkOAuthHref || yandexOAuthHref" class="social-login">
          <span class="social-divider">или войти через</span>
          <div class="social-buttons">
            <a v-if="yandexOAuthHref" class="social-btn yandex-btn" :href="yandexOAuthHref">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M13.31 11.53H11.7V6.34h1.68c2.01 0 3.04.94 3.04 2.57 0 1.7-1.08 2.62-3.11 2.62ZM21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9ZM14.42 14.1c1.8-.56 2.88-2.01 2.88-3.86 0-2.56-1.76-4.24-4.76-4.24H9.7V18h2V13.1h1.42l2.74 4.9h2.22l-3.06-4.9h.4Z"/>
              </svg>
              Яндекс
            </a>
            <a v-if="vkOAuthHref" class="social-btn vk-btn" :href="vkOAuthHref">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M21.547 7h-3.29a.743.743 0 0 0-.655.392s-1.312 2.416-1.734 3.23C14.734 12.813 14 12.126 14 11.11V7.603A1.104 1.104 0 0 0 12.896 6.5h-2.474a1.982 1.982 0 0 0-1.75.813s1.255-.204 1.255 1.49c0 .42.022 1.626.04 2.64a.73.73 0 0 1-1.272.503 21.54 21.54 0 0 1-2.498-4.543.693.693 0 0 0-.63-.403h-2.99a.508.508 0 0 0-.48.685C3.005 10.175 6.918 18 11.38 18h1.878a.742.742 0 0 0 .742-.742v-1.135a.73.73 0 0 1 1.23-.53l2.247 2.112a1.09 1.09 0 0 0 .746.295h2.953c1.424 0 1.424-.988.647-1.753-.546-.538-2.518-2.617-2.518-2.617a1.02 1.02 0 0 1-.078-1.323c.637-.84 1.68-2.212 2.122-2.8.603-.804 1.697-2.507.197-2.507z"/>
              </svg>
              ВКонтакте
            </a>
          </div>
        </div>
      </template>

      <form v-else-if="mode === 'forgot'" class="form" @submit.prevent="submitForgotPassword">
        <label>
          Email
          <input v-model="resetEmail" type="email" required />
        </label>
        <label>
          Token (из письма, если уже есть)
          <input v-model="resetToken" type="text" />
        </label>
        <label>
          Новый пароль
          <input v-model="newPassword" type="password" minlength="8" />
        </label>
        <label>
          Повтор пароля
          <input v-model="newPasswordConfirmation" type="password" minlength="8" />
        </label>
        <button type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Отправляем...' : resetToken ? 'Сбросить пароль' : 'Получить ссылку' }}
        </button>
      </form>

      <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
      <p v-if="successMessage" class="success">{{ successMessage }}</p>

      <footer class="switches">
        <button v-if="mode !== 'register'" type="button" @click="setMode('register')">Регистрация</button>
        <button v-if="mode !== 'login'" type="button" @click="setMode('login')">Уже есть аккаунт? Войти</button>
        <button v-if="mode === 'login'" type="button" @click="setMode('forgot')">Восстановить пароль</button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgb(17 21 31 / 50%);
}

.modal-card {
  position: relative;
  width: min(460px, 100%);
  border-radius: 18px;
  background: #fff;
  padding: 18px;
  box-shadow: 0 20px 48px rgb(16 24 40 / 22%);
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
}

h2 {
  margin-bottom: 12px;
  font-size: 30px;
  line-height: 1;
  font-family: var(--font-display);
}

.form {
  display: grid;
  gap: 8px;
}

.form label {
  display: grid;
  gap: 4px;
}

.form input {
  border: 1px solid #d7d4ce;
  border-radius: 10px;
  padding: 10px;
  font: inherit;
}

.form button {
  margin-top: 6px;
  border: none;
  border-radius: 10px;
  padding: 11px;
  background: #1f2233;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.form button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.social-login {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #e8e5df;
}

.social-divider {
  display: block;
  text-align: center;
  font-size: 0.8125rem;
  color: #9b9690;
  margin-bottom: 8px;
}

.social-buttons {
  display: flex;
  gap: 8px;
}

.social-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  flex: 1;
  border-radius: 10px;
  padding: 10px 14px;
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  font-size: 0.875rem;
  transition: filter 0.15s;
}

.social-btn:hover {
  filter: brightness(1.07);
}

.yandex-btn {
  background: #fc3f1d;
}

.vk-btn {
  background: rgb(39 136 229);
}

.switches {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.switches button {
  border: 1px solid #d6d3cc;
  border-radius: 999px;
  padding: 6px 10px;
  background: #fff;
  cursor: pointer;
}

.error {
  margin-top: 10px;
  color: #a83a0f;
}

.success {
  margin-top: 10px;
  color: #185f2d;
}
</style>
