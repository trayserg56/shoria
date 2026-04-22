export type RecentlyViewedItem = {
  id: number
  slug: string
  name: string
  price: number
  old_price: number | null
  stock: number
  currency: string
  image_url: string | null
  category: {
    name: string
    slug: string
  } | null
  viewed_at: string
}

const STORAGE_KEY = 'shoria_recently_viewed_v2'
const MAX_ITEMS = 8

export function readRecentlyViewed(): RecentlyViewedItem[] {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)

    if (!raw) {
      return []
    }

    const parsed = JSON.parse(raw)

    if (!Array.isArray(parsed)) {
      return []
    }

    return parsed.filter((item): item is RecentlyViewedItem => {
        return (
          item &&
          typeof item.id === 'number' &&
          typeof item.slug === 'string' &&
          typeof item.name === 'string' &&
          typeof item.price === 'number' &&
          typeof item.stock === 'number' &&
          typeof item.currency === 'string'
        )
      })
  } catch (error) {
    console.error(error)
    return []
  }
}

export function saveRecentlyViewed(item: Omit<RecentlyViewedItem, 'viewed_at'>): void {
  const existing = readRecentlyViewed().filter((entry) => entry.id !== item.id)
  const next: RecentlyViewedItem[] = [
    {
      ...item,
      viewed_at: new Date().toISOString(),
    },
    ...existing,
  ].slice(0, MAX_ITEMS)

  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
  } catch (error) {
    console.error(error)
  }
}
