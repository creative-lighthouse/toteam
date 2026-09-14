import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

export const useMarketingStore = defineStore('marketing', () => {
  const distributions = ref([])
  const sizes = ref([])
  const organizations = ref([])
  const years = ref([])
  const canManageSizes = ref(false)
  const loading = ref(false)
  const error = ref(null)

  const filterYear = ref(null)
  const filterOrganization = ref(null)

  const statistics = ref(null)
  const statisticsLoading = ref(false)
  const statisticsError = ref(null)

  async function fetchDistributions(forceRefresh = false) {
    try {
      loading.value = distributions.value.length === 0
      error.value = null

      const params = new URLSearchParams()
      if (filterYear.value) params.set('year', filterYear.value)
      if (filterOrganization.value) params.set('organization', filterOrganization.value)
      const query = params.toString() ? `?${params.toString()}` : ''
      const endpoint = `/marketing${query}`

      if (forceRefresh) {
        await clearCacheForEndpoint('/marketing')
      }

      const response = await apiGet(endpoint, !forceRefresh)
      distributions.value = response.distributions || []
      sizes.value = response.sizes || []
      organizations.value = response.organizations || []
      years.value = response.years || []
      canManageSizes.value = !!response.canManageSizes
    } catch (err) {
      console.error('Failed to fetch marketing distributions:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function createDistribution(data) {
    const response = await apiPost('/marketing/store', data)
    if (response.success && response.data?.distribution) {
      distributions.value.unshift(response.data.distribution)
      await clearCacheForEndpoint('/marketing')
    }
    return response
  }

  async function updateDistribution(id, data) {
    const response = await apiPut(`/marketing/update/${id}`, data)
    if (response.success && response.data?.distribution) {
      const idx = distributions.value.findIndex(d => d.ID === id)
      if (idx !== -1) distributions.value[idx] = response.data.distribution
      await clearCacheForEndpoint('/marketing')
    }
    return response
  }

  async function deleteDistribution(id) {
    const response = await apiDelete(`/marketing/remove/${id}`)
    if (response.success) {
      distributions.value = distributions.value.filter(d => d.ID !== id)
      await clearCacheForEndpoint('/marketing')
    }
    return response
  }

  async function createSize(organizationId, title) {
    const response = await apiPost('/marketing/sizeStore', { OrganizationID: organizationId, Title: title })
    if (response.success && response.data?.size) {
      sizes.value = [...sizes.value, response.data.size]
    }
    return response
  }

  async function updateSize(id, title) {
    const response = await apiPut(`/marketing/sizeUpdate/${id}`, { Title: title })
    if (response.success && response.data?.size) {
      const idx = sizes.value.findIndex(s => s.ID === id)
      if (idx !== -1) sizes.value[idx] = response.data.size
    }
    return response
  }

  async function deleteSize(id) {
    const response = await apiDelete(`/marketing/sizeRemove/${id}`)
    if (response.success) {
      sizes.value = sizes.value.filter(s => s.ID !== id)
    }
    return response
  }

  async function fetchStatistics(forceRefresh = false) {
    try {
      statisticsLoading.value = true
      statisticsError.value = null

      const params = new URLSearchParams()
      if (filterYear.value) params.set('year', filterYear.value)
      if (filterOrganization.value) params.set('organization', filterOrganization.value)
      const query = params.toString() ? `?${params.toString()}` : ''
      const endpoint = `/marketing/statistics${query}`

      if (forceRefresh) {
        await clearCacheForEndpoint('/marketing/statistics')
      }

      statistics.value = await apiGet(endpoint, !forceRefresh)
    } catch (err) {
      console.error('Failed to fetch marketing statistics:', err)
      statisticsError.value = err.message
    } finally {
      statisticsLoading.value = false
    }
  }

  function setYearFilter(year) {
    filterYear.value = year
  }

  function setOrganizationFilter(orgId) {
    filterOrganization.value = orgId
  }

  return {
    distributions,
    sizes,
    organizations,
    years,
    canManageSizes,
    loading,
    error,
    filterYear,
    filterOrganization,
    statistics,
    statisticsLoading,
    statisticsError,
    fetchStatistics,
    fetchDistributions,
    createDistribution,
    updateDistribution,
    deleteDistribution,
    createSize,
    updateSize,
    deleteSize,
    setYearFilter,
    setOrganizationFilter,
  }
})
