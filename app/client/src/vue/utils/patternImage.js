// Platzhalterbilder aus einem Text (z.B. Event-Titel): gleicher Text ergibt immer
// dasselbe Muster, verschiedene Texte verschiedene Farben und Formen.

/** Text → 32-Bit-Zahl (FNV-1a) als Startwert für den Zufallsgenerator */
function hashString(text) {
  let hash = 0x811c9dc5
  for (let i = 0; i < text.length; i++) {
    hash ^= text.charCodeAt(i)
    hash = Math.imul(hash, 0x01000193)
  }
  return hash >>> 0
}

/** Kleiner, reproduzierbarer Zufallsgenerator (mulberry32) — liefert Zahlen in [0, 1) */
function createRandom(seed) {
  let state = seed
  return () => {
    state = (state + 0x6d2b79f5) | 0
    let t = Math.imul(state ^ (state >>> 15), 1 | state)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

const normalizeHue = h => ((h % 360) + 360) % 360
const hsl = (h, s, l) => `hsl(${Math.round(normalizeHue(h))} ${s}% ${l}%)`

// Gelb-/Grüntöne wirken bei gleicher HSL-Sättigung und -Helligkeit viel greller als
// Blau/Rot — dort etwas zurücknehmen (0 = keine Dämpfung, 1 = volle Dämpfung)
const neonDamping = h => Math.max(0, 1 - Math.abs(normalizeHue(h) - 110) / 60)

/**
 * Muster für eine Fläche von width × height: ein dunklerer Verlauf als Grund und
 * einige helle, später weichgezeichnete Farbkreise in benachbarten Farbtönen.
 */
function blobColor(h, saturation, lightness) {
  const damping = neonDamping(h)
  return hsl(h, Math.round(70 + saturation * 20 - damping * 25), Math.round(52 + lightness * 14 - damping * 12))
}

export function generatePattern(text, { width = 160, height = 90, blobs = 5 } = {}) {
  const random = createRandom(hashString(String(text ?? '')))
  const between = (min, max) => min + random() * (max - min)

  const hue = between(0, 360)
  // Eng verwandte Farbtöne wirken harmonisch (weit entfernte mischen sich im
  // Weichzeichner zu Braun/Grau); ein kleiner Akzent in größerem Abstand belebt
  const hues = [hue, hue + 25, hue + 50]
  const accent = hue + (random() < 0.5 ? -70 : 110)

  return {
    width,
    height,
    background: {
      from: hsl(hue, 55, 20),
      to: hsl(hue + 30, 60, 34),
      angle: between(0, 360),
    },
    blobs: Array.from({ length: blobs }, (_, i) => {
      const isAccent = i === blobs - 1
      return {
        // Über die ganze Fläche verteilt, eher zur Mitte hin
        cx: between(0, 1) * width,
        cy: between(0.1, 0.9) * height,
        r: (isAccent ? between(0.1, 0.18) : between(0.16, 0.32)) * width,
        color: blobColor(isAccent ? accent : hues[i % 3], between(0, 1), between(0, 1)),
        opacity: between(0.75, 1),
      }
    }),
  }
}
