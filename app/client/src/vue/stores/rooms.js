import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiGetSWR, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

export const useRoomsStore = defineStore('rooms', () => {
  const rooms = ref([])
  const organizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  const filterOrganization = ref(null)
  const filterSearch = ref('')

  const filteredRooms = computed(() => {
    let result = rooms.value

    if (filterOrganization.value) {
      result = result.filter(r => r.Organization?.ID === filterOrganization.value.ID)
    }

    if (filterSearch.value.trim()) {
      const q = filterSearch.value.toLowerCase()
      result = result.filter(r => r.Title?.toLowerCase().includes(q))
    }

    return result
  })

  function applyRoomsResponse(response) {
    rooms.value = response.rooms || []
    organizations.value = response.organizations || []
  }

  async function fetchRooms(forceRefresh = false) {
    try {
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/rooms')
        loading.value = rooms.value.length === 0
        applyRoomsResponse(await apiGet('/rooms', false))
        return
      }

      loading.value = rooms.value.length === 0
      const { data } = await apiGetSWR('/rooms', applyRoomsResponse, 2 * 60 * 1000)
      applyRoomsResponse(data)
    } catch (err) {
      console.error('Failed to fetch rooms:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchRoomDetail(id) {
    try {
      const response = await apiGet(`/rooms/detail/${id}`, false)
      return response.room || null
    } catch (err) {
      console.error('Failed to fetch room detail:', err)
      return null
    }
  }

  async function fetchAttachableTasks(orgId) {
    try {
      const response = await apiGet(`/rooms/attachableTasks?organization=${orgId}`, false)
      return response.tasks || []
    } catch (err) {
      console.error('Failed to fetch attachable tasks:', err)
      return []
    }
  }

  async function createRoom(data) {
    const response = await apiPost('/rooms/store', data)
    if (response.success) {
      await clearCacheForEndpoint('/rooms')
      await fetchRooms(true)
    }
    return response
  }

  async function updateRoom(id, data) {
    const response = await apiPut(`/rooms/update/${id}`, data)
    if (response.success) {
      await clearCacheForEndpoint('/rooms')
      await fetchRooms(true)
    }
    return response
  }

  async function deleteRoom(id) {
    const response = await apiDelete(`/rooms/remove/${id}`)
    if (response.success) {
      rooms.value = rooms.value.filter(r => r.ID !== id)
      await clearCacheForEndpoint('/rooms')
    }
    return response
  }

  function setOrganizationFilter(org) { filterOrganization.value = org }
  function setSearchFilter(q) { filterSearch.value = q }

  async function refresh() {
    await fetchRooms(true)
  }

  return {
    rooms,
    organizations,
    loading,
    error,
    filterOrganization,
    filterSearch,
    filteredRooms,
    fetchRooms,
    fetchRoomDetail,
    fetchAttachableTasks,
    createRoom,
    updateRoom,
    deleteRoom,
    setOrganizationFilter,
    setSearchFilter,
    refresh,
  }
})
