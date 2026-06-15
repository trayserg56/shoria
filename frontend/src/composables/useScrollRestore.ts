import { onBeforeUnmount, onMounted } from 'vue'

const STORAGE_KEY = 'scroll_restore'

export function useScrollRestore() {
  function save() {
    const data = { path: location.pathname + location.search, y: Math.round(window.scrollY) }
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data))
  }

  function restore() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY)
      if (!raw) return
      const { path, y } = JSON.parse(raw) as { path: string; y: number }
      // Восстанавливаем только если путь совпадает
      if (path !== location.pathname + location.search) return
      // Небольшая задержка — дать странице отрендериться
      setTimeout(() => window.scrollTo({ top: y, behavior: 'instant' }), 80)
    } catch {
      // ignore
    }
  }

  onMounted(() => {
    restore()
    window.addEventListener('beforeunload', save)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', save)
  })
}
