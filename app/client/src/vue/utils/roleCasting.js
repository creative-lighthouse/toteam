/**
 * Hilfen für die Rollenzuteilung eines Events (OrgEventRoleTable, OrgEventRoleAssignModal).
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

const toMinutes = t => parseInt(t.slice(0, 2)) * 60 + parseInt(t.slice(3, 5))

/** Ob sich zwei Zeitspannen überschneiden — ganztägig (ohne Uhrzeit) überschneidet sich mit allem */
export function castingOverlaps(a, b) {
  if (!a.TimeStart || !b.TimeStart) return true
  return toMinutes(a.TimeStart) < toMinutes(b.TimeEnd) && toMinutes(b.TimeStart) < toMinutes(a.TimeEnd)
}

/** Wer am Tag zugesagt hat: Map MemberID → null (ganzer Tag) oder [{ TimeStart, TimeEnd }] */
export function castingAvailability(day) {
  return new Map((day?.Available ?? []).map(a => [a.MemberID, a.Windows]))
}

/**
 * Hinweise an Zuteilungen eines Tages: Person hat nicht (mehr) zugesagt oder
 * überschneidet sich mit einer anderen Zuteilung (nur aus Altdaten möglich).
 * @returns {Object<number, string>} Zuteilungs-ID → Hinweis
 */
export function castingWarnings(dayAssignments, availability, roleTitles) {
  const result = {}
  dayAssignments.forEach(a => {
    const notes = []
    if (a.Member && !availability.has(a.Member.ID)) notes.push('Hat für diesen Tag nicht (mehr) zugesagt')
    const others = dayAssignments.filter(b => b.ID !== a.ID && b.Member?.ID === a.Member?.ID && castingOverlaps(a, b))
    if (others.length) notes.push(`Überschneidung mit: ${[...new Set(others.map(b => roleTitles[b.RoleID]))].join(', ')}`)
    if (notes.length) result[a.ID] = notes.join(' · ')
  })
  return result
}
