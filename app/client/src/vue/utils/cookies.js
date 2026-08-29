/**
 * Minimal cookie helpers for persisting small bits of UI state (filters, view
 * mode, …) across reloads. Not for anything sensitive — cookies here are plain,
 * client-readable strings.
 */

export function getCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

export function setCookie(name, value, days = 365) {
  const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString()
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`
}

export function getJSONCookie(name) {
  const raw = getCookie(name)
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function setJSONCookie(name, value, days = 365) {
  setCookie(name, JSON.stringify(value), days)
}
