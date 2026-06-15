import { ref } from 'vue'
import { defineStore } from 'pinia'

export type StoredCity = {
  name: string
  region: string
  district: string
  source: 'auto' | 'manual' | 'default'
  address?: string
}

const CITY_STORAGE_KEY = 'shoria.city.v1'

const DEFAULT_CITY: StoredCity = {
  name: 'Москва',
  region: 'Москва',
  district: 'Центральный',
  source: 'default',
}

/**
 * Единый источник правды о текущем городе магазина.
 * App.vue синхронизирует выбор пользователя сюда; ProductView и другие
 * компоненты читают `name` реактивно вместо ручного ввода города.
 */
export const useSiteCityStore = defineStore('site-city', () => {
  const name = ref(DEFAULT_CITY.name)
  const region = ref(DEFAULT_CITY.region)
  const district = ref(DEFAULT_CITY.district)
  const source = ref<StoredCity['source']>(DEFAULT_CITY.source)
  const address = ref('')

  // Открыть модалку выбора города (слушает App.vue)
  const requestPickerOpen = ref(false)

  function load(): StoredCity | null {
    try {
      const raw = localStorage.getItem(CITY_STORAGE_KEY)
      if (!raw) {
        return null
      }

      const parsed = JSON.parse(raw) as Partial<StoredCity>
      if (!parsed?.name) {
        return null
      }

      setCity({
        name: parsed.name,
        region: parsed.region || 'Не указан',
        district: parsed.district || 'Не указан',
        source: parsed.source ?? 'manual',
        address: parsed.address || '',
      })

      return {
        name: name.value,
        region: region.value,
        district: district.value,
        source: source.value,
        address: address.value,
      }
    } catch {
      return null
    }
  }

  function setCity(city: StoredCity): void {
    name.value = city.name
    region.value = city.region
    district.value = city.district
    source.value = city.source
    address.value = city.address ?? ''
    localStorage.setItem(CITY_STORAGE_KEY, JSON.stringify({ ...city, address: address.value }))
  }

  function setAddress(value: string): void {
    address.value = value
    localStorage.setItem(CITY_STORAGE_KEY, JSON.stringify({
      name: name.value,
      region: region.value,
      district: district.value,
      source: source.value,
      address: address.value,
    }))
  }

  function openPicker(): void {
    requestPickerOpen.value = true
  }

  return { name, region, district, source, address, requestPickerOpen, load, setCity, setAddress, openPicker }
})
