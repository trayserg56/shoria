import { getAuthToken } from './auth-token'
import { computed, ref } from 'vue'

const apiPendingRequests = ref(0)

export const isApiLoading = computed(() => apiPendingRequests.value > 0)

export function getApiBaseUrl() {
  const configured = (import.meta.env.VITE_API_URL ?? '').trim().replace(/\/$/, '')

  if (!configured) {
    return ''
  }

  if (typeof window !== 'undefined') {
    const currentHost = window.location.hostname
    const isCurrentHostLocal = currentHost === 'localhost' || currentHost === '127.0.0.1'
    const isConfiguredLocalApi = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i.test(configured)

    // Safety net for production: ignore accidental localhost API target.
    if (!isCurrentHostLocal && isConfiguredLocalApi) {
      return ''
    }
  }

  return configured
}

export async function requestJson<T>(path: string, init?: RequestInit): Promise<T> {
  const token = getAuthToken()
  apiPendingRequests.value += 1

  try {
    const response = await fetch(`${getApiBaseUrl()}${path}`, {
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(init?.headers ?? {}),
      },
      ...init,
    })

    if (!response.ok) {
      throw new Error(`Request failed: ${response.status}`)
    }

    return response.json() as Promise<T>
  } finally {
    apiPendingRequests.value = Math.max(0, apiPendingRequests.value - 1)
  }
}

export async function fetchJson<T>(path: string): Promise<T> {
  return requestJson<T>(path)
}
