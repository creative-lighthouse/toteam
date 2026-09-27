// Gemeinsame Helfer fürs Essens-Totem (Übersicht und Essensplaner)

function parseDay(dateStr) {
  return new Date(`${dateStr}T00:00:00`)
}

/** Überschrift eines Tages: "Heute", "Morgen" oder "Samstag, 30.09.2026" */
export function formatMealDay(dateStr) {
  if (!dateStr) return ''
  const date = parseDay(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const diff = Math.round((date - today) / 86400000)
  if (diff === 0) return 'Heute'
  if (diff === 1) return 'Morgen'
  if (diff === -1) return 'Gestern'
  return new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
}

/** Kurzform, z.B. für Auswahllisten: "Sa., 30.09." */
export function formatMealDayShort(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }).format(parseDay(dateStr))
}

/** Mahlzeiten (mit `date`) nach Tag gruppieren, Reihenfolge bleibt erhalten */
export function groupMealsByDay(meals) {
  const groups = []
  for (const meal of meals) {
    const last = groups[groups.length - 1]
    if (last && last.date === meal.date) {
      last.meals.push(meal)
    } else {
      groups.push({ date: meal.date, meals: [meal] })
    }
  }
  return groups
}

export const PREFERENCE_LABELS = { Vegetarian: 'Vegetarisch', Vegan: 'Vegan' }
