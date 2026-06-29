import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref([])
  const organizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Filters
  const filterOrganization = ref(null)
  const filterDeadline = ref(null)
  const filterSearch = ref('')
  const filterState = ref(null)

  const STATES = [
    { value: 'open',        label: 'Offen' },
    { value: 'in_progress', label: 'In Bearbeitung' },
    { value: 'feedback',    label: 'Feedback' },
    { value: 'finished',    label: 'Abgeschlossen' },
  ]

  const filteredTasks = computed(() => {
    let result = tasks.value

    if (filterOrganization.value) {
      result = result.filter(t => t.Organization?.ID === filterOrganization.value.ID)
    }

    if (filterState.value) {
      result = result.filter(t => t.State === filterState.value)
    }

    if (filterDeadline.value) {
      result = result.filter(t => t.Deadline && t.Deadline <= filterDeadline.value + 'T23:59:59')
    }

    if (filterSearch.value.trim()) {
      const q = filterSearch.value.toLowerCase()
      result = result.filter(t =>
        t.Title?.toLowerCase().includes(q) ||
        t.Description?.toLowerCase().includes(q)
      )
    }

    return result
  })

  const tasksByState = computed(() => {
    const grouped = { open: [], in_progress: [], feedback: [], finished: [] }
    for (const task of filteredTasks.value) {
      const state = task.State || 'open'
      if (grouped[state]) grouped[state].push(task)
    }
    return grouped
  })

  async function fetchTasks(forceRefresh = false) {
    try {
      loading.value = true
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/tasks')
      }

      const response = await apiGet('/tasks', !forceRefresh, 2 * 60 * 1000)
      tasks.value = response.tasks || []
      organizations.value = response.organizations || []
    } catch (err) {
      console.error('Failed to fetch tasks:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchTaskByHash(hash) {
    try {
      const response = await apiGet(`/tasks/detail?hash=${hash}`, false)
      return response.task || null
    } catch (err) {
      console.error('Failed to fetch task by hash:', err)
      return null
    }
  }

  async function createTask(data) {
    const response = await apiPost('/tasks/store', data)
    if (response.success && response.data?.task) {
      tasks.value.unshift(response.data.task)
      await clearCacheForEndpoint('/tasks')
    }
    return response
  }

  async function updateTask(id, data) {
    const response = await apiPut(`/tasks/update/${id}`, data)
    if (response.success && response.data?.task) {
      const idx = tasks.value.findIndex(t => t.ID === id)
      if (idx !== -1) tasks.value[idx] = response.data.task
      await clearCacheForEndpoint('/tasks')
    }
    return response
  }

  async function updateTaskState(id, state) {
    const response = await apiPut(`/tasks/updateState/${id}`, { State: state })
    if (response.success && response.data?.task) {
      const updated = response.data.task
      const idx = tasks.value.findIndex(t => t.ID === id)
      if (idx !== -1) tasks.value[idx] = { ...tasks.value[idx], ...updated }
      // Also update within subtasks
      for (const task of tasks.value) {
        const subIdx = task.SubTasks?.findIndex(s => s.ID === id) ?? -1
        if (subIdx !== -1) task.SubTasks[subIdx] = { ...task.SubTasks[subIdx], ...updated }
      }
      await clearCacheForEndpoint('/tasks')
    }
    return response
  }

  async function deleteTask(id) {
    const response = await apiDelete(`/tasks/remove/${id}`)
    if (response.success) {
      tasks.value = tasks.value.filter(t => t.ID !== id)
      await clearCacheForEndpoint('/tasks')
    }
    return response
  }

  function getTaskById(id) {
    return tasks.value.find(t => t.ID === id) ?? null
  }

  function setOrganizationFilter(org) { filterOrganization.value = org }
  function setDeadlineFilter(date) { filterDeadline.value = date }
  function setSearchFilter(q) { filterSearch.value = q }
  function setStateFilter(state) { filterState.value = state }

  async function refresh() {
    await fetchTasks(true)
  }

  return {
    tasks,
    organizations,
    loading,
    error,
    filterOrganization,
    filterDeadline,
    filterSearch,
    filterState,
    filteredTasks,
    tasksByState,
    STATES,
    fetchTasks,
    fetchTaskByHash,
    createTask,
    updateTask,
    updateTaskState,
    deleteTask,
    getTaskById,
    setOrganizationFilter,
    setDeadlineFilter,
    setSearchFilter,
    setStateFilter,
    refresh,
  }
})
