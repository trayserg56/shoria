import { ref } from 'vue'
import { requestJson } from '@/lib/api'

export interface AddressSuggestion {
  value: string
  city: string
  street: string
  lat: number | null
  lon: number | null
}

const DEBOUNCE_MS = 250

export function useAddressSuggest() {
  const suggestions = ref<AddressSuggestion[]>([])
  const loading = ref(false)

  let debounceTimer: ReturnType<typeof setTimeout> | undefined
  let requestId = 0

  function search(query: string): void {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
    }

    const trimmed = query.trim()

    if (trimmed.length < 2) {
      suggestions.value = []
      loading.value = false
      return
    }

    loading.value = true

    debounceTimer = setTimeout(async () => {
      const currentRequestId = ++requestId

      try {
        const response = await requestJson<{ data: AddressSuggestion[] }>(
          `/api/address/suggest?q=${encodeURIComponent(trimmed)}`,
        )

        if (currentRequestId === requestId) {
          suggestions.value = response.data
        }
      } catch {
        if (currentRequestId === requestId) {
          suggestions.value = []
        }
      } finally {
        if (currentRequestId === requestId) {
          loading.value = false
        }
      }
    }, DEBOUNCE_MS)
  }

  function clear(): void {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
    }
    suggestions.value = []
    loading.value = false
  }

  return { suggestions, loading, search, clear }
}
