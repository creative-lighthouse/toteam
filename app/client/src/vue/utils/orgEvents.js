// Formatierungs-Helfer für Events (OrgEvent) im Events-Totem

import { formatDate, formatDateRange } from '@utils/inventory'
import { formatEventRange, isMultiDay } from '@utils/eventDates'

const priceFormat = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' })

/** 8 → "8,00 €", 0 → "kostenlos" */
export function formatPrice(value) {
  return Number(value) === 0 ? 'kostenlos' : priceFormat.format(Number(value))
}

/**
 * Zeitraum eines Events: mit eigenen Daten inkl. Uhrzeiten (z.B. "30.10.2026, 18:00 – 22:00"
 * oder "30.10. 18:00 – 01.11. 14:00"), sonst der Zeitraum seiner Termine.
 */
export function formatOrgEventRange(event) {
  if (!event.DateStart) return formatDateRange(event.RangeStart, event.RangeEnd)
  if (isMultiDay(event)) return formatEventRange(event)
  if (event.AllDay || !event.TimeStart) return formatDate(event.DateStart)
  const time = event.TimeEnd
    ? `${event.TimeStart.slice(0, 5)} – ${event.TimeEnd.slice(0, 5)}`
    : event.TimeStart.slice(0, 5)
  return `${formatDate(event.DateStart)}, ${time}`
}
