import { defineStore } from 'pinia'
import { ref } from 'vue'

export type OneClickCheckoutContext = {
  productName: string
  productSlug: string
  productVariantId?: number | null
  qty: number
  productPrice?: number
  currency?: string
  source?: string
}

export const useOneClickCheckoutModalStore = defineStore('oneClickCheckoutModal', () => {
  const isOpen = ref(false)
  const context = ref<OneClickCheckoutContext | null>(null)

  function open(payload: OneClickCheckoutContext) {
    context.value = payload
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
    context.value = null
  }

  return { isOpen, context, open, close }
})
