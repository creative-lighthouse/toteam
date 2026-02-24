import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'

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
  
  const unreadCount = computed(() => {
    return notices.value.filter(notice => !notice.IsRead).length
  })
  
  // Actions
  async function fetchNotices(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null
      
      if (forceRefresh) {
        await clearCacheForEndpoint('/notices')
      }
      
      const response = await apiGet('/notices', !forceRefresh, 2 * 60 * 1000) // Cache for 2 minutes
      
      notices.value = response.notices || []
      categories.value = response.categories || []
    } catch (err) {
      console.error('Failed to fetch notices:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }
  
  async function markAsRead(noticeId) {
    try {
      await apiPost(`/notices/${noticeId}/read`)
      
      // Update local state
      const notice = notices.value.find(n => n.ID === noticeId)
      if (notice) {
        notice.IsRead = true
      }
      
      // Clear cache to refresh on next load
      await clearCacheForEndpoint('/notices')
    } catch (err) {
      console.error('Failed to mark notice as read:', err)
      throw err
    }
  }
  
  function setCategory(category) {
    selectedCategory.value = category
  }
  
  async function refresh() {
    await fetchNotices(true)
  }
  
  return {
    // State
    notices,
    categories,
    selectedCategory,
    loading,
    error,
    // Getters
    filteredNotices,
    unreadCount,
    // Actions
    fetchNotices,
    markAsRead,
    setCategory,
    refresh
  }
})
