import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'

export const useMoneyStore = defineStore('money', () => {
  const accounts = ref([])
  const currentAccount = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetchOverview(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null
      if (forceRefresh) await clearCacheForEndpoint('/money')
      const response = await apiGet('/money', !forceRefresh, 2 * 60 * 1000)
      accounts.value = response.accounts || []
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchAccount(id) {
    try {
      loading.value = true
      error.value = null
      const response = await apiGet(`/money/account/${id}`, false)
      currentAccount.value = response.account || null
    } catch (err) {
      error.value = err.message
      currentAccount.value = null
    } finally {
      loading.value = false
    }
  }

  async function createAccount(data) {
    const response = await apiPost('/money/accountStore', data)
    if (response.success) await clearCacheForEndpoint('/money')
    return response
  }

  async function updateAccount(id, data) {
    const response = await apiPut(`/money/accountUpdate/${id}`, data)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  async function removeAccount(id) {
    const response = await apiDelete(`/money/accountRemove/${id}`)
    if (response.success) {
      accounts.value = accounts.value.filter(a => a.ID !== id)
      if (currentAccount.value?.ID === id) currentAccount.value = null
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  async function createBudget(data) {
    const response = await apiPost('/money/budgetStore', data)
    if (response.success && currentAccount.value) {
      await fetchAccount(currentAccount.value.ID)
    }
    return response
  }

  async function updateBudget(id, data) {
    const response = await apiPut(`/money/budgetUpdate/${id}`, data)
    if (response.success && currentAccount.value) {
      await fetchAccount(currentAccount.value.ID)
    }
    return response
  }

  async function createEntry(formData) {
    const response = await apiPostForm('/money/entryStore', formData)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  async function approveEntry(id, approve) {
    const response = await apiPut(`/money/entryApprove/${id}`, { approve })
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  async function removeEntry(id) {
    const response = await apiDelete(`/money/entry/${id}`)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  return {
    accounts,
    currentAccount,
    loading,
    error,
    fetchOverview,
    fetchAccount,
    createAccount,
    updateAccount,
    removeAccount,
    createBudget,
    updateBudget,
    createEntry,
    approveEntry,
    removeEntry,
  }
})
