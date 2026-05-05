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
      publicGetCache.set(path, {
        expiresAt: Date.now() + ttl,
        value,
      })

      return value
    })
    .finally(() => {
      publicGetInflight.delete(path)
    })

  publicGetInflight.set(path, request)

  return request
}
