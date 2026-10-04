// Deterministic per-id pastel color (not re-randomized on every render), so
// the same entity (e.g. a poster size) keeps the same color everywhere it's
// shown — list chips, charts, etc. Uses the golden angle for well-spread
// hues even with only a few ids.
export function pastelColorForId(id) {
  if (!id) return 'hsl(0, 0%, 85%)'
  const hue = (id * 137.508) % 360
  return `hsl(${hue}, 65%, 87%)`
}

// Passende Schriftfarbe für Text auf pastelColorForId(): derselbe Farbton, dunkel.
// Fest statt per Variable, weil der Pastell-Hintergrund auch im Dark Mode hell bleibt.
export function pastelTextColorForId(id) {
  if (!id) return 'hsl(0, 0%, 25%)'
  const hue = (id * 137.508) % 360
  return `hsl(${hue}, 40%, 25%)`
}
