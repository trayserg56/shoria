import type { Component } from 'vue'
import {
  Baby,
  Car,
  Coffee,
  Cpu,
  Dumbbell,
  Flame,
  Footprints,
  Gem,
  Headphones,
  Home,
  Laptop,
  LayoutGrid,
  Shirt,
  ShoppingBag,
  Sparkles,
  Watch,
} from 'lucide-vue-next'

/**
 * Иконка для пункта каталога в шапке по slug (без отдельного поля в API).
 * Есть явная карта + простые эвристики по подстроке.
 */
const BY_SLUG: Record<string, Component> = {
  lifestyle: Coffee,
  running: Footprints,
  premium: Gem,
  street: Flame,
  elektronika: Laptop,
  electronics: Laptop,
  odezhda: Shirt,
  clothing: Shirt,
  'dom-i-kuhnya': Home,
  'dom-i-kitchen': Home,
  dom: Home,
  krasota: Sparkles,
  beauty: Sparkles,
  sport: Dumbbell,
  fitness: Dumbbell,
  avtotovary: Car,
  avto: Car,
  'detskie-tovary': Baby,
  detskie: Baby,
  deti: Baby,
  kids: Baby,
  chasy: Watch,
  watches: Watch,
  aksessuary: ShoppingBag,
  accessories: ShoppingBag,
  audio: Headphones,
  noutbuki: Laptop,
  smartphones: Cpu,
  planshety: Laptop,
}

export function resolveCategoryMenuIcon(slug: string): Component {
  const key = slug.toLowerCase().trim().replace(/_/g, '-')
  const direct = BY_SLUG[key]
  if (direct) {
    return direct
  }

  if (key.includes('run') || key.includes('beg') || key.includes('jog')) {
    return Footprints
  }
  if (key.includes('cloth') || key.includes('odezh') || key.includes('fashion')) {
    return Shirt
  }
  if (key.includes('electron') || key.includes('elektro') || key.includes('tech') || key.includes('gadget')) {
    return Laptop
  }
  if (key.includes('dom') || key.includes('kuhn') || key.includes('kitchen') || key.includes('home')) {
    return Home
  }
  if (key.includes('beauty') || key.includes('krasot') || key.includes('cosmetic')) {
    return Sparkles
  }
  if (key.includes('sport') || key.includes('fitness') || key.includes('tren')) {
    return Dumbbell
  }
  if (key.includes('child') || key.includes('det') || key.includes('baby') || key.includes('kids')) {
    return Baby
  }
  if (key.includes('auto') || key.includes('avto') || key.includes('mashin')) {
    return Car
  }
  if (key.includes('premium') || key.includes('luxury')) {
    return Gem
  }
  if (key.includes('street') || key.includes('urban')) {
    return Flame
  }
  if (key.includes('lifestyle') || key.includes('life')) {
    return Coffee
  }
  if (key.includes('watch') || key.includes('chasy')) {
    return Watch
  }
  if (key.includes('audio') || key.includes('sound') || key.includes('headphone')) {
    return Headphones
  }
  if (key.includes('accessor') || key.includes('aksess')) {
    return ShoppingBag
  }

  return LayoutGrid
}
