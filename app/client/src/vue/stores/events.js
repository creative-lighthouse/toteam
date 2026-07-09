import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'
import { Event } from '@models/Event'
import { useAuthStore } from '@stores/auth'

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
          const existing = event.Participations.find(p => p.IsCurrentUser)
          if (existing) {
            existing.Type = response.data.Type
            existing.TimeStart = response.data.TimeStart
            existing.TimeEnd = response.data.TimeEnd
            existing.CustomTimeframe = response.data.CustomTimeframe ?? false
          } else {
            // First RSVP — add a new entry so avatars + counts update immediately
            const authStore = useAuthStore()
            const u = authStore.user
            event.Participations.push({
              ID: response.data.ID,
              MemberID: u?.ID ?? null,
              MemberName: u ? `${u.FirstName} ${u.Surname}` : '',
              ProfileImageURL: u?.ProfileImage?.URL ?? u?.Gravatar ?? null,
              Type: response.data.Type,
              TimeStart: response.data.TimeStart,
              TimeEnd: response.data.TimeEnd,
              CustomTimeframe: response.data.CustomTimeframe ?? false,
              IsCurrentUser: true,
            })
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
        timestart: timeStart ?? null,
        timeend: timeEnd ?? null,
      })

      // Update local state
      const event = getEventById(eventId)
      if (event && event.UserParticipation) {
        event.UserParticipation.TimeStart = response.data.TimeStart
        event.UserParticipation.TimeEnd = response.data.TimeEnd
        event.UserParticipation.CustomTimeframe = response.data.CustomTimeframe ?? false

        // Update in participations list
        if (event.Participations) {
          const userParticipation = event.Participations.find(p => p.IsCurrentUser)
          if (userParticipation) {
            userParticipation.TimeStart = response.data.TimeStart
            userParticipation.TimeEnd = response.data.TimeEnd
            userParticipation.CustomTimeframe = response.data.CustomTimeframe ?? false
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
   * Ändert die Notiz der Teilnahme
   * @param {number} eventId
   * @param {string|null} notes
   */
  async function changeParticipationNotes(eventId, notes) {
    try {
      const response = await apiPost(`/calendar/participationNotes/${eventId}`, {
        notes: notes ?? null,
      })

      // Update local state
      const event = getEventById(eventId)
      if (event && event.UserParticipation) {
        event.UserParticipation.Notes = response.data.Notes

        if (event.Participations) {
          const userParticipation = event.Participations.find(p => p.IsCurrentUser)
          if (userParticipation) {
            userParticipation.Notes = response.data.Notes
          }
        }
      }

      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to change participation notes:', err)
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
   * Fügt eine Mahlzeit zu einem Termin hinzu (nur Moderatoren/Admins)
   * @param {number} appointmentId
   * @param {string} title
   * @param {string} time - Format: HH:mm
   */
  async function updateMeal(mealId, title, time, acceptsContributions) {
    try {
      const response = await apiPut(`/calendar/meal/${mealId}`, { title, time, acceptsContributions })

      for (const event of events.value) {
        if (event.Meals) {
          const meal = event.Meals.find(m => m.ID === mealId)
          if (meal) {
            meal.Title = response.data.Title
            meal.Time = response.data.Time
            meal.RenderTime = response.data.RenderTime
            meal.AcceptsContributions = response.data.AcceptsContributions
            break
          }
        }
      }

      await clearCacheForEndpoint('/calendar')
      return response.data
    } catch (err) {
      console.error('Failed to update meal:', err)
      throw err
    }
  }

  async function deleteMeal(mealId) {
    try {
      await apiDelete(`/calendar/meal/${mealId}`)

      for (const event of events.value) {
        if (event.Meals) {
          const idx = event.Meals.findIndex(m => m.ID === mealId)
          if (idx !== -1) {
            event.Meals.splice(idx, 1)
            break
          }
        }
      }

      await clearCacheForEndpoint('/calendar')
    } catch (err) {
      console.error('Failed to delete meal:', err)
      throw err
    }
  }

  async function addMeal(appointmentId, title, time, acceptsContributions) {
    try {
      const response = await apiPost(`/calendar/meal/${appointmentId}`, { title, time, acceptsContributions })

      const event = getEventById(appointmentId)
      if (event) {
        if (!event.Meals) event.Meals = []
        event.Meals.push(response.data)
      }

      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to add meal:', err)
      throw err
    }
  }

  async function addAgendaPoint(appointmentId, data) {
    const response = await apiPost(`/calendar/agendaPoint/${appointmentId}`, data)
    const event = getEventById(appointmentId)
    if (event) {
      if (!event.AgendaPoints) event.AgendaPoints = []
      event.AgendaPoints.push(response.data)
      event.AgendaPoints.sort((a, b) => (a.StartTime ?? '').localeCompare(b.StartTime ?? ''))
    }
    await clearCacheForEndpoint('/calendar')
    return response.data
  }

  async function updateAgendaPoint(pointId, data) {
    const response = await apiPut(`/calendar/agendaPoint/${pointId}`, data)
    for (const event of events.value) {
      if (event.AgendaPoints) {
        const point = event.AgendaPoints.find(p => p.ID === pointId)
        if (point) {
          Object.assign(point, response.data)
          event.AgendaPoints.sort((a, b) => (a.StartTime ?? '').localeCompare(b.StartTime ?? ''))
          break
        }
      }
    }
    await clearCacheForEndpoint('/calendar')
    return response.data
  }

  async function deleteAgendaPoint(pointId) {
    await apiDelete(`/calendar/agendaPoint/${pointId}`)
    for (const event of events.value) {
      if (event.AgendaPoints) {
        const idx = event.AgendaPoints.findIndex(p => p.ID === pointId)
        if (idx !== -1) {
          event.AgendaPoints.splice(idx, 1)
          break
        }
      }
    }
    await clearCacheForEndpoint('/calendar')
  }

  /**
   * Leert alle Events (z.B. für Logout)
   */
  function clearEvents() {
    events.value = []
    error.value = null
  }

  async function createAbsence(data) {
    const response = await apiPost('/calendar/absence', data)
    return response
  }

  async function createAppointment(data) {
    const response = await apiPost('/calendar/appointment', data)
    return response
  }

  async function updateAbsence(id, data) {
    return apiPut('/calendar/absence', { id, ...data })
  }

  async function deleteAbsence(id) {
    return apiDelete('/calendar/absence?id=' + id)
  }

  async function updateAppointment(id, data) {
    return apiPut('/calendar/appointment', { id, ...data })
  }

  async function deleteAppointment(id) {
    return apiDelete('/calendar/appointment?id=' + id)
  }

  async function fetchAbsencesForDate(date) {
    const response = await apiGet(`/calendar/absences?date=${date}`, false)
    return response.absences ?? []
  }

  async function saveMealProductOrders(mealId, orders) {
    try {
      await apiPut(`/food/mealProductOrder/${mealId}`, { orders })
      // Update local state for all matching events
      for (const event of events.value) {
        const meal = event.Meals?.find(m => m.ID === mealId)
        if (meal) {
          for (const product of meal.Products || []) {
            product.UserQuantity = orders[product.ID] ?? 0
          }
        }
      }
      await clearCacheForEndpoint('/calendar')
    } catch (err) {
      console.error('Failed to save meal product orders:', err)
      throw err
    }
  }

  async function fetchAbsenceCountsForMonth(year, month) {
    const monthParam = `${year}-${String(month).padStart(2, '0')}`
    const response = await apiGet(`/calendar/absences?month=${monthParam}`, false)
    return response.absenceCounts ?? {}
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
    changeParticipationNotes,
    changeFoodParticipation,
    addMeal,
    updateMeal,
    deleteMeal,
    addAgendaPoint,
    updateAgendaPoint,
    deleteAgendaPoint,
    clearEvents,
    createAbsence,
    updateAbsence,
    deleteAbsence,
    createAppointment,
    updateAppointment,
    deleteAppointment,
    fetchAbsencesForDate,
    fetchAbsenceCountsForMonth,
    saveMealProductOrders,
  }
})
