<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { requestJson } from '@/lib/api'
import AddressAutocomplete from '@/components/AddressAutocomplete.vue'
import type { AddressSuggestion } from '@/composables/useAddressSuggest'
import { useSiteCityStore } from '@/stores/site-city'

const cityStore = useSiteCityStore()

type UserAddress = {
  id: number
  label: string
  city: string
  city_code: number | null
  address: string
  is_default: boolean
}

const addresses = ref<UserAddress[]>([])
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isSubmitting = ref(false)

const form = ref({
  label: '',
  city: '',
  address: '',
  is_default: false,
})

const editingId = ref<number | null>(null)

function resetForm() {
  form.value = { label: '', city: '', address: '', is_default: false }
  editingId.value = null
}

async function loadAddresses() {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await requestJson<{ data: UserAddress[] }>('/api/me/addresses')
    addresses.value = response.data
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось загрузить адреса.'
  } finally {
    isLoading.value = false
  }
}

function startEdit(address: UserAddress) {
  editingId.value = address.id
  form.value = {
    label: address.label,
    city: address.city,
    address: address.address,
    is_default: address.is_default,
  }
}

function onAddressSelect(suggestion: AddressSuggestion) {
  if (suggestion.city) {
    form.value.city = suggestion.city
  }
}

async function submitForm() {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const payload = {
    ...form.value,
    city: form.value.city || cityStore.name,
  }

  try {
    if (editingId.value != null) {
      const response = await requestJson<{ data: UserAddress[] }>(`/api/me/addresses/${editingId.value}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
      })
      addresses.value = response.data
      successMessage.value = 'Адрес обновлён.'
    } else {
      const response = await requestJson<{ data: UserAddress[] }>('/api/me/addresses', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      addresses.value = response.data
      successMessage.value = 'Адрес добавлен.'
    }

    resetForm()
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось сохранить адрес. Проверьте данные.'
  } finally {
    isSubmitting.value = false
  }
}

async function deleteAddress(id: number) {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const response = await requestJson<{ data: UserAddress[] }>(`/api/me/addresses/${id}`, {
      method: 'DELETE',
    })
    addresses.value = response.data
    if (editingId.value === id) {
      resetForm()
    }
    successMessage.value = 'Адрес удалён.'
  } catch (error) {
    console.error(error)
    errorMessage.value = 'Не удалось удалить адрес.'
  }
}

onMounted(loadAddresses)
</script>

<template>
  <section class="addresses-page">
    <div class="addresses-page__header">
      <h2>Адреса доставки</h2>
      <p>Сохраняйте адреса с пометками — например «Дом» или «Работа», чтобы быстрее оформлять заказы.</p>
    </div>

    <p v-if="errorMessage" class="message message--error">{{ errorMessage }}</p>
    <p v-if="successMessage" class="message message--success">{{ successMessage }}</p>

    <section class="addresses-grid">
      <form class="address-card" @submit.prevent="submitForm">
        <div class="address-card__head">
          <h3>{{ editingId != null ? 'Редактировать адрес' : 'Новый адрес' }}</h3>
        </div>

        <label class="field">
          <span>Метка</span>
          <input v-model="form.label" type="text" placeholder="Дом, Работа..." required maxlength="60" />
        </label>

        <label class="field">
          <span>Адрес</span>
          <AddressAutocomplete
            v-model="form.address"
            input-class="field__input"
            placeholder="Например: Транспортная 1/1, город Оренбург"
            @select="onAddressSelect"
          />
          <small v-if="form.city" class="field__hint">Город: {{ form.city }}</small>
        </label>

        <label class="field field--checkbox">
          <input v-model="form.is_default" type="checkbox" />
          <span>Использовать по умолчанию</span>
        </label>

        <div class="address-card__actions">
          <button type="submit" class="primary-btn" :disabled="isSubmitting">
            {{ isSubmitting ? 'Сохраняем...' : editingId != null ? 'Сохранить' : 'Добавить адрес' }}
          </button>
          <button v-if="editingId != null" type="button" class="secondary-btn" @click="resetForm">
            Отмена
          </button>
        </div>
      </form>

      <section class="address-card">
        <div class="address-card__head">
          <h3>Сохранённые адреса</h3>
        </div>

        <p v-if="isLoading">Загрузка...</p>
        <p v-else-if="addresses.length === 0">Адресов пока нет.</p>

        <ul v-else class="address-list">
          <li v-for="address in addresses" :key="address.id" class="address-list__item">
            <div>
              <strong>{{ address.label }}</strong>
              <span v-if="address.is_default" class="address-list__badge">по умолчанию</span>
              <p>{{ address.city }}, {{ address.address }}</p>
            </div>
            <div class="address-list__actions">
              <button type="button" class="secondary-btn" @click="startEdit(address)">Изменить</button>
              <button type="button" class="danger-btn" @click="deleteAddress(address.id)">Удалить</button>
            </div>
          </li>
        </ul>
      </section>
    </section>
  </section>
</template>

<style scoped>
.addresses-page,
.addresses-grid {
  display: grid;
  gap: 18px;
}

.addresses-page__header h2 {
  font-size: 34px;
}

.addresses-page__header p {
  color: var(--color-text-soft);
}

.message {
  padding: 14px 16px;
  border-radius: 18px;
}

.message--error {
  background: #fff1ec;
  color: #a83a0f;
}

.message--success {
  background: #edf9ef;
  color: #185f2d;
}

.addresses-grid {
  grid-template-columns: 1fr 1.2fr;
}

.address-card {
  padding: 22px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid #eadfcf;
  box-shadow: 0 18px 40px rgb(16 24 40 / 8%);
}

.address-card__head h3 {
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

.field input[type='text'],
.field :deep(.field__input) {
  min-height: 54px;
  padding: 0 16px;
  border-radius: 16px;
  border: 1px solid #d8d4cf;
  background: #fff;
  font: inherit;
}

.field__hint {
  color: var(--color-text-soft);
}

.field--checkbox {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
}

.field--checkbox input {
  width: 18px;
  height: 18px;
}

.address-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
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

.address-list {
  display: grid;
  gap: 12px;
  margin-top: 14px;
  list-style: none;
  padding: 0;
}

.address-list__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 18px;
  border: 1px solid #eee3d7;
  background: #fff;
}

.address-list__badge {
  margin-left: 8px;
  font-size: 12px;
  padding: 2px 10px;
  border-radius: 999px;
  background: #fff2e8;
  color: #c74803;
}

.address-list__item p {
  margin-top: 4px;
  color: var(--color-text-soft);
}

.address-list__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

@media (max-width: 980px) {
  .addresses-grid {
    grid-template-columns: 1fr;
  }

  .address-list__item {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
