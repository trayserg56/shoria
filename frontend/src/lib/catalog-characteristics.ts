import type { RouteLocationRaw } from 'vue-router'

/** Токен фильтра в URL каталога, совпадает с parseCharacteristicFilters в API (разделитель `::`). */
export function characteristicCatalogToken(name: string, value: string): string | null {
  const n = name.trim()
  const v = value.trim()

  if (!n || !v) {
    return null
  }

  return `${n}::${v}`
}

/** Переход в каталог с фильтром по паре название характеристики / значение. */
export function characteristicsCatalogRoute(
  name: string,
  value: string,
  opts?: { categorySlug?: string | null },
): RouteLocationRaw {
  const token = characteristicCatalogToken(name, value)

  if (!token) {
    return { path: '/catalog' }
  }

  const query: Record<string, string> = { characteristics: token }
  const category = opts?.categorySlug?.trim()

  if (category) {
    query.category = category
  }

  return { path: '/catalog', query }
}
