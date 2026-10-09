// Farbton aus einem Hash des Organisationsnamens (FNV-1a): gleicher Name → immer
// derselbe Farbton, ohne etwas speichern zu müssen. Genutzt vom Logo-Platzhalter
// (AppOrgLogo) und als Akzentfarbe für Organisationen ohne Logo (EventCard).
export function orgHue(name) {
  const value = (name || '').trim().toLowerCase()
  if (!value) return null
  let hash = 0x811c9dc5
  for (let i = 0; i < value.length; i++) {
    hash ^= value.charCodeAt(i)
    hash = Math.imul(hash, 0x01000193)
  }
  return (hash >>> 0) % 360
}

// Akzentfarbe einer Organisation: die aus dem Logo berechnete Farbe (API-Feld Color),
// ohne Logo derselbe Farbton wie der Platzhalter, nur kräftiger, damit er als Streifen
// auf hellen und dunklen Karten sichtbar ist. null = Logo ohne nennenswerte Farbe.
export function orgAccentColor(org) {
  if (!org) return null
  if (org.Color) return org.Color
  if (org.LogoURL) return null
  const hue = orgHue(org.Title)
  return hue === null ? null : `hsl(${hue} 55% 50%)`
}
