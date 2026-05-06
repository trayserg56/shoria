import { ref, type Ref } from 'vue'

export type ProductQuickViewOpen = {
  slug: string
  categorySlug: string | null
  source?: string
}

export const productQuickViewState: Ref<ProductQuickViewOpen | null> = ref(null)

export function openProductQuickView(
  slug: string,
  categorySlug: string | null = null,
  source?: string,
): void {
  productQuickViewState.value = {
    slug,
    categorySlug: categorySlug?.trim() || null,
    ...(source ? { source } : {}),
  }
}

export function closeProductQuickView(): void {
  productQuickViewState.value = null
}
