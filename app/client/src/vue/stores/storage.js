import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiGetSWR, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

/**
 * Lager im Inventar-Totem: verschachtelte Lagerpunkte (Ort › Gebäude › Kiste …)
 * mit den Objekten, Fahrzeugen und Räumen, die direkt darin lagern.
 */
export const useStorageStore = defineStore('storage', () => {
  const locations = ref([])
  // Sichtbare Objekte/Fahrzeuge/Räume ohne Lagerort, je Organisation bzw. Person:
  // [{ Key, Title, Image, IsMine, Items: [...], Rooms: [...] }]
  const unassigned = ref([])
  // Organisationen mit Inventar, je mit CanCreate (Recht "Lagerpunkte anlegen")
  const organizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  const filterSearch = ref('')

  const byId = computed(() => new Map(locations.value.map(l => [l.ID, l])))

  /** Lagerpunkte nach übergeordnetem Lagerpunkt; nicht sichtbare Eltern zählen als oberste Ebene */
  const childrenByParent = computed(() => {
    const map = new Map()
    for (const location of locations.value) {
      const parentId = location.ParentID && byId.value.has(location.ParentID) ? location.ParentID : null
      if (!map.has(parentId)) map.set(parentId, [])
      map.get(parentId).push(location)
    }
    // Reihenfolge per Drag & Drop (SortOrder), bei Gleichstand alphabetisch
    for (const list of map.values()) {
      list.sort((a, b) =>
        (a.SortOrder ?? 0) - (b.SortOrder ?? 0)
        || a.Title.localeCompare(b.Title, 'de', { sensitivity: 'base', numeric: true })
      )
    }
    return map
  })

  const rootLocations = computed(() => childrenByParent.value.get(null) ?? [])

  function childrenOf(id) {
    return childrenByParent.value.get(id) ?? []
  }

  /** Titel vom obersten sichtbaren Lagerpunkt bis zu diesem */
  function pathOf(id) {
    const path = []
    const seen = new Set()
    let current = byId.value.get(id)
    while (current && !seen.has(current.ID)) {
      seen.add(current.ID)
      path.unshift(current.Title)
      current = current.ParentID ? byId.value.get(current.ParentID) : null
    }
    return path
  }

  /** IDs aller (auch indirekt) enthaltenen Lagerpunkte */
  function descendantIds(id) {
    const ids = new Set()
    const stack = [id]
    while (stack.length) {
      for (const child of childrenOf(stack.pop())) {
        if (!ids.has(child.ID)) {
          ids.add(child.ID)
          stack.push(child.ID)
        }
      }
    }
    return ids
  }

  /** Anzahl Objekte/Fahrzeuge/Räume in einem Lagerpunkt samt allen enthaltenen */
  function totalContents(id) {
    return [id, ...descendantIds(id)].reduce((sum, locId) => {
      const location = byId.value.get(locId)
      return sum + (location?.Items?.length ?? 0) + (location?.Rooms?.length ?? 0)
    }, 0)
  }

  /**
   * Alle Lagerpunkte als flache Liste in Baumreihenfolge, mit Tiefe — z.B. für
   * Auswahllisten. `excludeId` lässt einen Lagerpunkt samt Inhalt weg (kein
   * Lagerpunkt kann in sich selbst lagern).
   */
  function flatTree(excludeId = null) {
    const excluded = excludeId ? new Set([excludeId, ...descendantIds(excludeId)]) : new Set()
    const result = []
    const walk = (list, depth) => {
      for (const location of list) {
        if (excluded.has(location.ID)) continue
        result.push({ location, depth })
        walk(childrenOf(location.ID), depth + 1)
      }
    }
    walk(rootLocations.value, 0)
    return result
  }

  /**
   * Suche: IDs der Lagerpunkte, die selbst oder über ihren Inhalt (Objekte,
   * Räume) passen, plus ihre übergeordneten — damit der Treffer im Baum sichtbar ist.
   * null = keine Suche aktiv.
   */
  const matchingIds = computed(() => {
    const q = filterSearch.value.trim().toLowerCase()
    if (!q) return null
    const ids = new Set()
    for (const location of locations.value) {
      const hit = location.Title?.toLowerCase().includes(q)
        || location.Description?.toLowerCase().includes(q)
        || location.Type?.Title?.toLowerCase().includes(q)
        || location.Items?.some(i => i.Title?.toLowerCase().includes(q) || i.InventoryNumber?.toLowerCase().includes(q))
        || location.Rooms?.some(r => r.Title?.toLowerCase().includes(q))
      if (!hit) continue
      let current = location
      while (current && !ids.has(current.ID)) {
        ids.add(current.ID)
        current = current.ParentID ? byId.value.get(current.ParentID) : null
      }
    }
    return ids
  })

  const creatableOrgs = computed(() => organizations.value.filter(o => o.CanCreate))
  // Private Lagerpunkte kann jeder anlegen, der in einer Organisation mit Inventar ist
  const canCreatePrivate = computed(() => organizations.value.length > 0)

  function applyResponse(response) {
    locations.value = response.locations || []
    unassigned.value = response.unassigned || []
    organizations.value = response.organizations || []
  }

  async function fetchLocations(forceRefresh = false) {
    try {
      error.value = null
      if (forceRefresh) {
        await clearCacheForEndpoint('/storage')
        loading.value = locations.value.length === 0
        applyResponse(await apiGet('/storage', false))
        return
      }
      loading.value = locations.value.length === 0
      const { data } = await apiGetSWR('/storage', applyResponse, 2 * 60 * 1000)
      applyResponse(data)
    } catch (err) {
      console.error('Failed to fetch storage locations:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function createLocation(data) {
    const response = await apiPost('/storage/store', data)
    if (response.success) await fetchLocations(true)
    return response
  }

  async function updateLocation(id, data) {
    const response = await apiPut(`/storage/update/${id}`, data)
    if (response.success) await fetchLocations(true)
    return response
  }

  async function deleteLocation(id) {
    const response = await apiDelete(`/storage/remove/${id}`)
    if (response.success) await fetchLocations(true)
    return response
  }

  /**
   * Drag & Drop: Lagerpunkt in parentId (null = oberste Ebene) verschieben; orderedIds
   * ist die neue Reihenfolge dieser Ebene (inkl. des verschobenen). Sofort lokal
   * übernommen, bei einem Fehler wird neu geladen.
   */
  async function moveLocation(id, parentId, orderedIds) {
    const order = new Map(orderedIds.map((locId, index) => [locId, index]))
    locations.value = locations.value.map(location => {
      if (location.ID === id) return { ...location, ParentID: parentId, SortOrder: order.get(id) }
      return order.has(location.ID) ? { ...location, SortOrder: order.get(location.ID) } : location
    })
    try {
      const response = await apiPost(`/storage/move/${id}`, { ParentID: parentId ?? 0, OrderedIDs: orderedIds })
      if (!response.success) await fetchLocations(true)
      else await clearCacheForEndpoint('/storage')
      return response
    } catch (err) {
      await fetchLocations(true)
      return { success: false, error: err.message }
    }
  }

  /**
   * Drag & Drop: Objekt/Fahrzeug (kind 'item'/'vehicle') oder Raum ('room') in einen
   * Lagerpunkt legen (locationId null = "Nicht einsortiert"). In einen Lagerpunkt sofort
   * lokal übernommen; zurück nach "Nicht einsortiert" lädt neu (Gruppe nach Besitzer).
   */
  async function placeEntity(kind, entity, locationId) {
    const listKey = kind === 'room' ? 'Rooms' : 'Items'
    const strip = list => (list ?? []).filter(e => e.ID !== entity.ID)
    if (locationId) {
      locations.value = locations.value.map(location => {
        const before = location[listKey] ?? []
        const after = location.ID === locationId ? [...strip(before), entity] : strip(before)
        return after.length === before.length && location.ID !== locationId ? location : { ...location, [listKey]: after }
      })
      unassigned.value = unassigned.value
        .map(group => ({ ...group, [listKey]: strip(group[listKey]) }))
        .filter(group => group.Items.length || group.Rooms.length)
    }
    try {
      const response = await apiPost('/storage/place', {
        Kind: kind === 'room' ? 'room' : 'item',
        ID: entity.ID,
        StorageLocationID: locationId ?? 0,
      })
      if (!response.success || !locationId) await fetchLocations(true)
      else await clearCacheForEndpoint('/storage')
      return response
    } catch (err) {
      await fetchLocations(true)
      return { success: false, error: err.message }
    }
  }

  /** Öffentlichen Teilen-Link (auch fürs NFC-Tag) anlegen bzw. mit revoke deaktivieren → { data: { url } } */
  async function shareLocation(id, revoke = false) {
    const response = await apiPost(`/storage/share/${id}`, { Revoke: revoke })
    if (response.success) await clearCacheForEndpoint('/storage')
    return response
  }

  /** Seite hinter dem Teilen-Link, auch ohne Anmeldung ({ isMember, location }) */
  async function fetchPublicLocation(token) {
    return apiGet(`/storage/public/${encodeURIComponent(token)}`, false)
  }

  function setSearchFilter(q) { filterSearch.value = q }

  return {
    locations,
    unassigned,
    organizations,
    loading,
    error,
    filterSearch,
    rootLocations,
    matchingIds,
    creatableOrgs,
    canCreatePrivate,
    byId,
    childrenOf,
    pathOf,
    descendantIds,
    totalContents,
    flatTree,
    fetchLocations,
    createLocation,
    updateLocation,
    deleteLocation,
    moveLocation,
    placeEntity,
    shareLocation,
    fetchPublicLocation,
    setSearchFilter,
  }
})
