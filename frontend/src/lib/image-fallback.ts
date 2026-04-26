export const IMAGE_FALLBACK_SRC = '/images/product-fallback.svg'

export function resolveImageSrc(src?: string | null): string {
  const value = src?.trim()
  return value ? value : IMAGE_FALLBACK_SRC
}

export function applyImageFallback(event: Event): void {
  const target = event.target

  if (!(target instanceof HTMLImageElement)) {
    return
  }

  if (target.dataset.fallbackApplied === '1') {
    return
  }

  target.dataset.fallbackApplied = '1'
  target.src = IMAGE_FALLBACK_SRC
}

export function resolveBackgroundImage(src?: string | null): string {
  const value = src?.trim()

  if (!value) {
    return `url("${IMAGE_FALLBACK_SRC}")`
  }

  return `url("${value}"), url("${IMAGE_FALLBACK_SRC}")`
}
