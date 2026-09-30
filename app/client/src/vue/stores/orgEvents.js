import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'

/**
 * Events der Organisationen (OrgEvent, z.B. "Halloweenhaus 2026"), die mehrere
 * Termine bündeln. Nicht zu verwechseln mit dem `events`-Store, der einzelne
 * Kalendertermine hält.
 */
export const useOrgEventsStore = defineStore('orgEvents', () => {
  const events = ref([])
  const loading = ref(false)
  const error = ref(null)
  // Im CMS gepflegte Auswahllisten fürs Event-Formular
  const types = ref([])
  const ageGroups = ref([])

  // Laufende und kommende Events zuerst (nach Beginn), danach vergangene und
  // Events ohne Datum. RangeStart/RangeEnd sind die eigenen Daten des Events
  // oder, ohne eigene Angabe, der Zeitraum seiner Termine.
  const sortedEvents = computed(() => {
    const today = new Date().toISOString().slice(0, 10)
    const rank = e => (!e.RangeStart ? 1 : (e.RangeEnd ?? e.RangeStart) >= today ? 0 : 2)
    return [...events.value].sort((a, b) =>
      rank(a) - rank(b)
      || (rank(a) === 2
        ? (b.RangeStart ?? '').localeCompare(a.RangeStart ?? '')
        : (a.RangeStart ?? '').localeCompare(b.RangeStart ?? ''))
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

  /**
   * Event-Seite per ID oder URL-Segment: { isInternal, event, appointments? }.
   * Außenstehende bekommen nur die Eckdaten öffentlicher Events (sonst 404).
   */
  async function fetchEvent(key) {
    const res = await apiGet(`/calendar/orgEvent/${encodeURIComponent(key)}`, false)
    if (res?.success === false) throw new Error(res.error || 'Event konnte nicht geladen werden')
    if (res.isInternal) replace(res.event)
    return res
  }

  async function fetchOptions() {
    const res = await apiGet('/calendar/orgEventOptions')
    types.value = res.types ?? []
    ageGroups.value = res.ageGroups ?? []
  }

  /** data: { title, organizationId, dateStart, …, prices } — siehe OrgEvent::applyApiData() */
  async function createEvent(data) {
    const res = await apiPost('/calendar/orgEvents', data)
    if (!res?.success) throw new Error(res?.error || 'Event konnte nicht angelegt werden')
    replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  async function updateEvent(id, data) {
    const res = await apiPut(`/calendar/orgEvent/${id}`, data)
    if (!res?.success) throw new Error(res?.error || 'Event konnte nicht gespeichert werden')
    replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  /** image: zugeschnittenes JPEG (Blob aus ImageCropModal) */
  async function uploadImage(id, image) {
    const fd = new FormData()
    fd.append('image', image, 'image.jpg')
    const res = await apiPostForm(`/calendar/orgEventImage/${id}`, fd)
    if (!res?.success) throw new Error(res?.error || 'Bild konnte nicht gespeichert werden')
    replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  /** files: File[] aus AppFileUpload — landen in der Galerie des Events */
  async function uploadGallery(id, files) {
    if (!files.length) return null
    const fd = new FormData()
    files.forEach(f => fd.append('images[]', f))
    const res = await apiPostForm(`/calendar/orgEventGallery/${id}`, fd)
    if (res?.data?.event) replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    if (!res?.success) throw new Error(res?.error || 'Bilder konnten nicht hochgeladen werden')
    return res.data.event
  }

  async function removeGalleryImage(id, imageId) {
    const res = await apiDelete(`/calendar/orgEventGallery/${id}?image=${imageId}`)
    if (!res?.success) throw new Error(res?.error || 'Bild konnte nicht entfernt werden')
    replace(res.data.event)
    await clearCacheForEndpoint('/calendar')
    return res.data.event
  }

  async function removeImage(id) {
    const res = await apiDelete(`/calendar/orgEventImage/${id}`)
    if (!res?.success) throw new Error(res?.error || 'Bild konnte nicht entfernt werden')
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
    types,
    ageGroups,
    sortedEvents,
    fetchEvents,
    fetchEvent,
    fetchOptions,
    createEvent,
    updateEvent,
    uploadImage,
    removeImage,
    uploadGallery,
    removeGalleryImage,
    deleteEvent,
  }
})
