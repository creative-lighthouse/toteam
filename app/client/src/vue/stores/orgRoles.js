import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'

export const useOrgRolesStore = defineStore('orgRoles', () => {
  const categories = ref({})
  const roles = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchCatalogue() {
    if (Object.keys(categories.value).length) return
    try {
      const response = await apiGet('/orgroles/catalogue', true, 60 * 60 * 1000)
      categories.value = response.categories || {}
    } catch (err) {
      console.error('Failed to fetch permission catalogue:', err)
    }
  }

  async function fetchRoles(orgId) {
    try {
      loading.value = true
      error.value = null
      const response = await apiGet(`/orgroles/index/${orgId}`, false)
      roles.value = response.roles || []
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function createRole(data) {
    const response = await apiPost('/orgroles/store', data)
    if (response.success && response.data?.role) {
      roles.value.push(response.data.role)
    }
    return response
  }

  async function updateRole(id, data) {
    const response = await apiPut(`/orgroles/update/${id}`, data)
    if (response.success && response.data?.role) {
      const idx = roles.value.findIndex(r => r.ID === id)
      if (idx !== -1) roles.value[idx] = response.data.role
    }
    return response
  }

  async function deleteRole(id) {
    const response = await apiDelete(`/orgroles/remove/${id}`)
    if (response.success) {
      roles.value = roles.value.filter(r => r.ID !== id)
    }
    return response
  }

  async function assignRolesToMember(membershipId, roleIds) {
    return apiPut(`/orgroles/assignToMember/${membershipId}`, { RoleIDs: roleIds })
  }

  return {
    categories,
    roles,
    loading,
    error,
    fetchCatalogue,
    fetchRoles,
    createRole,
    updateRole,
    deleteRole,
    assignRolesToMember,
  }
})
