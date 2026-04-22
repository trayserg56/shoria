import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export type CompareItem = {
  id: number
  slug: string
  name: string
  price: number
  old_price: number | null
  currency: string
  image_url: string | null
  stock: number
  category: {
    name: string
    slug: string
  } | null
  tags?: Array<{
    code: string
    label: string
  }>
}

const STORAGE_KEY = 'shoria_compare_v1'
const MAX_ITEMS = 4

export const useCompareStore = defineStore('compare', () => {
  const items = ref<CompareItem[]>([])
  const isHydrated = ref(false)

  function hydrate() {
    if (isHydrated.value) {
      return
    }

    try {
      const raw = window.localStorage.getItem(STORAGE_KEY)

      if (!raw) {
        isHydrated.value = true
        return
      }

      const parsed = JSON.parse(raw)

      if (Array.isArray(parsed)) {
        items.value = parsed.filter((item): item is CompareItem => {
          return (
            item &&
            typeof item.id === 'number' &&
            typeof item.slug === 'string' &&
            typeof item.name === 'string' &&
            typeof item.price === 'number' &&
            typeof item.currency === 'string'
          )
        })
      }
    } catch (error) {
      console.error(error)
      items.value = []
    } finally {
      isHydrated.value = true
    }
  }

  function persist() {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value))
    } catch (error) {
      console.error(error)
    }
  }

  function has(productId: number) {
    return items.value.some((item) => item.id === productId)
  }

  function add(item: CompareItem) {
    items.value = [item, ...items.value.filter((entry) => entry.id !== item.id)].slice(0, MAX_ITEMS)
    persist()
  }

  function remove(productId: number) {
    items.value = items.value.filter((item) => item.id !== productId)
    persist()
  }

  function clear() {
    items.value = []
    persist()
  }

  function toggle(item: CompareItem) {
    if (has(item.id)) {
      remove(item.id)
      return { active: false, overflow: false }
    }

    const willOverflow = items.value.length >= MAX_ITEMS
    add(item)

    return { active: true, overflow: willOverflow }
  }

  const totalItems = computed(() => items.value.length)

  return {
    items,
    totalItems,
    isHydrated,
    hydrate,
    has,
    add,
    remove,
    clear,
    toggle,
  }
})
