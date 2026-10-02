/**
 * Hilfen für die Rollenzuteilung eines Events (OrgEventRoleCasting, OrgEventRoleTable).
 */

/**
 * Auswählbare Tage: die Tage der Termine mit Rollenplan plus alle Tage, an denen
 * schon jemand eingeteilt ist (z.B. wenn der Rollenplan am Termin später
 * ausgeschaltet wurde — die Zuteilungen sollen nicht unsichtbar werden).
 * @returns {{ Date: string, Appointments: Array, label: string }[]}
 */
export function castingDayList(castingDays = [], assignments = []) {
  const map = new Map(castingDays.map(d => [d.Date, d]))
  assignments.forEach(a => {
    if (!map.has(a.Date)) map.set(a.Date, { Date: a.Date, Appointments: [] })
  })
  return [...map.values()]
    .sort((a, b) => a.Date.localeCompare(b.Date))
    .map(d => ({ ...d, label: [...new Set(d.Appointments.map(a => a.Title))].join(', ') }))
}

const dayFormat = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' })

/** "Fr., 30.10." */
export function formatCastingDay(date) {
  return dayFormat.format(new Date(`${date}T00:00:00Z`))
}

/** "18:00 – 20:00" bzw. "ganztägig" */
export function formatCastingSpan(assignment) {
  return assignment.TimeStart ? `${assignment.TimeStart} – ${assignment.TimeEnd}` : 'ganztägig'
}
