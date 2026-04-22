type SeoTemplatePayload = {
  title: string
  description: string
  robots: string
  canonical: string
}

type NewsListSeoInput = {
  page: number
  section?: string
  type?: string | null
}

type NewsPostSeoInput = {
  slug: string
  title: string
  excerpt: string | null
  section?: string
}

type NewsArticleStructuredDataInput = NewsPostSeoInput & {
  publishedAt: string
  coverUrl: string | null
  schemaType?: 'NewsArticle' | 'Article' | 'CollectionPage'
}

type ProductStructuredDataInput = {
  slug: string
  name: string
  description: string | null
  sku: string | null
  price: number
  currency: string
  imageUrl: string | null
  categoryName: string | null
  availability: 'InStock' | 'OutOfStock'
}

type BreadcrumbItem = {
  name: string
  path: string
}

export function buildNewsListSeo(page: number): SeoTemplatePayload {
  return buildNewsListSeoWithType({ page })
}

export function buildNewsListSeoWithType(input: NewsListSeoInput): SeoTemplatePayload {
  const normalizedPage = Number.isFinite(input.page) && input.page > 0 ? Math.floor(input.page) : 1
  const section = input.section?.trim() || 'Новости'
  const sectionLower = section.toLocaleLowerCase('ru-RU')
  const baseTitle = `${section} — Shoria`
  const title = normalizedPage > 1 ? `${baseTitle} · Страница ${normalizedPage}` : baseTitle
  const description =
    normalizedPage > 1
      ? `Архив раздела «${section}» в Shoria: страница ${normalizedPage}. Актуальные ${sectionLower}, идеи и полезные материалы.`
      : `${section} Shoria: актуальные материалы, идеи и практические рекомендации.`
  const params = new URLSearchParams()

  if (input.type) {
    params.set('type', input.type)
  }

  if (normalizedPage > 1) {
    params.set('page', String(normalizedPage))
  }

  const canonical = `${window.location.origin}/news${params.size ? `?${params.toString()}` : ''}`

  return {
    title,
    description,
    robots: 'index,follow',
    canonical,
  }
}

export function buildNewsPostSeo(input: NewsPostSeoInput): SeoTemplatePayload {
  const section = input.section?.trim() || 'Новости'

  return {
    title: `${input.title} — ${section} Shoria`,
    description: input.excerpt?.trim() || 'Материал из блога Shoria о товарах, трендах и полезных подборках.',
    robots: 'index,follow',
    canonical: `${window.location.origin}/news/${input.slug}`,
  }
}

export function buildBreadcrumbStructuredData(items: BreadcrumbItem[]) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: `${window.location.origin}${item.path}`,
    })),
  }
}

export function buildNewsArticleStructuredData(input: NewsArticleStructuredDataInput) {
  return {
    '@context': 'https://schema.org',
    '@type': input.schemaType ?? 'NewsArticle',
    headline: input.title,
    description:
      input.excerpt?.trim() || 'Материал из блога Shoria о товарах, трендах и полезных подборках.',
    datePublished: input.publishedAt,
    dateModified: input.publishedAt,
    image: input.coverUrl ? [input.coverUrl] : undefined,
    mainEntityOfPage: `${window.location.origin}/news/${input.slug}`,
    author: {
      '@type': 'Organization',
      name: 'Shoria',
    },
    publisher: {
      '@type': 'Organization',
      name: 'Shoria',
    },
  }
}

export function buildProductStructuredData(input: ProductStructuredDataInput) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: input.name,
    description: input.description?.trim() || `Купить ${input.name} в магазине Shoria.`,
    sku: input.sku ?? undefined,
    image: input.imageUrl ? [input.imageUrl] : undefined,
    category: input.categoryName ?? undefined,
    offers: {
      '@type': 'Offer',
      priceCurrency: input.currency,
      price: input.price.toFixed(0),
      availability: `https://schema.org/${input.availability}`,
      url: `${window.location.origin}/product/${input.slug}`,
    },
    brand: {
      '@type': 'Brand',
      name: 'Shoria',
    },
  }
}
