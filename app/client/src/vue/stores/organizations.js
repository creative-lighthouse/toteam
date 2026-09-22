import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, clearCacheForEndpoint } from '@utils/api'

const LAST_ORGANIZATION_STORAGE_KEY = 'toteam_last_organization_id'

function readLastOrganizationId() {
  try {
    const stored = localStorage.getItem(LAST_ORGANIZATION_STORAGE_KEY)
    return stored ? parseInt(stored) : null
  } catch {
    return null
  }
}

export const useOrganizationsStore = defineStore('organizations', () => {
  const organizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Persisted across the whole app so any single-organization picker can
  // default to the org the user last worked in, instead of always falling
  // back to "first in the list".
  const lastOrganizationId = ref(readLastOrganizationId())

  function setLastOrganizationId(id) {
    lastOrganizationId.value = id || null
    try {
      if (id) {
        localStorage.setItem(LAST_ORGANIZATION_STORAGE_KEY, String(id))
      } else {
        localStorage.removeItem(LAST_ORGANIZATION_STORAGE_KEY)
      }
    } catch {
      // localStorage unavailable (private mode, etc.) — in-memory value still works for this session
    }
  }

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

  async function createOrganization(payload) {
    const response = await apiPost('/organizations/store', payload)

    if (response.success) {
      organizations.value.push(response.data.organization)
      await clearCacheForEndpoint('/organizations')
    }

    return response
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
    lastOrganizationId,
    fetchOrganizations,
    createOrganization,
    joinOrganization,
    setLastOrganizationId,
  }
})
