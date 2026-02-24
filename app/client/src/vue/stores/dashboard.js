import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, clearCacheForEndpoint } from '@utils/api'

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const todaysParticipations = ref([])
  const upcomingEvents = ref([])
  const eventsWithoutFeedback = ref([])
  const loading = ref(false)
  const error = ref(null)
  
  // Getters
  const hasEventsToday = computed(() => todaysParticipations.value.length > 0)
  const hasUpcomingEvents = computed(() => upcomingEvents.value.length > 0)
  const needsFeedback = computed(() => eventsWithoutFeedback.value.length > 0)
  
  // Actions
  async function fetchDashboardData(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null
      
      if (forceRefresh) {
        await clearCacheForEndpoint('/dashboard')
      }
      
      const response = await apiGet('/dashboard', !forceRefresh, 3 * 60 * 1000) // Cache for 3 minutes
      
      todaysParticipations.value = response.todaysParticipations || []
      upcomingEvents.value = response.upcomingEvents || []
      eventsWithoutFeedback.value = response.eventsWithoutFeedback || []
    } catch (err) {
      console.error('Failed to fetch dashboard data:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }
  
  async function refresh() {
    await fetchDashboardData(true)
  }
  
  return {
    // State
    todaysParticipations,
    upcomingEvents,
    eventsWithoutFeedback,
    loading,
    error,
    // Getters
    hasEventsToday,
    hasUpcomingEvents,
    needsFeedback,
    // Actions
    fetchDashboardData,
    refresh
  }
})
