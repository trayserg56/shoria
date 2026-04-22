<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'

const authStore = useAuthStore()
const cartStore = useCartStore()
const route = useRoute()
const router = useRouter()

const { user } = storeToRefs(authStore)

const form = ref({
  name: '',
  email: '',
  phone: '',
})
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const verificationMessage = computed(() => {
  if (route.query.verified !== '1' && route.query.verified !== '0') {
    return ''
  }

  if (route.query.verified === '1') {
    return route.query.reason === 'already_verified'
      ? 'Email уже был подтвержден.'
      : 'Email успешно подтвержден.'
  }

  return 'Не удалось подтвердить email. Попробуйте запросить новое письмо.'
})

function syncForm() {
  form.value = {
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    phone: user.value?.phone ?? '',
  }
}

async function submitProfile() {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await authStore.updateProfile(form.value)
    successMessage.value = response.status
    syncForm()
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось обновить профиль. Проверьте данные и попробуйте еще раз.'
  } finally {
    isSubmitting.value = false
  }
}

async function resendVerificationEmail() {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await authStore.resendVerificationEmail()
    successMessage.value = response.status
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось отправить письмо подтверждения.'
  }
}

async function refreshAccountStatus() {
  errorMessage.value = ''

  try {
    await authStore.loadMe()
    syncForm()
    successMessage.value = 'Статус аккаунта обновлен.'
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось обновить статус аккаунта.'
  }
}

async function submitLogout() {
  await authStore.logout()
  await cartStore.loadCart()
  await cartStore.loadOrderHistory()
  void router.push('/')
}

onMounted(async () => {
  await authStore.loadMe()
  syncForm()
})
</script>

<template>
  <section class="settings-page">
    <div class="settings-page__header">
      <div>
        <h2>Настройки профиля</h2>
        <p>Изменяйте основные данные аккаунта и управляйте подтверждением почты.</p>
      </div>
    </div>

    <p v-if="verificationMessage" class="message message--info">{{ verificationMessage }}</p>
    <p v-if="errorMessage" class="message message--error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="message message--success">{{ successMessage }}</p>

    <section class="settings-grid">
      <form class="settings-card" @submit.prevent="submitProfile">
        <div class="settings-card__head">
          <h3>Контактные данные</h3>
          <p>Имя, телефон и email для заказов и уведомлений.</p>
        </div>

        <label class="field">
          <span>Имя</span>
          <input v-model="form.name" type="text" autocomplete="name" required />
        </label>

        <label class="field">
          <span>Телефон</span>
          <input v-model="form.phone" type="tel" autocomplete="tel" placeholder="+7 (900) 000-00-00" />
        </label>

        <label class="field">
          <span>Email</span>
          <input v-model="form.email" type="email" autocomplete="email" required />
        </label>

        <button type="submit" class="primary-btn" :disabled="isSubmitting">
          {{ isSubmitting ? 'Сохраняем...' : 'Сохранить изменения' }}
        </button>
      </form>

      <section class="settings-card">
        <div class="settings-card__head">
          <h3>Подтверждение email</h3>
          <p>Текущий статус безопасности и доступ к повторной отправке письма.</p>
        </div>

        <div class="verification-box" :class="user?.email_verified_at ? 'verification-box--ok' : 'verification-box--warn'">
          <strong>{{ user?.email_verified_at ? 'Email подтвержден' : 'Email не подтвержден' }}</strong>
          <p v-if="user?.email_verified_at">Аккаунт готов для получения уведомлений и безопасного восстановления доступа.</p>
          <p v-else>Если вы сменили email или не открывали письмо ранее, можно отправить ссылку ещё раз.</p>
        </div>

        <div class="settings-actions">
          <button
            v-if="!user?.email_verified_at"
            type="button"
            class="secondary-btn"
            @click="resendVerificationEmail"
          >
            Отправить письмо подтверждения
          </button>
          <button type="button" class="secondary-btn" @click="refreshAccountStatus">Обновить статус</button>
        </div>
      </section>

      <section class="settings-card settings-card--danger">
        <div class="settings-card__head">
          <h3>Сессия</h3>
          <p>Выход из аккаунта на этом устройстве.</p>
        </div>

        <button type="button" class="danger-btn" @click="submitLogout">Выйти из аккаунта</button>
      </section>
    </section>
  </section>
</template>

<style scoped>
.settings-page,
.settings-grid {
  display: grid;
  gap: 18px;
}

.settings-page__header h2 {
  font-size: 34px;
}

.settings-page__header p,
.settings-card__head p {
  color: var(--color-text-soft);
}

.message {
  padding: 14px 16px;
  border-radius: 18px;
}

.message--info {
  background: #eef4ff;
  color: #37527f;
}

.message--error {
  background: #fff1ec;
  color: #a83a0f;
}

.message--success {
  background: #edf9ef;
  color: #185f2d;
}

.settings-grid {
  grid-template-columns: 1.2fr 1fr;
}

.settings-card {
  padding: 22px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.settings-card--danger {
  grid-column: 1 / -1;
}

.settings-card__head h3 {
  font-size: 26px;
}

.field {
  display: grid;
  gap: 8px;
  margin-top: 14px;
}

.field span {
  font-weight: 700;
}

.field input {
  min-height: 54px;
  padding: 0 16px;
  border-radius: 16px;
  border: 1px solid #d8d4cf;
  background: #fff;
  font: inherit;
}

.primary-btn,
.secondary-btn,
.danger-btn {
  min-height: 48px;
  padding: 0 18px;
  border-radius: 16px;
  border: 1px solid transparent;
  font: inherit;
  cursor: pointer;
}

.primary-btn {
  margin-top: 18px;
  background: #23263a;
  color: #fff;
}

.secondary-btn {
  background: #fff;
  border-color: #d8d4cf;
}

.danger-btn {
  background: #fff2e8;
  color: #c74803;
  border-color: #f6c8ad;
}

.verification-box {
  margin-top: 14px;
  padding: 16px 18px;
  border-radius: 18px;
}

.verification-box--ok {
  background: #edf9ef;
  color: #185f2d;
}

.verification-box--warn {
  background: #fff2e8;
  color: #a83a0f;
}

.verification-box p {
  margin-top: 6px;
}

.settings-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 16px;
}

@media (max-width: 980px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
}
</style>
