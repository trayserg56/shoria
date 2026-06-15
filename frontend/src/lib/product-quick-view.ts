import { ref, type Ref } from 'vue'

export type ProductQuickViewOpen = {
  slug: string
  categorySlug: string | null
  source?: string
}

export const productQuickViewState: Ref<ProductQuickViewOpen | null> = ref(null)

function setPreviewParam(slug: string | null): void {
  const url = new URL(location.href)
  if (slug) {
    url.searchParams.set('preview', slug)
  } else {
    url.searchParams.delete('preview')
  }
  // Меняем URL без навигации — scrollBehavior роутера не триггерится
  history.replaceState(history.state, '', url.toString())
}

export function initProductQuickView(): void {
  // оставлен для обратной совместимости вызова в App.vue
}

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
  setPreviewParam(slug)
}

export function closeProductQuickView(): void {
  productQuickViewState.value = null
  setPreviewParam(null)
}
