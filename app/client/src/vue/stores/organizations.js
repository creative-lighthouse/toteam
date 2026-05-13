import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'

export const useOrganizationsStore = defineStore('organizations', () => {
  const organizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  async function fetchOrganizations(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/organizations')
      }

      const response = await apiGet('/organizations', !forceRefresh, 2 * 60 * 1000)
      organizations.value = response.organizations || []
    } catch (err) {
      console.error('Failed to fetch organizations:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function joinOrganization(orgID) {
    const response = await apiPost(`/organizations/join/${orgID}`, {})

    if (response.success) {
      const org = organizations.value.find(o => o.ID === orgID)
      if (org) {
        org.MembershipStatus = response.data.MembershipStatus
        if (response.data.MembershipStatus === 'member') {
          org.MemberCount++
        }
      }
    }

    return response
  }

  return {
    organizations,
    loading,
    error,
    fetchOrganizations,
    joinOrganization,
  }
})
