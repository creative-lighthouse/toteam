// Formatierungs-Helfer für das Inventar-Totem

const dateFormat = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })

/** "2026-09-25" → "25.09.2026" */
export function formatDate(value) {
  if (!value) return ''
  const date = new Date(`${value.slice(0, 10)}T00:00:00`)
  return Number.isNaN(date.getTime()) ? value : dateFormat.format(date)
}

/** Zeitraum, bei gleichem Start und Ende nur ein Datum */
export function formatDateRange(start, end) {
  if (!end || start === end) return formatDate(start)
  return `${formatDate(start)} – ${formatDate(end)}`
}

export function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
}

/** Heutiges Datum als "YYYY-MM-DD" (lokale Zeit) */
export function todayIso() {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

/**
 * Nummern einer Gruppe kompakt: "KAB-01" bzw. bei fortlaufenden Nummern mit
 * gemeinsamer Basis "KAB-XX" (so viele X wie Stellen). Haben die Nummern keine
 * gemeinsame Basis, "erste … letzte".
 * @param {string[]} numbers
 */
export function numberRangeLabel(numbers) {
  const sorted = numbers.filter(Boolean).sort((a, b) => a.localeCompare(b, 'de', { numeric: true }))
  if (sorted.length <= 1) return sorted[0] || ''

  const match = sorted[0].match(/^(.*-)(\d+)$/)
  if (match) {
    const [, base, digits] = match
    const sameBase = sorted.every(n => n.startsWith(base) && /^\d+$/.test(n.slice(base.length)))
    if (sameBase) {
      const width = Math.max(...sorted.map(n => n.length - base.length), digits.length)
      return base + 'X'.repeat(width)
    }
  }
  return `${sorted[0]} … ${sorted[sorted.length - 1]}`
}

const BADGE_LABELS = {
  rented_out: 'ausgeliehen',
  defective:  'defekt',
  in_repair:  'in Reparatur',
  retired:    'ausgemustert',
}

/**
 * Status-Badges für eine Gruppe gleicher Objekte: bei Einzelobjekten wie gehabt
 * ("Ausgeliehen", "Defekt"), bei Gruppen mit Anzahl ("3 ausgeliehen", "1 defekt").
 * Einsatzbereite, nicht ausgeliehene Objekte bekommen kein Badge.
 */
export function groupStatusBadges(items) {
  const counts = {}
  for (const item of items) {
    const status = item.IsRentedOut ? 'rented_out' : item.Status
    if (status === 'available') continue
    counts[status] = (counts[status] || 0) + 1
  }
  return Object.entries(counts).map(([status, count]) => {
    const label = BADGE_LABELS[status] || status
    return {
      status,
      count,
      label: items.length > 1 ? `${count} ${label}` : label.charAt(0).toUpperCase() + label.slice(1),
    }
  })
}

const numberFormat = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 3 })
const priceFormat = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' })

/**
 * Anzeigewert eines Zusatzfelds ({ Format, Input, Unit }) — gespeicherte Werte
 * sind Strings (Zahlen mit Punkt, Datum als YYYY-MM-DD, Ja/Nein als "1"/"0").
 * Gibt '' für leere Werte zurück.
 */
export function formatFieldValue(field, raw) {
  if (raw === null || raw === undefined || raw === '') return ''
  switch (field.Input) {
    case 'number':
      if (field.Format === 'eur') return priceFormat.format(Number(raw))
      return `${numberFormat.format(Number(raw))} ${field.Unit || ''}`.trim()
    case 'date':
      return formatDate(raw)
    case 'boolean':
      return raw === '1' ? 'Ja' : 'Nein'
    default:
      return String(raw)
  }
}
