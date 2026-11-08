import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, apiPostForm, apiDownload } from '@utils/api'

// Foto-Totem (api/v1/photos). Die Bilder liegen im Bunny-Speicher der jeweiligen
// Organisation; die URLs kommen signiert vom Server und laufen nach einigen Stunden ab.
export const usePhotosStore = defineStore('photos', () => {
  // Kurzform: ID, OrganizationID, Title, Width, Height, TakenAt, ThumbURL, URL
  const items = ref([])
  const total = ref(0)
  const hasMore = ref(false)
  // { ID, Title, LogoURL, Configured, CanUpload, CanManage, CanManageStorage }
  const organizations = ref([])
  const loading = ref(false)
  const loadingMore = ref(false)
  const error = ref(null)
  // Auswahl je Organisation (Personen, Charaktere, Events, Schlagworte)
  const optionsByOrg = ref({})
  // Ordner der geöffneten Organisation: { organization, events: [{ ID, Title, DateStart, PhotoCount }], withoutEvent }
  const folders = ref(null)
  const foldersLoading = ref(false)

  let lastFilters = {}

  function unwrap(response, fallback) {
    if (!response?.success) throw new Error(response?.error || fallback)
    return response.data
  }

  function query(filters, offset) {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value) params.set(key, value)
    }
    if (offset) params.set('offset', offset)
    return `/photos?${params}`
  }

  async function fetchList(filters = {}) {
    lastFilters = filters
    listStale.value = false
    loading.value = items.value.length === 0
    error.value = null
    try {
      const response = await apiGet(query(filters, 0), false)
      items.value = response.photos || []
      total.value = response.total || 0
      hasMore.value = !!response.hasMore
      organizations.value = response.organizations || []
    } catch (err) {
      console.error('Failed to fetch photos:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /** Oberste Ordner-Ebene: die Organisationen (mit Fotoanzahl und Farbe) */
  async function fetchOrganizations() {
    loading.value = organizations.value.length === 0
    error.value = null
    try {
      organizations.value = unwrap(await apiGet('/photos/organizations', false), 'Organisationen konnten nicht geladen werden').organizations
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /** Zweite Ebene: die Event-Ordner einer Organisation */
  async function fetchFolders(orgId) {
    if (folders.value?.organization.ID !== orgId) folders.value = null
    foldersLoading.value = !folders.value
    error.value = null
    try {
      folders.value = unwrap(await apiGet(`/photos/folders/${orgId}`, false), 'Ordner konnten nicht geladen werden')
    } catch (err) {
      error.value = err.message
    } finally {
      foldersLoading.value = false
    }
  }

  async function fetchMore() {
    if (!hasMore.value || loadingMore.value) return
    loadingMore.value = true
    try {
      const response = await apiGet(query(lastFilters, items.value.length), false)
      const known = new Set(items.value.map(p => p.ID))
      items.value = [...items.value, ...(response.photos || []).filter(p => !known.has(p.ID))]
      hasMore.value = !!response.hasMore
      total.value = response.total || total.value
    } finally {
      loadingMore.value = false
    }
  }

  async function fetchOptions(orgId, force = false) {
    if (!orgId) return null
    if (!force && optionsByOrg.value[orgId]) return optionsByOrg.value[orgId]
    const data = unwrap(await apiGet(`/photos/options/${orgId}`, false), 'Auswahl konnte nicht geladen werden')
    optionsByOrg.value = { ...optionsByOrg.value, [orgId]: data }
    return data
  }

  async function fetchDetail(id) {
    return unwrap(await apiGet(`/photos/detail/${id}`, false), 'Foto nicht gefunden').photo
  }

  /**
   * Ein vorbereitetes Foto hochladen (siehe utils/photoUpload.js).
   * @returns {{ photo: object, duplicate: boolean }}
   */
  async function upload({ organizationId, eventId, tagging = {}, copyright, file, prepared, meta }) {
    const form = new FormData()
    form.append('OrganizationID', organizationId)
    if (eventId) form.append('EventID', eventId)
    // Tags für alle Fotos des Uploads: Tags (Titel), PeopleIDs, CharacterIDs, RoomIDs, AreaIDs
    for (const [field, values] of Object.entries(tagging)) {
      if (values?.length) form.append(field, JSON.stringify(values))
    }
    form.append('OriginalName', file.name)
    if (copyright) form.append('Copyright', copyright)
    if (meta.takenAt) form.append('TakenAt', meta.takenAt)
    if (meta.latitude !== null && meta.longitude !== null) {
      form.append('Latitude', meta.latitude)
      form.append('Longitude', meta.longitude)
    }
    form.append('full', prepared.full, 'full.jpg')
    form.append('thumb', prepared.thumb, 'thumb.jpg')
    return unwrap(await apiPostForm('/photos/upload', form), 'Hochladen fehlgeschlagen')
  }

  /** Nach dem Hochladen: Liste neu laden, damit Reihenfolge und Filter stimmen */
  function refresh() {
    return fetchList(lastFilters)
  }

  // Nach Änderungen im Betrachter muss die Liste neu geladen werden (z.B. Event gewechselt:
  // dann gehört das Foto nicht mehr in den offenen Ordner)
  const listStale = ref(false)

  /**
   * Die Kurzform in der Liste aus der Detailform nachziehen — Listen- und Kachelansicht
   * zeigen Titel, Copyright und Tags, ohne neu zu laden.
   */
  function syncSummary(photo) {
    const item = items.value.find(p => p.ID === photo.ID)
    if (!item) return
    Object.assign(item, {
      Title: photo.Title,
      Copyright: photo.Copyright,
      PeopleNames: photo.People.map(p => p.Name),
      CharacterNames: photo.Characters.map(c => c.Title),
      TagNames: photo.Tags.map(t => t.Title),
      RoomNames: [...(photo.Rooms ?? []), ...(photo.MapAreas ?? [])].map(r => r.Title),
    })
  }

  async function update(id, data) {
    const photo = unwrap(await apiPut(`/photos/update/${id}`, data), 'Fehler beim Speichern').photo
    syncSummary(photo)
    if ('EventIDs' in data) listStale.value = true
    // Neue Schlagworte und Copyright-Einträge tauchen in der Auswahl auf
    if (data.Tags || 'Copyright' in data) fetchOptions(photo.OrganizationID, true).catch(() => {})
    return photo
  }

  async function remove(id) {
    unwrap(await apiDelete(`/photos/remove/${id}`), 'Fehler beim Löschen')
    items.value = items.value.filter(p => p.ID !== id)
    total.value = Math.max(0, total.value - 1)
  }

  /**
   * Mehrfachauswahl: Tags zu mehreren Fotos hinzufügen ({ PeopleIDs, CharacterIDs, RoomIDs,
   * AreaIDs, Tags }), danach die Liste neu laden
   * @returns {{ updated: number, skipped: number }}
   */
  async function bulkTag(ids, tagging) {
    const result = unwrap(await apiPost('/photos/bulkTag', { IDs: ids, ...tagging }), 'Fehler beim Taggen')
    if (tagging.Tags?.length && items.value[0]) fetchOptions(items.value[0].OrganizationID, true).catch(() => {})
    await refresh()
    return result
  }

  /** @returns {{ removed: number[], skipped: number }} */
  async function bulkRemove(ids) {
    const result = unwrap(await apiPost('/photos/bulkRemove', { IDs: ids }), 'Fehler beim Löschen')
    const removed = new Set(result.removed)
    items.value = items.value.filter(p => !removed.has(p.ID))
    total.value = Math.max(0, total.value - removed.size)
    return result
  }

  async function untagMe(id) {
    const photo = unwrap(await apiPost(`/photos/untagMe/${id}`, {}), 'Fehler beim Entfernen').photo
    syncSummary(photo)
    return photo
  }

  async function fetchSettings(orgId) {
    return unwrap(await apiGet(`/photos/settings/${orgId}`, false), 'Einstellungen konnten nicht geladen werden')
  }

  async function saveSettings(orgId, data) {
    const result = unwrap(await apiPut(`/photos/settings/${orgId}`, data), 'Speichern fehlgeschlagen')
    const org = organizations.value.find(o => o.ID === orgId)
    if (org) org.Configured = result.settings.IsComplete
    return result
  }

  /** Verbindung prüfen, ohne zu speichern — gibt die Meldung des Servers zurück */
  async function testSettings(orgId, data) {
    const response = await apiPost(`/photos/testSettings/${orgId}`, data)
    if (!response?.success) throw new Error(response?.error || 'Verbindung fehlgeschlagen')
    return response.message
  }

  /** Foto als Datei speichern (über den Server, siehe photos/download) */
  function download(photo) {
    const base = (photo.OriginalName || '').replace(/\.[^.]+$/, '') || `foto-${photo.ID}`
    return apiDownload(`/photos/download/${photo.ID}`, `${base}.jpg`)
  }

  async function fetchEventPhotos(eventId) {
    return unwrap(await apiGet(`/photos/eventPhotos/${eventId}`, false), 'Fotos konnten nicht geladen werden')
  }

  return {
    items,
    listStale,
    total,
    hasMore,
    organizations,
    loading,
    loadingMore,
    error,
    optionsByOrg,
    folders,
    foldersLoading,
    fetchOrganizations,
    fetchFolders,
    fetchList,
    fetchMore,
    fetchOptions,
    fetchDetail,
    upload,
    refresh,
    update,
    remove,
    untagMe,
    bulkTag,
    bulkRemove,
    fetchSettings,
    saveSettings,
    testSettings,
    fetchEventPhotos,
    download,
  }
})
