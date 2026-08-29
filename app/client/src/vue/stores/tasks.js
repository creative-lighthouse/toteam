import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { apiGet, apiGetSWR, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'
import { getJSONCookie, setJSONCookie } from '@utils/cookies'

const FILTERS_COOKIE = 'toteam_tasks_filters'

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref([])
  const organizations = ref([])
  const assignableMembers = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Restore filters from the last session. The organization filter needs the
  // organizations list (loaded async with the tasks), so we only remember its
  // ID here and resolve it once organizations.value is populated (see below).
  const savedFilters = getJSONCookie(FILTERS_COOKIE) || {}
  let pendingOrganizationId = savedFilters.organizationId ?? null

  // Filters
  const filterOrganization = ref(null)
  const filterDeadline = ref(savedFilters.deadline ?? null)
  const filterSearch = ref(savedFilters.search ?? '')
  const filterState = ref(savedFilters.state ?? null)
  const filterPersonId = ref(savedFilters.personId ?? null)

  // Which status groups are collapsed in the list view (state values, e.g. "open")
  const collapsedGroups = ref(new Set(savedFilters.collapsedGroups ?? []))

  watch([filterOrganization, filterDeadline, filterSearch, filterState, filterPersonId, collapsedGroups], () => {
    setJSONCookie(FILTERS_COOKIE, {
      organizationId: filterOrganization.value?.ID ?? null,
      deadline: filterDeadline.value,
      search: filterSearch.value,
      state: filterState.value,
      personId: filterPersonId.value,
      collapsedGroups: Array.from(collapsedGroups.value),
    })
  })

  function toggleGroupCollapsed(stateValue) {
    const next = new Set(collapsedGroups.value)
    if (next.has(stateValue)) {
      next.delete(stateValue)
    } else {
      next.add(stateValue)
    }
    collapsedGroups.value = next
  }

  const STATES = [
    { value: 'open',        label: 'Offen' },
    { value: 'in_progress', label: 'In Bearbeitung' },
    { value: 'feedback',    label: 'Feedback' },
    { value: 'finished',    label: 'Abgeschlossen' },
  ]

  // A task is "mine" if I'm its owner/supporter, or if I'm assigned to one of
  // its subtasks — a subtask-only assignment should still surface the parent
  // card, since subtasks aren't shown as their own entries in the list/kanban.
  function isTaskAssignedToMember(task, memberId) {
    if (task.Owner?.ID === memberId) return true
    if (task.Supporters?.some(s => s.ID === memberId)) return true
    return false
  }

  function isTaskMine(task, memberId) {
    if (isTaskAssignedToMember(task, memberId)) return true
    return task.SubTasks?.some(sub => isTaskAssignedToMember(sub, memberId)) ?? false
  }

  const filteredTasks = computed(() => {
    let result = tasks.value

    if (filterOrganization.value) {
      result = result.filter(t => t.Organization?.ID === filterOrganization.value.ID)
    }

    if (filterPersonId.value) {
      result = result.filter(t => isTaskMine(t, filterPersonId.value))
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

  function applyTasksResponse(response) {
    tasks.value = response.tasks || []
    organizations.value = response.organizations || []

    if (pendingOrganizationId !== null) {
      const org = organizations.value.find(o => o.ID === pendingOrganizationId)
      if (org) filterOrganization.value = org
      pendingOrganizationId = null
    }
  }

  async function fetchTasks(forceRefresh = false) {
    try {
      error.value = null

      if (forceRefresh) {
        await clearCacheForEndpoint('/tasks')
        loading.value = tasks.value.length === 0
        applyTasksResponse(await apiGet('/tasks', false))
        return
      }

      // Show cached tasks instantly (if any) and quietly refresh them in the
      // background — only block with a spinner when there's nothing to show yet.
      loading.value = tasks.value.length === 0
      const { data } = await apiGetSWR('/tasks', applyTasksResponse, 2 * 60 * 1000)
      applyTasksResponse(data)
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

  async function fetchOrgMembers(orgId) {
    try {
      const response = await apiGet(`/tasks/orgMembers/${orgId}`, false)
      return response.members || []
    } catch (err) {
      console.error('Failed to fetch organization members:', err)
      return []
    }
  }

  // Every member across all of the user's organizations, incl. members without
  // any tasks yet — used to populate the "filter by person" dropdown.
  async function fetchAssignableMembers() {
    try {
      const response = await apiGet('/tasks/assignableMembers')
      assignableMembers.value = response.members || []
    } catch (err) {
      console.error('Failed to fetch assignable members:', err)
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

  async function deleteTask(id, subtasksMode = null) {
    const query = subtasksMode ? `?subtasks=${subtasksMode}` : ''
    const response = await apiDelete(`/tasks/remove/${id}${query}`)
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
  function setPersonFilter(memberId) { filterPersonId.value = memberId }

  async function refresh() {
    await fetchTasks(true)
  }

  return {
    tasks,
    organizations,
    assignableMembers,
    loading,
    error,
    filterOrganization,
    filterDeadline,
    filterSearch,
    filterState,
    filterPersonId,
    collapsedGroups,
    filteredTasks,
    tasksByState,
    STATES,
    fetchTasks,
    fetchTaskByHash,
    fetchOrgMembers,
    fetchAssignableMembers,
    createTask,
    updateTask,
    updateTaskState,
    deleteTask,
    getTaskById,
    setOrganizationFilter,
    setDeadlineFilter,
    setSearchFilter,
    setStateFilter,
    setPersonFilter,
    toggleGroupCollapsed,
    refresh,
  }
})
