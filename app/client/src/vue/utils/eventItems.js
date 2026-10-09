import { orgHue } from '@utils/orgColor'

// Ausgeliehene Objekte eines Events auf dem Lageplan (MapDetail, MapEventItems,
// OrgEventInventory): gleich heißende Objekte bekommen dieselbe Farbe, damit man
// z.B. alle "LED-Scheinwerfer" auf einen Blick erkennt.

// Ohne Namen (sollte nicht vorkommen) die bisherige Objektfarbe, wie --ColorPlanItem
const FALLBACK_COLOR = '#e67e22'

function hslToHex(h, s, l) {
  s /= 100
  l /= 100
  const k = n => (n + h / 30) % 12
  const a = s * Math.min(l, 1 - l)
  const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))
  return '#' + [f(0), f(8), f(4)].map(x => Math.round(x * 255).toString(16).padStart(2, '0')).join('')
}

/** Markerfarbe aus dem Objektnamen — als Hex, weil der Canvas-Renderer damit rechnet */
export function itemColor(title) {
  const hue = orgHue(title)
  return hue === null ? FALLBACK_COLOR : hslToHex(hue, 65, 45)
}

/** Schwarz oder Weiß, je nachdem, was auf der Markerfarbe besser lesbar ist */
export function itemTextColor(hex) {
  const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16))
  return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#000' : '#fff'
}

/** Text im Marker: der eigene Marker-Text oder die laufende Nummer */
export function itemMarkerText(item) {
  return item.MarkerText || String(item.Number)
}

/** Inline-Style für die Nummern-Plakette in Listen — wie der Marker auf dem Plan */
export function itemBadgeStyle(item) {
  const color = itemColor(item.Title)
  return { backgroundColor: color, color: itemTextColor(color) }
}
