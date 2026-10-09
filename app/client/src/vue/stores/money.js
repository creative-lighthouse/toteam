import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'

export const useMoneyStore = defineStore('money', () => {
  const accounts = ref([])
  const currentAccount = ref(null)
  const currentBudget = ref(null)
  const currentEntry = ref(null)
  const loading = ref(false)
  const error = ref(null)
  // Noch nicht genehmigte Buchungen, die man genehmigen darf — für die Zahl im Hauptmenü
  const pendingEntries = ref(0)

  async function fetchPendingCount() {
    try {
      const response = await apiGet('/money/pendingCount', false)
      pendingEntries.value = response.pendingEntries || 0
    } catch {
      // Ohne Verbindung bleibt die letzte Zahl stehen
    }
  }

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

  async function fetchBudgetEntries(id) {
    try {
      loading.value = true
      error.value = null
      const response = await apiGet(`/money/budgetEntries/${id}`, false)
      currentBudget.value = response.budget ? response : null
    } catch (err) {
      error.value = err.message
      currentBudget.value = null
    } finally {
      loading.value = false
    }
  }

  async function fetchEntryDetail(id) {
    try {
      loading.value = true
      error.value = null
      const response = await apiGet(`/money/entryDetail/${id}`, false)
      currentEntry.value = response.entry ? response : null
    } catch (err) {
      error.value = err.message
      currentEntry.value = null
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

  async function removeBudget(id) {
    const response = await apiDelete(`/money/budgetRemove/${id}`)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  // Mitglieder der Organisation einer Kasse — Auswahl, für wen eine Buchung erfasst wird
  async function fetchAccountMembers(accountId) {
    try {
      const response = await apiGet(`/money/accountMembers/${accountId}`, false)
      return response.members || []
    } catch (err) {
      console.error('Failed to fetch account members:', err)
      return []
    }
  }

  async function createEntry(formData) {
    const response = await apiPostForm('/money/entryStore', formData)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
      fetchPendingCount()
    }
    return response
  }

  async function updateEntry(id, formData) {
    const response = await apiPostForm(`/money/entryUpdate/${id}`, formData)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
      fetchPendingCount()
    }
    return response
  }

  async function approveEntry(id, approve) {
    const response = await apiPut(`/money/entryApprove/${id}`, { approve })
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
      fetchPendingCount()
    }
    return response
  }

  async function removeEntry(id) {
    const response = await apiDelete(`/money/entry/${id}`)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
      fetchPendingCount()
    }
    return response
  }

  async function settleEntry(id, data) {
    const response = await apiPost(`/money/entrySettle/${id}`, data)
    if (response.success) {
      if (response.data?.account) currentAccount.value = response.data.account
      await clearCacheForEndpoint('/money')
    }
    return response
  }

  // ── Kassen eines Events (Event-Seite) ──
  // Alle Aufrufe liefern den vollständigen Stand: { accounts, availableAccounts, CanCreate }

  async function fetchEventAccounts(eventId) {
    const response = await apiGet(`/money/eventAccounts/${eventId}`, false)
    if (response?.success === false) throw new Error(response.error || 'Kassen konnten nicht geladen werden')
    return response
  }

  async function eventAccountMutation(promise, fallbackError) {
    const response = await promise
    if (!response?.success) throw new Error(response?.error || fallbackError)
    await clearCacheForEndpoint('/money')
    return response.data
  }

  /** data: { AccountID } für eine vorhandene Kasse oder { Title, TargetAmount? } für eine neue */
  function attachEventAccount(eventId, data) {
    return eventAccountMutation(apiPost(`/money/eventAccountAttach/${eventId}`, data), 'Kasse konnte nicht hinzugefügt werden')
  }

  function detachEventAccount(eventId, accountId) {
    return eventAccountMutation(apiDelete(`/money/eventAccountDetach/${eventId}?account=${accountId}`), 'Kasse konnte nicht gelöst werden')
  }

  return {
    accounts,
    currentAccount,
    currentBudget,
    currentEntry,
    loading,
    error,
    pendingEntries,
    fetchPendingCount,
    fetchOverview,
    fetchAccount,
    fetchBudgetEntries,
    fetchEntryDetail,
    fetchAccountMembers,
    createAccount,
    updateAccount,
    removeAccount,
    createBudget,
    updateBudget,
    removeBudget,
    createEntry,
    updateEntry,
    approveEntry,
    removeEntry,
    settleEntry,
    fetchEventAccounts,
    attachEventAccount,
    detachEventAccount,
  }
})
