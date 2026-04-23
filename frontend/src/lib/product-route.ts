type ProductRouteCategory = {
  slug: string
} | null

type ProductRouteInput = {
  slug: string
  category?: ProductRouteCategory
  variant_slug?: string | null
}

export function toProductRoute(input: ProductRouteInput) {
  const categorySlug = input.category?.slug?.trim()
  const variantSlug = input.variant_slug?.trim() || undefined

  if (categorySlug) {
    return {
      name: 'product' as const,
      params: {
        categorySlug,
        slug: input.slug,
        ...(variantSlug ? { variantSlug } : {}),
      },
    }
  }

  return {
    name: 'product-legacy' as const,
    params: {
      slug: input.slug,
      ...(variantSlug ? { variantSlug } : {}),
    },
  }
}
