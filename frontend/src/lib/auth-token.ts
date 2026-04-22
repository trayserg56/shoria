const tokenStorageKey = 'shoria_auth_token'

export function getAuthToken() {
  return window.localStorage.getItem(tokenStorageKey)
}

export function setAuthToken(token: string) {
  window.localStorage.setItem(tokenStorageKey, token)
}

export function clearAuthToken() {
  window.localStorage.removeItem(tokenStorageKey)
}
