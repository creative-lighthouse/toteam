// Dark Mode: gespeichert pro Gerät in localStorage ('theme' = 'dark' | 'light'),
// aktiv über die Klasse `theme--dark` am body (Farbwerte in scss/base/variables.scss).
// app.js setzt das gespeicherte Theme vor dem Mounten, damit es auf jeder Seite
// (auch Login) von Anfang an gilt und alle Komponenten den richtigen Zustand lesen.
const STORAGE_KEY = 'theme'

export function isDarkMode() {
  return document.body.classList.contains('theme--dark')
}

export function setDarkMode(enabled) {
  document.body.classList.toggle('theme--dark', enabled)
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? 'dark' : 'light')
  } catch {
    // z.B. privater Modus: gilt dann nur für diese Sitzung
  }
}

export function applyStoredTheme() {
  let stored = null
  try {
    stored = localStorage.getItem(STORAGE_KEY)
  } catch {
    // kein Zugriff auf localStorage → helles Theme
  }
  document.body.classList.toggle('theme--dark', stored === 'dark')
}
