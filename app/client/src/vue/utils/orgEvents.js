// Formatierungs-Helfer für Events (OrgEvent) im Events-Totem

import { formatDate } from '@utils/inventory'
import { formatEventRange, isMultiDay } from '@utils/eventDates'

const priceFormat = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' })
// Für Preisspannen: ganze Beträge ohne ",00" ("4 € – 8,50 €")
const wholePriceFormat = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
const compactPrice = value => (Number.isInteger(value) ? wholePriceFormat : priceFormat).format(value)
const MONTHS_SHORT = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']

/** 8 → "8,00 €", 0 → "kostenlos" */
export function formatPrice(value) {
  return Number(value) === 0 ? 'kostenlos' : priceFormat.format(Number(value))
}

/**
 * Zeitraum eines Events: mit eigenen Daten inkl. Uhrzeiten (z.B. "30.10.2026, 18:00 – 22:00"
 * oder "30.10. 18:00 – 01.11.26 14:00"), sonst der Zeitraum seiner Termine — mehrtägig
 * kompakt wie bei Kalender-Terminen, das Jahr am Ende ("30.09. – 30.10.26").
 */
export function formatOrgEventRange(event) {
  if (!event.DateStart) {
    if (!event.RangeEnd || event.RangeEnd === event.RangeStart) return formatDate(event.RangeStart)
    return formatEventRange({ DateStart: event.RangeStart, DateEnd: event.RangeEnd, AllDay: true }, { withYear: true })
  }
  if (isMultiDay(event)) return formatEventRange(event, { withYear: true })
  if (event.AllDay || !event.TimeStart) return formatDate(event.DateStart)
  const time = event.TimeEnd
    ? `${event.TimeStart.slice(0, 5)} – ${event.TimeEnd.slice(0, 5)}`
    : event.TimeStart.slice(0, 5)
  return `${formatDate(event.DateStart)}, ${time}`
}

/** OrgEvent.PriceMode ohne Preistabelle */
export const PRICE_MODE_LABELS = {
  Free: 'Kostenfrei',
  Donation: 'Gegen Spende',
}

/**
 * Preisangabe eines Events kurz: "Kostenfrei", "Gegen Spende", "8,00 €" (fester Preis)
 * oder "4 € – 8,50 €" (gestaffelt); ohne jede Angabe null
 */
export function formatPriceSummary(event) {
  return PRICE_MODE_LABELS[event.PriceMode] ?? formatPriceRange(event.Prices ?? [])
}

/** Der feste Preis (PriceMode "Fixed") als Text, z.B. "8,00 €"; ohne Betrag null */
export function formatFixedPrice(event) {
  return event.PriceMode === 'Fixed' ? formatPriceRange(event.Prices ?? []) : null
}

/** Preistabelle kurz zusammengefasst: "8,00 €", "Kostenfrei" oder "4 € – 8,50 €"; ohne Preise null */
export function formatPriceRange(prices = []) {
  if (!prices.length) return null
  const values = prices.map(p => Number(p.Price))
  const min = Math.min(...values)
  const max = Math.max(...values)
  if (max === 0) return PRICE_MODE_LABELS.Free
  if (min === max) return formatPrice(min)
  return `${compactPrice(min)} – ${compactPrice(max)}`
}

/**
 * Tag und Monat des Beginns fürs Datums-Badge, z.B. { day: '30', month: 'Okt' };
 * außerhalb des laufenden Jahres mit Jahr ("Okt 25")
 */
export function dateBadge(dateStr) {
  if (!dateStr) return null
  const [y, m, d] = dateStr.split('-').map(Number)
  const year = y !== new Date().getFullYear() ? ` ${String(y % 100).padStart(2, '0')}` : ''
  return { day: String(d), month: MONTHS_SHORT[m - 1] + year }
}

/** Vollständige Adresse fürs Event-Seite, eine Angabe pro Zeile (Veranstaltungsort, Straße, PLZ Ort) */
export function formatAddress(event) {
  return [event.Location, event.Street, [event.PostalCode, event.City].filter(Boolean).join(' ')]
    .filter(Boolean)
    .join('\n')
}

/** Kurzer Ort für die OrgEventCard: die Stadt, ersatzweise der Veranstaltungsort */
export function formatPlace(event) {
  return event.City || event.Location || null
}
