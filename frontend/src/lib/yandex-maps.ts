declare global {
  interface Window {
    ymaps3?: YMaps3
  }
}

export type YMapEntity = object

export interface YMapLocationRequest {
  center?: [number, number]
  zoom?: number
  bounds?: [[number, number], [number, number]]
  duration?: number
}

export interface YMap3 {
  addChild(child: YMapEntity): YMap3
  removeChild(child: YMapEntity): YMap3
  setLocation(location: YMapLocationRequest): Promise<void>
  destroy(): void
}

export interface YMapMarkerProps {
  coordinates: [number, number]
  onClick?: () => void
}

export interface YMaps3 {
  ready: Promise<void>
  YMap: new (element: HTMLElement, props: { location: YMapLocationRequest }) => YMap3
  YMapDefaultSchemeLayer: new (props?: Record<string, unknown>) => YMapEntity
  YMapDefaultFeaturesLayer: new (props?: Record<string, unknown>) => YMapEntity
  YMapMarker: new (props: YMapMarkerProps, content: HTMLElement) => YMapEntity
}

let loadPromise: Promise<YMaps3 | null> | null = null

/**
 * Загружает Яндекс.Карты JS API 3.0. Без VITE_YANDEX_MAPS_API_KEY возвращает null —
 * вызывающий код должен скрыть карту и оставить обычный список ПВЗ.
 */
export function loadYandexMaps(): Promise<YMaps3 | null> {
  if (loadPromise) {
    return loadPromise
  }

  const apiKey = import.meta.env.VITE_YANDEX_MAPS_API_KEY as string | undefined

  if (!apiKey) {
    loadPromise = Promise.resolve(null)
    return loadPromise
  }

  if (window.ymaps3) {
    loadPromise = window.ymaps3.ready.then(() => window.ymaps3!).catch(() => null)
    return loadPromise
  }

  loadPromise = new Promise((resolve) => {
    const script = document.createElement('script')
    script.src = `https://api-maps.yandex.ru/v3/?apikey=${encodeURIComponent(apiKey)}&lang=ru_RU`
    script.async = true
    script.onload = () => {
      window.ymaps3!.ready.then(() => resolve(window.ymaps3!)).catch(() => resolve(null))
    }
    script.onerror = () => resolve(null)
    document.head.appendChild(script)
  })

  return loadPromise
}
