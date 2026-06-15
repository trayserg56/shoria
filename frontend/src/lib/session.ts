const sessionStorageKey = 'shoria_session_id'

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

export function getAppSessionId(): string {
  const current = window.sessionStorage.getItem(sessionStorageKey)

  if (current && UUID_RE.test(current)) {
    return current
  }

  // crypto.randomUUID() поддерживается всеми современными браузерами (Chrome 92+, FF 95+, Safari 15.4+)
  const created = crypto.randomUUID()
  window.sessionStorage.setItem(sessionStorageKey, created)

  return created
}
