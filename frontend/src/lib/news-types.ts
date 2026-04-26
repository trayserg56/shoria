export type NewsContentType = 'news' | 'guide' | 'collection' | 'promo'

type NewsTypeMeta = {
  label: string
  seoSection: string
  articleSchemaType: 'NewsArticle' | 'Article' | 'CollectionPage'
  spotlightTitle: string
  spotlightDescription: string
  spotlightCtaLabel: string
  spotlightCtaLink: string
}

const NEWS_TYPE_META: Record<NewsContentType, NewsTypeMeta> = {
  news: {
    label: 'Новость',
    seoSection: 'Новости',
    articleSchemaType: 'NewsArticle',
    spotlightTitle: 'Что посмотреть после новости',
    spotlightDescription: 'Свежие релизы и заметные пары, которые стоит открыть сразу после чтения.',
    spotlightCtaLabel: 'Открыть каталог',
    spotlightCtaLink: '/catalog',
  },
  guide: {
    label: 'Гайд',
    seoSection: 'Гайды',
    articleSchemaType: 'Article',
    spotlightTitle: 'Товары по теме гайда',
    spotlightDescription: 'Подобрали модели, которые лучше всего подходят под советы из материала.',
    spotlightCtaLabel: 'Смотреть беговые модели',
    spotlightCtaLink: '/catalog/running',
  },
  collection: {
    label: 'Подборка',
    seoSection: 'Подборки',
    articleSchemaType: 'CollectionPage',
    spotlightTitle: 'Товары из этой стилистики',
    spotlightDescription: 'Готовые варианты для образа и покупки без долгого поиска по каталогу.',
    spotlightCtaLabel: 'Перейти в каталог',
    spotlightCtaLink: '/catalog',
  },
  promo: {
    label: 'Промо',
    seoSection: 'Промо',
    articleSchemaType: 'Article',
    spotlightTitle: 'Товары с выгодой',
    spotlightDescription: 'Актуальные позиции со скидкой и заметной разницей в цене.',
    spotlightCtaLabel: 'Открыть предложения',
    spotlightCtaLink: '/catalog',
  },
}

export const NEWS_CONTENT_TYPE_ORDER: NewsContentType[] = ['news', 'guide', 'collection', 'promo']

export function resolveNewsTypeMeta(type: string | null | undefined): NewsTypeMeta {
  if (!type) {
    return NEWS_TYPE_META.news
  }

  return NEWS_TYPE_META[type as NewsContentType] ?? NEWS_TYPE_META.news
}
