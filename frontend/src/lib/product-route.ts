type ProductRouteCategory = {
  slug: string
} | null

type ProductRouteInput = {
  slug: string
  category?: ProductRouteCategory
}

export function toProductRoute(input: ProductRouteInput) {
  const categorySlug = input.category?.slug?.trim()

  if (categorySlug) {
    return {
      name: 'product' as const,
      params: {
        categorySlug,
        slug: input.slug,
      },
    }
  }

  return {
    name: 'product-legacy' as const,
    params: {
      slug: input.slug,
    },
  }
}
