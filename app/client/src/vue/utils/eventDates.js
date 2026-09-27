/**
 * Datums-Helfer für Kalender-Termine (DateStart/DateEnd als "YYYY-MM-DD").
 * Termine ohne DateEnd gelten als eintägig.
 */

const pad = n => String(n).padStart(2, '0')

/** "YYYY-MM-DD" → lokales Date (ohne UTC-Verschiebung) */
function parseDate(dateStr) {
  const [y, m, d] = dateStr.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function toKey(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function formatTime(time) {
  return time ? time.substring(0, 5) : ''
}

export function isMultiDay(event) {
  return !!(event.DateStart && event.DateEnd && event.DateEnd > event.DateStart)
}

/** Alle Tage des Termins als "YYYY-MM-DD", von DateStart bis einschließlich DateEnd */
export function getEventDateKeys(event) {
  if (!event.DateStart) return []
  if (!isMultiDay(event)) return [event.DateStart]
  const keys = []
  const end = parseDate(event.DateEnd)
  for (const d = parseDate(event.DateStart); d <= end; d.setDate(d.getDate() + 1)) {
    keys.push(toKey(d))
  }
  return keys
}

const WEEKDAYS = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.']
// Drei Buchstaben; Intl kürzt z.B. September zu "Sept."
const MONTHS = ['Jan.', 'Feb.', 'Mär.', 'Apr.', 'Mai', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.']

/**
 * Zeitraum eines mehrtägigen Termins inkl. Uhrzeiten, z.B.
 * - kurz:    "30.10. – 01.11." bzw. "30.10. 18:00 – 01.11. 14:00"
 * - weekday: "Fr., 30.10. – So., 01.11."
 * - long:    "Fr., 30. Okt., 18:00 – So., 1. Nov. 26, 14:00"
 * Gleiches Jahr bzw. gleicher Monat stehen nur beim Enddatum ("Fr., 2. – So., 4. Okt. 26").
 * Die Kurzform nennt das Jahr nur, wenn der Termin über den Jahreswechsel geht.
 */
export function formatEventRange(event, { weekday = false, long = false } = {}) {
  const start = parseDate(event.DateStart)
  const end = parseDate(event.DateEnd || event.DateStart)
  const sameYear = start.getFullYear() === end.getFullYear()
  const sameMonth = sameYear && start.getMonth() === end.getMonth()

  const format = (date, { month, year }) => {
    const yy = pad(date.getFullYear() % 100)
    const text = long
      ? `${date.getDate()}.` + (month ? ` ${MONTHS[date.getMonth()]}` : '') + (year ? ` ${yy}` : '')
      : `${pad(date.getDate())}.` + (month ? `${pad(date.getMonth() + 1)}.` : '') + (year ? yy : '')
    return long || weekday ? `${WEEKDAYS[date.getDay()]}, ${text}` : text
  }
  const timeSep = long || weekday ? ', ' : ' '
  const withTime = (text, time) =>
    text + (!event.AllDay && time ? timeSep + formatTime(time) : '')

  const startText = format(start, { month: !sameMonth, year: !sameYear })
  const endText = format(end, { month: true, year: long || !sameYear })
  return `${withTime(startText, event.TimeStart)} – ${withTime(endText, event.TimeEnd)}`
}
