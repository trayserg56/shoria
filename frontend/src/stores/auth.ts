import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { clearAuthToken, getAuthToken, setAuthToken } from '@/lib/auth-token'
import { requestJson } from '@/lib/api'

export type User = {
  id: number
  name: string
  email: string
  phone: string | null
  role: 'admin' | 'content_manager' | 'customer'
  email_verified_at: string | null
  loyalty: {
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
}

type AuthResponse = {
  token: string
  user: User
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => !!user.value && !!getAuthToken())

  async function register(payload: { name: string; email: string; password: string }) {
    const response = await requestJson<AuthResponse>('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    setAuthToken(response.token)
    user.value = response.user
  }

  async function login(payload: { email: string; password: string }) {
    const response = await requestJson<AuthResponse>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    setAuthToken(response.token)
    user.value = response.user
  }

  async function loadMe() {
    if (!getAuthToken()) {
      user.value = null
      return
    }

    isLoading.value = true

    try {
      const response = await requestJson<{ user: User }>('/api/auth/me')
      user.value = response.user
    } catch (error) {
      console.error(error)
      clearAuthToken()
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await requestJson<{ ok: boolean }>('/api/auth/logout', {
        method: 'POST',
      })
    } catch (error) {
      console.error(error)
    } finally {
      clearAuthToken()
      user.value = null
    }
  }

  async function forgotPassword(payload: { email: string }) {
    return requestJson<{ ok: boolean; status: string }>('/api/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  }

  async function resetPassword(payload: {
    email: string
    token: string
    password: string
    password_confirmation: string
  }) {
    return requestJson<{ ok: boolean; status: string }>('/api/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  }

  async function resendVerificationEmail() {
    return requestJson<{ ok: boolean; status: string }>('/api/auth/email/verification-notification', {
      method: 'POST',
    })
  }

  async function updateProfile(payload: { name: string; email: string; phone: string }) {
    const response = await requestJson<{ ok: boolean; status: string; user: User }>('/api/auth/profile', {
      method: 'PATCH',
      body: JSON.stringify(payload),
    })

    user.value = response.user

    return response
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    register,
    login,
    loadMe,
    logout,
    forgotPassword,
    resetPassword,
    resendVerificationEmail,
    updateProfile,
  }
})
