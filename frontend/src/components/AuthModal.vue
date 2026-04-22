<script setup lang="ts">
import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

type Mode = 'register' | 'login' | 'forgot'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  (event: 'close'): void
  (event: 'authenticated'): void
}>()

const authStore = useAuthStore()

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

      <form v-if="mode === 'register'" class="form" @submit.prevent="submitRegister">
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

      <form v-else-if="mode === 'login'" class="form" @submit.prevent="submitLogin">
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

      <form v-else class="form" @submit.prevent="submitForgotPassword">
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
