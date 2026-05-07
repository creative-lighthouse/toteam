import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, clearCacheForEndpoint } from '@utils/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const latestAnnouncements = ref([])
  const newFeedback = ref([])
  const loading = ref(false)
  const error = ref(null)

  const hasLatestAnnouncements = computed(() => latestAnnouncements.value.length > 0)
  const hasNewFeedback = computed(() => newFeedback.value.length > 0)

  async function fetchDashboardData(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/dashboard')
      }

      const response = await apiGet('/dashboard', !forceRefresh, 3 * 60 * 1000)

      latestAnnouncements.value = response.latestAnnouncements || []
      newFeedback.value = response.newFeedback || []
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
    latestAnnouncements,
    newFeedback,
    loading,
    error,
    hasLatestAnnouncements,
    hasNewFeedback,
    fetchDashboardData,
    refresh,
  }
})
