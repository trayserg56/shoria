const CITY_STORAGE_KEY = 'shoria.city.v1'

type StoredCity = {
  name: string
  region: string
  district: string
  source: 'auto' | 'manual' | 'default'
}

/**
 * Город, выбранный пользователем в шапке (см. App.vue).
 * Используется как город по умолчанию для расчёта доставки.
 */
export function getStoredCityName(): string {
  try {
    const raw = localStorage.getItem(CITY_STORAGE_KEY)
    if (!raw) {
      return ''
    }

    const parsed = JSON.parse(raw) as Partial<StoredCity>
    return typeof parsed.name === 'string' ? parsed.name : ''
  } catch {
    return ''
  }
}
