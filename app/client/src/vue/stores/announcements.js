import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, clearCacheForEndpoint } from '@utils/api'

export const useAnnouncementsStore = defineStore('announcements', () => {
  const announcements = ref([])
  const categories = ref([])
  const selectedCategory = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const filteredAnnouncements = computed(() => {
    if (!selectedCategory.value) return announcements.value
    return announcements.value.filter(a => a.CategoryID === selectedCategory.value.ID)
  })

  const usedCategories = computed(() => {
    const usedIDs = new Set(announcements.value.map(a => a.CategoryID).filter(Boolean))
    return categories.value.filter(c => usedIDs.has(c.ID))
  })

  async function fetchAnnouncements(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/announcements')
      }

      const response = await apiGet('/announcements', !forceRefresh, 2 * 60 * 1000)

      announcements.value = response.announcements || []
      categories.value = response.categories || []
    } catch (err) {
      console.error('Failed to fetch announcements:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  function getAnnouncementById(id) {
    return announcements.value.find(a => a.ID === id) ?? null
  }

  function setCategory(category) {
    selectedCategory.value = category
  }

  async function refresh() {
    await fetchAnnouncements(true)
  }

  return {
    announcements,
    categories,
    usedCategories,
    selectedCategory,
    loading,
    error,
    filteredAnnouncements,
    fetchAnnouncements,
    getAnnouncementById,
    setCategory,
    refresh
  }
})
