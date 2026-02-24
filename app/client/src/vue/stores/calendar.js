import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'

export const useCalendarStore = defineStore('calendar', () => {
  // State
  const events = ref([])
  const currentYear = ref(new Date().getFullYear())
  const currentMonth = ref(new Date().getMonth() + 1)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const currentMonthKey = computed(() => {
    return `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}`
  })

  const eventsGroupedByDate = computed(() => {
    const grouped = {}
    events.value.forEach(event => {
      if (!grouped[event.Date]) {
        grouped[event.Date] = []
      }
      grouped[event.Date].push(event)
    })
    return grouped
  })

  const upcomingEvents = computed(() => {
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    return events.value
      .filter(e => new Date(e.Date) >= today)
      .slice(0, 5)
  })

  // Actions
  async function fetchEvents(year, month, forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      currentYear.value = year
      currentMonth.value = month

      if (forceRefresh) {
        await clearCacheForEndpoint('/calendar')
      }

      const monthParam = `${year}-${String(month).padStart(2, '0')}`
      const response = await apiGet(`/calendar?month=${monthParam}`, !forceRefresh, 60 * 1000) // Cache for 1 minute

      events.value = response.events || []

      return events.value
    } catch (err) {
      console.error('Failed to fetch calendar events:', err)
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchCurrentMonth(forceRefresh = false) {
    return await fetchEvents(currentYear.value, currentMonth.value, forceRefresh)
  }

  async function changeParticipation(eventId, type) {
    try {
      const response = await apiPost(`/calendar/participation/${eventId}`, {
        response: type
      })

      // Update local state
      const event = events.value.find(e => e.ID === eventId)
      if (event) {
        if (!event.UserParticipation) {
          event.UserParticipation = {}
        }
        event.UserParticipation.ID = response.data.ID
        event.UserParticipation.Type = response.data.Type
        event.UserParticipation.TimeStart = response.data.TimeStart
        event.UserParticipation.TimeEnd = response.data.TimeEnd

        // Update in participations list if available
        if (event.Participations) {
          const userParticipation = event.Participations.find(p => p.IsCurrentUser)
          if (userParticipation) {
            userParticipation.Type = response.data.Type
            userParticipation.TimeStart = response.data.TimeStart
            userParticipation.TimeEnd = response.data.TimeEnd
          }
        }
      }

      // Clear cache to ensure fresh data on reload
      await clearCacheForEndpoint('/calendar')

      return response.data
    } catch (err) {
      console.error('Failed to change participation:', err)
      throw err
    }
  }

  async function changeParticipationTime(eventId, timeStart, timeEnd) {
    try {
      const response = await apiPost(`/calendar/participationTime/${eventId}`, {
        timestart: timeStart,
        timeend: timeEnd
      })

      // Update local state
      const event = events.value.find(e => e.ID === eventId)
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

  function getEventById(eventId) {
    return events.value.find(e => e.ID === eventId)
  }

  function setMonth(year, month) {
    currentYear.value = year
    currentMonth.value = month
  }

  function nextMonth() {
    if (currentMonth.value === 12) {
      currentMonth.value = 1
      currentYear.value++
    } else {
      currentMonth.value++
    }
    return { year: currentYear.value, month: currentMonth.value }
  }

  function previousMonth() {
    if (currentMonth.value === 1) {
      currentMonth.value = 12
      currentYear.value--
    } else {
      currentMonth.value--
    }
    return { year: currentYear.value, month: currentMonth.value }
  }

  async function refresh() {
    await fetchCurrentMonth(true)
  }

  return {
    // State
    events,
    currentYear,
    currentMonth,
    loading,
    error,
    // Getters
    currentMonthKey,
    eventsGroupedByDate,
    upcomingEvents,
    // Actions
    fetchEvents,
    fetchCurrentMonth,
    changeParticipation,
    changeParticipationTime,
    changeFoodParticipation,
    getEventById,
    setMonth,
    nextMonth,
    previousMonth,
    refresh
  }
})
