import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'
import { Event } from '@models/Event'

export const useEventsStore = defineStore('events', () => {
  // State
  const events = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed - Events gruppiert nach Datum
  const eventsByDate = computed(() => {
    const grouped = {}
    events.value.forEach(event => {
      if (!grouped[event.DateStart]) {
        grouped[event.DateStart] = []
      }
      grouped[event.DateStart].push(event)
    })
    return grouped
  })

  // Computed - Zukünftige Events
  const upcomingEvents = computed(() => {
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    return events.value
      .filter(e => new Date(e.DateStart) >= today)
      .sort((a, b) => a.DateStart.localeCompare(b.DateStart))
  })

  // Actions

  /**
   * Lädt Events für einen bestimmten Monat
   * @param {number} year - Jahr (z.B. 2024)
   * @param {number} month - Monat (1-12)
   * @param {boolean} forceRefresh - Cache ignorieren
   */
  async function fetchEvents(year, month, forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/calendar')
      }

      const monthParam = `${year}-${String(month).padStart(2, '0')}`
      const response = await apiGet(`/calendar?month=${monthParam}`, !forceRefresh, 60 * 1000)

      // Konvertiere API-Daten zu Event-Instanzen
      const newEvents = (response.events || []).map(data => Event.fromAPI(data))

      // Ersetze alle Events des geladenen Monats, behalte andere Monate
      const monthPrefix = `${year}-${String(month).padStart(2, '0')}`
      const otherMonthEvents = events.value.filter(e => !e.DateStart.startsWith(monthPrefix))

      events.value = [...otherMonthEvents, ...newEvents].sort((a, b) =>
        a.DateStart.localeCompare(b.DateStart)
      )

      return newEvents
    } catch (err) {
      console.error('Failed to fetch events:', err)
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Lädt Events für mehrere Monate
   * @param {number} startYear - Start Jahr
   * @param {number} startMonth - Start Monat
   * @param {number} endYear - End Jahr
   * @param {number} endMonth - End Monat
   */
  async function fetchEventRange(startYear, startMonth, endYear, endMonth) {
    const promises = []
    let year = startYear
    let month = startMonth

    while (year < endYear || (year === endYear && month <= endMonth)) {
      promises.push(fetchEvents(year, month))

      if (month === 12) {
        month = 1
        year++
      } else {
        month++
      }
    }

    await Promise.all(promises)
  }

  /**
   * Findet einen Event anhand seiner ID
   * @param {number} eventId
   * @returns {Event|undefined}
   */
  function getEventById(eventId) {
    return events.value.find(e => e.ID === eventId)
  }

  /**
   * Ändert die Teilnahme an einem Event
   * @param {number} eventId
   * @param {string} type - 'Accept', 'Maybe', oder 'Decline'
   */
  async function changeParticipation(eventId, type) {
    try {
      const response = await apiPost(`/calendar/participation/${eventId}`, {
        response: type
      })

      // Update local state
      const event = getEventById(eventId)
      if (event) {
        event.updateUserParticipation(response.data)

        // Update in participations list
        if (event.Participations) {
          const userParticipation = event.Participations.find(p => p.IsCurrentUser)
          if (userParticipation) {
            userParticipation.Type = response.data.Type
            userParticipation.TimeStart = response.data.TimeStart
            userParticipation.TimeEnd = response.data.TimeEnd
          }
        }
      }

      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to change participation:', err)
      throw err
    }
  }

  /**
   * Ändert die Zeitangabe der Teilnahme
   * @param {number} eventId
   * @param {string} timeStart - Format: HH:mm:ss
   * @param {string} timeEnd - Format: HH:mm:ss
   */
  async function changeParticipationTime(eventId, timeStart, timeEnd) {
    try {
      const response = await apiPost(`/calendar/participationTime/${eventId}`, {
        timestart: timeStart,
        timeend: timeEnd
      })

      // Update local state
      const event = getEventById(eventId)
      if (event && event.UserParticipation) {
        event.UserParticipation.TimeStart = response.data.TimeStart
        event.UserParticipation.TimeEnd = response.data.TimeEnd

        // Update in participations list
        if (event.Participations) {
          const userParticipation = event.Participations.find(p => p.IsCurrentUser)
          if (userParticipation) {
            userParticipation.TimeStart = response.data.TimeStart
            userParticipation.TimeEnd = response.data.TimeEnd
          }
        }
      }

      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to change participation time:', err)
      throw err
    }
  }

  /**
   * Ändert die Essens-Teilnahme
   * @param {number} mealId
   * @param {string} type - Teilnahme-Typ
   */
  async function changeFoodParticipation(mealId, type) {
    try {
      const response = await apiPost(`/calendar/participationFood/${mealId}`, {
        response: type
      })

      // Update local state - find the event containing this meal
      for (const event of events.value) {
        if (event.Meals) {
          const meal = event.Meals.find(m => m.ID === mealId)
          if (meal) {
            meal.UserResponse = type
            break
          }
        }
      }

      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to change food participation:', err)
      throw err
    }
  }

  /**
   * Leert alle Events (z.B. für Logout)
   */
  function clearEvents() {
    events.value = []
    error.value = null
  }

  return {
    // State
    events,
    loading,
    error,
    // Computed
    eventsByDate,
    upcomingEvents,
    // Actions
    fetchEvents,
    fetchEventRange,
    getEventById,
    changeParticipation,
    changeParticipationTime,
    changeFoodParticipation,
    clearEvents
  }
})
