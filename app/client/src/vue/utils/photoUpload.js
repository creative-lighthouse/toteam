// Vorbereitung eines Fotos fürs Foto-Totem, komplett im Browser:
// EXIF lesen (Aufnahmezeit, GPS) und zwei verkleinerte JPEGs erzeugen — die Ansicht
// (max. 2560px) und die Vorschau (max. 480px). Das Original wird nie hochgeladen;
// das spart im Bunny-Speicher der Organisation meist 70–90 %.

// Bildformate, die hochgeladen werden können (der Browser verkleinert sie vorher zu JPEG).
// HEIC/HEIF können nur manche Browser lesen (Safari) — sonst gibt es beim Vorbereiten eine Meldung.
export const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/bmp', 'image/heic', 'image/heif']
export const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'heic', 'heif']
// Für <input type="file" accept="…">
export const ACCEPT_ATTRIBUTE = [...ACCEPTED_TYPES, ...ACCEPTED_EXTENSIONS.map(ext => `.${ext}`)].join(',')
export const ACCEPTED_LABEL = 'JPEG, PNG, WebP, GIF, AVIF, BMP oder HEIC'

/** Ob eine Datei ein unterstütztes Bild ist (nach MIME-Typ, ersatzweise nach Endung) */
export function isAcceptedImage(file) {
  if (file.type && ACCEPTED_TYPES.includes(file.type.toLowerCase())) return true
  const ext = (file.name || '').split('.').pop()?.toLowerCase()
  // Manche Systeme liefern bei HEIC keinen oder einen leeren MIME-Typ
  return (!file.type || file.type === 'application/octet-stream') && ACCEPTED_EXTENSIONS.includes(ext)
}

/**
 * Beim Ziehen (dragover) ist nur der MIME-Typ bekannt, nicht der Dateiname.
 * @returns {'ok'|'mixed'|'invalid'|'unknown'} unknown = Browser verrät die Typen nicht
 */
export function classifyDraggedItems(dataTransfer) {
  const items = [...(dataTransfer?.items ?? [])].filter(i => i.kind === 'file')
  if (!items.length) return 'unknown'
  const types = items.map(i => (i.type || '').toLowerCase())
  if (types.some(t => !t)) return 'unknown'
  const ok = types.filter(t => ACCEPTED_TYPES.includes(t)).length
  if (ok === types.length) return 'ok'
  return ok ? 'mixed' : 'invalid'
}

export const FULL_MAX = 2560
export const FULL_QUALITY = 0.85
export const THUMB_MAX = 480
export const THUMB_QUALITY = 0.8

function pad(n) {
  return String(n).padStart(2, '0')
}

// Ortszeit der Kamera unverändert weitergeben ("YYYY-MM-DD HH:mm:ss")
function formatLocal(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

/**
 * Aufnahmezeit und Koordinaten aus den EXIF-Daten — fehlt etwas, ist es null.
 * @returns {Promise<{ takenAt: string|null, latitude: number|null, longitude: number|null }>}
 */
export async function readPhotoMeta(file) {
  const meta = { takenAt: null, latitude: null, longitude: null }
  let exifr
  try {
    ({ default: exifr } = await import('exifr'))
  } catch {
    return withFallbackDate(meta, file)
  }

  // Zwei getrennte Abfragen: Mit `pick` liefert exifr.parse nur die genannten Felder —
  // die GPS-Rohwerte fielen dabei weg und latitude/longitude blieben leer
  try {
    const data = await exifr.parse(file, ['DateTimeOriginal', 'CreateDate'])
    const taken = data?.DateTimeOriginal || data?.CreateDate
    if (taken instanceof Date && !Number.isNaN(taken.getTime())) meta.takenAt = formatLocal(taken)
  } catch {
    // Keine oder kaputte EXIF-Daten — dann eben ohne
  }
  try {
    const gps = await exifr.gps(file)
    if (Number.isFinite(gps?.latitude) && Number.isFinite(gps?.longitude)) {
      meta.latitude = gps.latitude
      meta.longitude = gps.longitude
    }
  } catch {
    // Kein GPS-Block
  }
  return withFallbackDate(meta, file)
}

// Ohne EXIF-Aufnahmezeit das Änderungsdatum der Datei nehmen
function withFallbackDate(meta, file) {
  if (!meta.takenAt && file.lastModified) meta.takenAt = formatLocal(new Date(file.lastModified))
  return meta
}

function canvasToBlob(canvas, quality) {
  if (canvas.convertToBlob) return canvas.convertToBlob({ type: 'image/jpeg', quality })
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => (blob ? resolve(blob) : reject(new Error('Bild konnte nicht umgewandelt werden'))), 'image/jpeg', quality)
  })
}

function scaled(width, height, max) {
  const factor = Math.min(1, max / Math.max(width, height))
  return [Math.round(width * factor), Math.round(height * factor)]
}

function makeCanvas(width, height) {
  if (typeof OffscreenCanvas !== 'undefined') return new OffscreenCanvas(width, height)
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  return canvas
}

async function render(bitmap, max, quality) {
  const [width, height] = scaled(bitmap.width, bitmap.height, max)
  const canvas = makeCanvas(width, height)
  const ctx = canvas.getContext('2d')
  // Transparente PNGs bekommen einen weißen statt schwarzen Hintergrund
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, width, height)
  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(bitmap, 0, 0, width, height)
  return { blob: await canvasToBlob(canvas, quality), width, height }
}

/**
 * Ansicht und Vorschau als JPEG. createImageBitmap dreht das Bild dabei gemäß
 * EXIF-Orientierung, die fertigen Dateien enthalten selbst keine EXIF-Daten mehr.
 * @returns {Promise<{ full: Blob, thumb: Blob, width: number, height: number }>}
 */
export async function preparePhoto(file) {
  let bitmap
  try {
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  } catch {
    throw new Error('Dieses Bildformat kann der Browser nicht lesen')
  }
  try {
    const full = await render(bitmap, FULL_MAX, FULL_QUALITY)
    const thumb = await render(bitmap, THUMB_MAX, THUMB_QUALITY)
    return { full: full.blob, thumb: thumb.blob, width: full.width, height: full.height }
  } finally {
    bitmap.close?.()
  }
}

export function formatBytes(bytes) {
  if (!bytes) return '0 MB'
  if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(1).replace('.', ',')} GB`
  return `${(bytes / 1024 ** 2).toFixed(1).replace('.', ',')} MB`
}

// ── Anzeige ────────────────────────────────────────────────────────────

/** "© Steffen Kahl" — das Zeichen speichert der Server nicht mit */
export function formatCopyright(value) {
  return value ? `© ${value}` : ''
}

function parsePhotoDate(value) {
  return value ? new Date(String(value).replace(' ', 'T')) : null
}

/** "7. November 2026" */
export function formatPhotoDate(value) {
  return parsePhotoDate(value)?.toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' }) ?? ''
}

/** "07.11.26" */
export function formatPhotoDateShort(value) {
  return parsePhotoDate(value)?.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }) ?? ''
}

/** "7. November 2026, 21:30 Uhr" */
export function formatPhotoDateTime(value) {
  const date = parsePhotoDate(value)
  return date ? `${formatPhotoDate(value)}, ${date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })} Uhr` : ''
}
