import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

/**
 * Events der Organisationen (OrgEvent, z.B. "Halloweenhaus 2026"), die mehrere
 * Termine bündeln. Nicht zu verwechseln mit dem `events`-Store, der einzelne
 * Kalendertermine hält.
 */
export const useOrgEventsStore = defineStore('orgEvents', () => {
  const events = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Laufende und kommende Events zuerst (nach Beginn), danach vergangene und
  // Events ohne Termine
  const sortedEvents = computed(() => {
    const today = new Date().toISOString().slice(0, 10)
    const rank = e => (!e.DateStart ? 1 : (e.DateEnd ?? e.DateStart) >= today ? 0 : 2)
    return [...events.value].sort((a, b) =>
      rank(a) - rank(b)
      || (rank(a) === 2
        ? (b.DateStart ?? '').localeCompare(a.DateStart ?? '')
        : (a.DateStart ?? '').localeCompare(b.DateStart ?? ''))
      || a.Title.localeCompare(b.Title)
    )
  })

  async function fetchEvents() {
    loading.value = true
    error.value = null
    try {
      const res = await apiGet('/calendar/orgEvents', false)
      events.value = res.events ?? []
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /** Event inkl. seiner Termine */
  async function fetchEvent(id) {
    const res = await apiGet(`/calendar/orgEvent/${id}`, false)
    if (res?.success === false) throw new Error(res.error || 'Event konnte nicht geladen werden')
    replace(res.event)
    return res
  }

  async function createEvent(title, organizationId) {
    const res = await apiPost('/calendar/orgEvents', { title, organizationId })
    if (!res?.success) throw new Error(res?.error || 'Event konnte nicht angelegt werden')
    events.value = [...events.value, res.data.event]
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  async function renameEvent(id, title) {
    const res = await apiPut(`/calendar/orgEvent/${id}`, { title })
    if (!res?.success) throw new Error(res?.error || 'Event konnte nicht gespeichert werden')
    replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  async function deleteEvent(id) {
    const res = await apiDelete(`/calendar/orgEvent/${id}`)
    if (!res?.success) throw new Error(res?.error || 'Event konnte nicht gelöscht werden')
    events.value = events.value.filter(e => e.ID !== id)
    await clearCacheForEndpoint('/calendar')
  }

  function replace(event) {
    if (!event) return
    const index = events.value.findIndex(e => e.ID === event.ID)
    if (index === -1) events.value = [...events.value, event]
    else events.value.splice(index, 1, event)
  }

  return {
    events,
    loading,
    error,
    sortedEvents,
    fetchEvents,
    fetchEvent,
    createEvent,
    renameEvent,
    deleteEvent,
  }
})
