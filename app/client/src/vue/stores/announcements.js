import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'

export const useAnnouncementsStore = defineStore('announcements', () => {
  const announcements = ref([])
  const categories = ref([])
  // Organisationen, in denen der Nutzer Mitteilungen erstellen darf
  const createOrganizations = ref([])
  const selectedCategory = ref(null)
  const loading = ref(false)
  const error = ref(null)
  // Abgelaufene Mitteilungen, werden erst bei Bedarf geladen
  const archivedAnnouncements = ref([])
  const archiveLoaded = ref(false)
  const archiveLoading = ref(false)
  const archiveError = ref(null)

  function filterByCategory(list) {
    if (!selectedCategory.value) return list
    return list.filter(a => a.CategoryID === selectedCategory.value.ID)
  }

  const filteredAnnouncements = computed(() => filterByCategory(announcements.value))
  const filteredArchivedAnnouncements = computed(() => filterByCategory(archivedAnnouncements.value))

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
      createOrganizations.value = response.createOrganizations || []
    } catch (err) {
      console.error('Failed to fetch announcements:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchArchivedAnnouncements(forceRefresh = false) {
    try {
      archiveLoading.value = true
      archiveError.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/announcements?archive=1')
      }

      const response = await apiGet('/announcements?archive=1', !forceRefresh, 2 * 60 * 1000)
      archivedAnnouncements.value = response.announcements || []
      archiveLoaded.value = true
    } catch (err) {
      console.error('Failed to fetch archived announcements:', err)
      archiveError.value = err.message
    } finally {
      archiveLoading.value = false
    }
  }

  function getAnnouncementById(id) {
    return announcements.value.find(a => a.ID === id)
      ?? archivedAnnouncements.value.find(a => a.ID === id)
      ?? null
  }

  async function createAnnouncement(data) {
    const response = await apiPost('/announcements/store', data)
    if (response.success && response.data?.announcement) {
      // Geplante oder bereits abgelaufene Mitteilungen gehören nicht in die aktuelle Liste
      if (response.data.announcement.Status === 'active') {
        announcements.value.unshift(response.data.announcement)
      }
      await clearCacheForEndpoint('/announcements')
      archiveLoaded.value = false
    }
    return response
  }

  function setCategory(category) {
    selectedCategory.value = category
  }

  async function refresh() {
    await fetchAnnouncements(true)
    if (archiveLoaded.value) {
      await fetchArchivedAnnouncements(true)
    }
  }

  return {
    announcements,
    categories,
    createOrganizations,
    usedCategories,
    selectedCategory,
    loading,
    error,
    filteredAnnouncements,
    archivedAnnouncements,
    archiveLoaded,
    archiveLoading,
    archiveError,
    filteredArchivedAnnouncements,
    fetchAnnouncements,
    fetchArchivedAnnouncements,
    getAnnouncementById,
    createAnnouncement,
    setCategory,
    refresh
  }
})
