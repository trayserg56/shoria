import { ref } from 'vue'

const STORAGE_KEY = 'shoria:color-scheme'
type Scheme = 'light' | 'dark'

const scheme = ref<Scheme>('light')

function applyScheme(value: Scheme) {
  if (value === 'dark') {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

function initColorScheme() {
  const stored = localStorage.getItem(STORAGE_KEY) as Scheme | null
  if (stored === 'light' || stored === 'dark') {
    scheme.value = stored
  } else {
    scheme.value = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  applyScheme(scheme.value)
}

function toggleColorScheme() {
  scheme.value = scheme.value === 'dark' ? 'light' : 'dark'
  localStorage.setItem(STORAGE_KEY, scheme.value)
  applyScheme(scheme.value)
}

export function useColorScheme() {
  return { scheme, toggleColorScheme, initColorScheme }
}
