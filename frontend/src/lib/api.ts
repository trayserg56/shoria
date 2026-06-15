import { getAuthToken } from './auth-token'
import { computed, ref } from 'vue'

const apiPendingRequests = ref(0)
const publicGetInflight = new Map<string, Promise<unknown>>()
const publicGetCache = new Map<string, { expiresAt: number; value: unknown }>()

export const isApiLoading = computed(() => apiPendingRequests.value > 0)

function resolvePublicGetTtl(path: string): number {
  if (path.startsWith('/api/search/suggest')) {
    return 0
  }

  if (path.startsWith('/api/products?') || path === '/api/products') {
    return 5000
  }

  if (path.startsWith('/api/recommendations/personal')) {
    return 10000
  }

  if (
    path === '/api/home' ||
    path === '/api/brands' ||
    path === '/api/categories' ||
    path === '/api/navigation' ||
    path.startsWith('/api/pages') ||
    path.startsWith('/api/news')
  ) {
    return 60000
  }

  return 15000
}

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

/** Laravel origin for browser redirects (OAuth). Uses VITE_API_URL; в dev без неё — localhost:8080 под Docker/nginx. */
export function getOAuthBackendBaseUrl(): string {
  const configured = getApiBaseUrl()

  if (configured) {
    return configured
  }

  if (import.meta.env.DEV) {
    return 'http://localhost:8080'
  }

  return ''
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
      // Пытаемся вытащить читаемое сообщение из Laravel validation / JSON ответа
      let serverMessage: string | undefined
      try {
        const body = await response.clone().json() as Record<string, unknown>
        if (typeof body.message === 'string' && body.message) {
          serverMessage = body.message
        } else if (body.errors && typeof body.errors === 'object') {
          const firstField = Object.values(body.errors as Record<string, string[]>)[0]
          if (Array.isArray(firstField) && typeof firstField[0] === 'string') {
            serverMessage = firstField[0]
          }
        }
      } catch { /* не JSON — ничего */ }
      throw new Error(serverMessage ?? `Request failed: ${response.status}`)
    }

    return response.json() as Promise<T>
  } finally {
    apiPendingRequests.value = Math.max(0, apiPendingRequests.value - 1)
  }
}

export async function fetchJson<T>(path: string): Promise<T> {
  if (getAuthToken()) {
    return requestJson<T>(path)
  }

  const ttl = resolvePublicGetTtl(path)

  if (ttl <= 0) {
    return requestJson<T>(path)
  }

  const now = Date.now()
  const cached = publicGetCache.get(path)

  if (cached && cached.expiresAt > now) {
    return cached.value as T
  }

  const inflight = publicGetInflight.get(path)

  if (inflight) {
    return inflight as Promise<T>
  }

  const request = requestJson<T>(path)
    .then((value) => {
      // Не кэшируем пустые массивы — при следующем запросе нужен свежий ответ
      const isEmpty = Array.isArray(value) && (value as unknown[]).length === 0
      if (!isEmpty && value !== null && value !== undefined) {
        publicGetCache.set(path, {
          expiresAt: Date.now() + ttl,
          value,
        })
      }

      return value
    })
    .finally(() => {
      publicGetInflight.delete(path)
    })

  publicGetInflight.set(path, request)

  return request
}
