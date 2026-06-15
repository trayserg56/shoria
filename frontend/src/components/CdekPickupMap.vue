<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { loadYandexMaps, type YMap3, type YMapEntity } from '@/lib/yandex-maps'

export interface PickupMapPoint {
  code: string
  name: string
  address: string | null
  lat: number | null
  lon: number | null
}

const props = defineProps<{
  points: PickupMapPoint[]
  selectedCode: string
  focusPoint?: { lat: number; lon: number } | null
}>()

const emit = defineEmits<{
  'update:selectedCode': [code: string]
}>()

const mapContainer = ref<HTMLElement | null>(null)
const isAvailable = ref(false)

let map: YMap3 | null = null
let markers: YMapEntity[] = []

function pointsWithCoordinates(): PickupMapPoint[] {
  return props.points.filter((point) => typeof point.lat === 'number' && typeof point.lon === 'number')
}

function createMarkerElement(modifier: string, title: string): HTMLDivElement {
  const element = document.createElement('div')
  element.className = `cdek-pickup-map__marker cdek-pickup-map__marker--${modifier}`
  element.title = title
  return element
}

async function renderMap() {
  const points = pointsWithCoordinates()
  const focus = props.focusPoint ?? null

  if (points.length === 0 && !focus) {
    isAvailable.value = false
    return
  }

  const ymaps3 = await loadYandexMaps()

  if (!ymaps3) {
    isAvailable.value = false
    return
  }

  isAvailable.value = true
  await nextTick()

  if (!mapContainer.value) {
    return
  }

  const coords: Array<[number, number]> = points.map((point) => [point.lon as number, point.lat as number])

  if (focus) {
    coords.push([focus.lon, focus.lat])
  }

  if (!map) {
    map = new ymaps3.YMap(mapContainer.value, {
      location: { center: coords[0]!, zoom: 13 },
    })
    map.addChild(new ymaps3.YMapDefaultSchemeLayer())
    map.addChild(new ymaps3.YMapDefaultFeaturesLayer())
  } else {
    for (const marker of markers) {
      map.removeChild(marker)
    }
  }

  markers = []

  for (const point of points) {
    const element = createMarkerElement(
      point.code === props.selectedCode ? 'selected' : 'default',
      [point.name, point.address].filter(Boolean).join(', '),
    )

    element.addEventListener('click', () => emit('update:selectedCode', point.code))

    const marker = new ymaps3.YMapMarker(
      { coordinates: [point.lon as number, point.lat as number] },
      element,
    )

    map.addChild(marker)
    markers.push(marker)
  }

  if (focus) {
    const element = createMarkerElement('focus', 'Ваш адрес')
    const marker = new ymaps3.YMapMarker({ coordinates: [focus.lon, focus.lat] }, element)
    map.addChild(marker)
    markers.push(marker)
  }

  if (coords.length > 1) {
    const lons = coords.map((coord) => coord[0])
    const lats = coords.map((coord) => coord[1])

    await map.setLocation({
      bounds: [
        [Math.min(...lons), Math.min(...lats)],
        [Math.max(...lons), Math.max(...lats)],
      ],
      duration: 200,
    })
  } else {
    await map.setLocation({ center: coords[0]!, zoom: 13, duration: 200 })
  }
}

onMounted(renderMap)

watch(() => props.points, renderMap)

watch(() => props.focusPoint, renderMap)

watch(() => props.selectedCode, () => {
  renderMap()
})

onBeforeUnmount(() => {
  map?.destroy()
  map = null
  markers = []
})
</script>

<template>
  <div v-if="isAvailable" class="cdek-pickup-map" ref="mapContainer" />
</template>

<style scoped>
.cdek-pickup-map {
  width: 100%;
  height: 320px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.cdek-pickup-map :deep(.cdek-pickup-map__marker) {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #fff;
  background: #2563eb;
  box-shadow: 0 1px 4px rgb(0 0 0 / 30%);
  cursor: pointer;
  transform: translate(-50%, -50%);
}

.cdek-pickup-map :deep(.cdek-pickup-map__marker--selected) {
  background: #dc2626;
}

.cdek-pickup-map :deep(.cdek-pickup-map__marker--focus) {
  width: 20px;
  height: 20px;
  background: #16a34a;
  cursor: default;
}
</style>
