import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, clearCacheForEndpoint } from '@utils/api'

export const useNoticesStore = defineStore('notices', () => {
  // State
  const notices = ref([])
  const categories = ref([])
  const selectedCategory = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const filteredNotices = computed(() => {
    if (!selectedCategory.value) return notices.value
    return notices.value.filter(notice => notice.CategoryID === selectedCategory.value.ID)
  })

  // Actions
  async function fetchNotices(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/notices')
      }

      const response = await apiGet('/notices', !forceRefresh, 2 * 60 * 1000)

      notices.value = response.notices || []
      categories.value = response.categories || []
    } catch (err) {
      console.error('Failed to fetch notices:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  function setCategory(category) {
    selectedCategory.value = category
  }

  async function refresh() {
    await fetchNotices(true)
  }

  return {
    notices,
    categories,
    selectedCategory,
    loading,
    error,
    filteredNotices,
    fetchNotices,
    setCategory,
    refresh
  }
})
