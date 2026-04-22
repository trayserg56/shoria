const sessionStorageKey = 'shoria_session_id'

export function getAppSessionId() {
  const current = window.sessionStorage.getItem(sessionStorageKey)

  if (current) {
    return current
  }

  const created = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
  window.sessionStorage.setItem(sessionStorageKey, created)

  return created
}
