import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiGetSWR, apiPost, apiPut, apiDelete, apiPostForm, clearCacheForEndpoint } from '@utils/api'

export const RENTAL_STATUS_LABELS = {
  requested:   'Beantragt',
  approved:    'Genehmigt',
  rejected:    'Abgelehnt',
  handed_over: 'Übergeben',
  returned:    'Zurückgegeben',
  cancelled:   'Storniert',
}

export const CONDITION_OPTIONS = [
  { value: 'free',        label: 'Frei nutzbar' },
  { value: 'conditional', label: 'Nur unter Bedingung' },
  { value: 'do_not_use',  label: 'Nicht benutzen' },
]

export const useInventoryStore = defineStore('inventory', () => {
  const items = ref([])
  const types = ref([])
  const organizations = ref([])
  // Wählbare Formate für Zusatzfelder der Arten ({ value, label, unit, input })
  const fieldFormats = ref([])
  const statuses = ref([])
  const pendingRentals = ref(0)
  const loading = ref(false)
  const error = ref(null)

  const rentals = ref([])
  const rentalsLoading = ref(false)
  const rentalsError = ref(null)

  const filterSearch = ref('')
  // Welcher Tab: 'item' (Objekte) oder 'vehicle' (Fahrzeuge)
  const filterKind = ref('item')
  // null = alle, 'private' = privates Equipment, 'mine' = eigenes privates Equipment, Zahl = Organisations-ID
  const filterOrganization = ref(null)
  const filterType = ref(null)
  const filterStatus = ref(null)

  const filteredItems = computed(() => {
    let result = items.value.filter(i => (i.Kind || 'item') === filterKind.value)

    if (filterOrganization.value === 'private') {
      result = result.filter(i => i.IsPrivate)
    } else if (filterOrganization.value === 'mine') {
      result = result.filter(i => i.IsMine)
    } else if (filterOrganization.value) {
      // Eigenes Inventar der Organisation plus Org-Objekte, die andere Organisationen für sie freigegeben haben
      result = result.filter(i => !i.IsPrivate && i.RentableOrgIDs?.includes(filterOrganization.value))
    }
    if (filterType.value) {
      result = result.filter(i => i.TypeID === filterType.value)
    }
    if (filterStatus.value === 'rented_out') {
      result = result.filter(i => i.IsRentedOut)
    } else if (filterStatus.value) {
      result = result.filter(i => i.Status === filterStatus.value)
    } else {
      // Ausgemusterte Objekte tauchen in der Liste nur über den Zustandsfilter auf
      // (in der Gruppenansicht stehen sie weiterhin, siehe itemsOfGroup())
      result = result.filter(i => i.Status !== 'retired')
    }

    const q = filterSearch.value.trim().toLowerCase()
    if (q) {
      result = result.filter(i =>
        i.Title?.toLowerCase().includes(q)
        || i.InventoryNumber?.toLowerCase().includes(q)
        || i.Description?.toLowerCase().includes(q)
        || i.Owner?.Name?.toLowerCase().includes(q)
      )
    }

    return result
  })

  // Gleiche Objekte (gleicher GroupKey) zusammengefasst, in der Reihenfolge der gefilterten Liste
  const groupedItems = computed(() => {
    const groups = new Map()
    for (const item of filteredItems.value) {
      if (!groups.has(item.GroupKey)) groups.set(item.GroupKey, { key: item.GroupKey, items: [] })
      groups.get(item.GroupKey).items.push(item)
    }
    return [...groups.values()].map(group => {
      group.items.sort((a, b) => (a.InventoryNumber || '').localeCompare(b.InventoryNumber || '', 'de', { numeric: true }))
      return { ...group, first: group.items[0] }
    })
  })

  /** Alle geladenen Objekte einer Gruppe (unabhängig vom Filter, ausgemusterte am Ende) */
  function itemsOfGroup(groupKey) {
    return items.value
      .filter(i => i.GroupKey === groupKey)
      .sort((a, b) =>
        ((a.Status === 'retired') - (b.Status === 'retired'))
        || (a.InventoryNumber || '').localeCompare(b.InventoryNumber || '', 'de', { numeric: true })
      )
  }

  // Arten gelten für Objekte, Fahrzeuge oder Räume (AppliesTo)
  const itemTypes = computed(() => types.value.filter(t => (t.AppliesTo || 'item') === 'item'))
  const vehicleTypes = computed(() => types.value.filter(t => t.AppliesTo === 'vehicle'))
  const roomTypes = computed(() => types.value.filter(t => t.AppliesTo === 'room'))
  // Arten des aktuellen Tabs (Objekte bzw. Fahrzeuge)
  const vehicleCount = computed(() => items.value.filter(i => i.Kind === 'vehicle').length)
  const kindTypes = computed(() => (filterKind.value === 'vehicle' ? vehicleTypes.value : itemTypes.value))

  // Arten des Tabs passend zum Organisationsfilter (für das Filter-Dropdown); privates
  // Equipment kann Arten aller Organisationen nutzen
  const filterableTypes = computed(() =>
    typeof filterOrganization.value === 'number'
      ? kindTypes.value.filter(t => t.OrganizationID === filterOrganization.value)
      : kindTypes.value
  )

  const creatableOrgs = computed(() => organizations.value.filter(o => o.CanCreate))
  // Privates Equipment kann jeder eintragen, der in einer Organisation mit Inventar ist
  const canCreatePrivate = computed(() => organizations.value.length > 0)
  const manageableTypeOrgs = computed(() => organizations.value.filter(o => o.CanManageTypes))
  const requestableOrgs = computed(() => organizations.value.filter(o => o.CanRequest))

  /** Arten einer Organisation, für Objekte ('item'), Fahrzeuge ('vehicle') oder Räume ('room') */
  function typesForOrg(orgId, appliesTo = 'item') {
    return types.value.filter(t => t.OrganizationID === orgId && (t.AppliesTo || 'item') === appliesTo)
  }

  function itemCountForType(typeId) {
    return items.value.filter(i => i.TypeID === typeId).length
  }

  /**
   * Vorausgewählter Kontext beim Ausleihen: eine Organisation, in der man das Objekt
   * beantragen (bzw. als Besitzer verleihen) darf — sonst "privat", falls erlaubt.
   */
  function rentalContextFor(item) {
    return item.RentableOrgIDs?.find(id => orgById(id)?.CanRequest || item.IsMine)
      ?? (item.CanRentPrivately ? 'private' : item.RentableOrgIDs?.[0])
  }

  function orgById(orgId) {
    return organizations.value.find(o => o.ID === orgId) ?? null
  }

  function applyIndexResponse(response) {
    items.value = response.items || []
    types.value = response.types || []
    organizations.value = response.organizations || []
    fieldFormats.value = response.fieldFormats || []
    statuses.value = response.statuses || []
    pendingRentals.value = response.pendingRentals || 0
  }

  async function fetchItems(forceRefresh = false) {
    try {
      error.value = null
      loading.value = items.value.length === 0

      if (forceRefresh) {
        await clearCacheForEndpoint('/inventory')
        applyIndexResponse(await apiGet('/inventory', false))
        return
      }

      const { data } = await apiGetSWR('/inventory', applyIndexResponse, 2 * 60 * 1000)
      applyIndexResponse(data)
    } catch (err) {
      console.error('Failed to fetch inventory:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchItemDetail(id) {
    try {
      const response = await apiGet(`/inventory/detail/${id}`, false)
      return response.item || null
    } catch (err) {
      console.error('Failed to fetch inventory item:', err)
      return null
    }
  }

  function replaceItem(item) {
    const idx = items.value.findIndex(i => i.ID === item.ID)
    if (idx !== -1) items.value[idx] = { ...items.value[idx], ...item }
    else items.value.unshift(item)
  }

  async function createItem(data) {
    const response = await apiPost('/inventory/store', data)
    if (response.success && response.data?.item) {
      replaceItem(response.data.item)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function updateItem(id, data) {
    const response = await apiPut(`/inventory/update/${id}`, data)
    if (response.success && response.data?.item) {
      replaceItem(response.data.item)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function deleteItem(id) {
    const response = await apiDelete(`/inventory/remove/${id}`)
    if (response.success) {
      items.value = items.value.filter(i => i.ID !== id)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  // applyToGroup: Dateien an alle gleichen Objekte hängen
  async function uploadFiles(id, { images = [], documents = [], applyToGroup = false }) {
    if (!images.length && !documents.length) return { success: true }
    const formData = new FormData()
    if (applyToGroup) formData.append('ApplyToGroup', '1')
    images.forEach(f => formData.append('images[]', f))
    documents.forEach(f => formData.append('documents[]', f))
    const response = await apiPostForm(`/inventory/uploadFiles/${id}`, formData)
    if (response.data?.item) {
      replaceItem(response.data.item)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function removeFile(id, fileId, applyToGroup = false) {
    const response = await apiPost(`/inventory/removeFile/${id}`, { FileID: fileId, ApplyToGroup: applyToGroup })
    if (response.success && response.data?.item) {
      replaceItem(response.data.item)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  /** Öffentlichen Teilen-Link anlegen (bzw. vorhandenen holen) oder mit revoke deaktivieren */
  async function shareItem(itemId, revoke = false) {
    return apiPost(`/inventory/share/${itemId}`, { Revoke: revoke })
  }

  /** Öffentliche Ansicht über den Teilen-Link ({ isMember, item }) */
  async function fetchPublicItem(token) {
    return apiGet(`/inventory/public/${encodeURIComponent(token)}`, false)
  }

  // Weitere gleiche Objekte mit fortlaufenden Nummern anlegen
  async function duplicateItem(itemId, count) {
    const response = await apiPost(`/inventory/duplicate/${itemId}`, { Count: count })
    if (response.success) {
      await clearCacheForEndpoint('/inventory')
      await fetchItems(true)
    }
    return response
  }

  async function createType(data) {
    const response = await apiPost('/inventory/typeStore', data)
    if (response.success && response.data?.type) {
      types.value = [...types.value, response.data.type].sort((a, b) => a.Title.localeCompare(b.Title))
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function updateType(id, data) {
    const response = await apiPut(`/inventory/typeUpdate/${id}`, data)
    if (response.success && response.data?.type) {
      const idx = types.value.findIndex(t => t.ID === id)
      if (idx !== -1) types.value[idx] = response.data.type
      // Aktivierte Felder der Objekte dieser Art ändern sich mit
      items.value = items.value.map(i => (i.TypeID === id ? { ...i, Fields: response.data.type.Fields, Type: { ID: id, Title: response.data.type.Title } } : i))
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function deleteType(id) {
    const response = await apiDelete(`/inventory/typeRemove/${id}`)
    if (response.success) {
      types.value = types.value.filter(t => t.ID !== id)
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  // ---- Ausleihen ----

  async function fetchRentals(forceRefresh = false) {
    try {
      rentalsError.value = null
      rentalsLoading.value = rentals.value.length === 0
      if (forceRefresh) await clearCacheForEndpoint('/inventory/rentals')
      const response = await apiGet('/inventory/rentals', !forceRefresh)
      rentals.value = response.rentals || []
      if (response.organizations) organizations.value = response.organizations
    } catch (err) {
      console.error('Failed to fetch rentals:', err)
      rentalsError.value = err.message
    } finally {
      rentalsLoading.value = false
    }
  }

  async function fetchRentalDetail(id) {
    try {
      const response = await apiGet(`/inventory/rentalDetail/${id}`, false)
      return response.rental || null
    } catch (err) {
      console.error('Failed to fetch rental:', err)
      return null
    }
  }

  async function fetchRentalOptions(orgId, start, end) {
    const params = new URLSearchParams({ organization: orgId })
    if (start) params.set('start', start)
    if (end) params.set('end', end)
    try {
      return await apiGet(`/inventory/rentalOptions?${params.toString()}`, false)
    } catch (err) {
      console.error('Failed to fetch rental options:', err)
      return { groups: [], rooms: [], busyRoomIDs: [], events: [] }
    }
  }

  async function afterRentalChange(response) {
    if (response.success && response.data?.rental) {
      // Ein Antrag kann beim Anlegen in mehrere (pro Quelle) aufgeteilt werden
      for (const rental of response.data.rentals || [response.data.rental]) {
        const idx = rentals.value.findIndex(r => r.ID === rental.ID)
        if (idx !== -1) rentals.value[idx] = rental
        else rentals.value.unshift(rental)
      }
      await clearCacheForEndpoint('/inventory')
    }
    return response
  }

  async function createRental(data) {
    return afterRentalChange(await apiPost('/inventory/rentalStore', data))
  }

  async function decideRental(id, data) {
    const response = await afterRentalChange(await apiPost(`/inventory/rentalDecide/${id}`, data))
    if (response.success && pendingRentals.value > 0) pendingRentals.value--
    return response
  }

  async function swapRentalItem(rentalId, itemId, newItemId) {
    return afterRentalChange(await apiPost(`/inventory/rentalSwapItem/${rentalId}`, { ItemID: itemId, NewItemID: newItemId }))
  }

  /**
   * Schaden zu einer Ausleihe melden (multipart, bis zu 4 Fotos).
   * @param {{ TargetType: 'item'|'room', TargetID: number, OccurredAt: string, Description: string, MakesUnusable: boolean, images: File[] }} data
   */
  async function reportDamage(rentalId, { images = [], ...fields }) {
    const formData = new FormData()
    Object.entries(fields).forEach(([key, value]) => formData.append(key, value === true ? '1' : value === false ? '0' : value))
    images.forEach(file => formData.append('images[]', file))
    const response = await apiPostForm(`/inventory/damageStore/${rentalId}`, formData)
    // Auch bei Teilfehlern (z.B. ein Foto zu groß) ist die Meldung gespeichert
    if (response.data?.rental) await afterRentalChange({ success: true, data: { rental: response.data.rental } })
    return response
  }

  /** Schaden als behoben markieren ({ ResolvedAt, Note, Restore }) */
  async function resolveDamage(damageId, data) {
    const response = await apiPost(`/inventory/damageResolve/${damageId}`, data)
    if (response.success) await clearCacheForEndpoint('/inventory')
    return response
  }

  /** @param mileages optionaler Kilometerstand je Fahrzeug ({ "<Objekt-ID>": km }) bei Übergabe/Rückgabe */
  async function setRentalStatus(id, status, mileages = {}) {
    return afterRentalChange(await apiPost(`/inventory/rentalStatus/${id}`, { Status: status, Mileages: mileages }))
  }

  function setSearchFilter(q) { filterSearch.value = q }
  function setKindFilter(kind) {
    filterKind.value = kind
    // Der Arten-Filter gehört zum Tab
    if (filterType.value && !kindTypes.value.some(t => t.ID === filterType.value)) filterType.value = null
  }
  function setOrganizationFilter(orgId) {
    filterOrganization.value = orgId
    if (filterType.value && !filterableTypes.value.some(t => t.ID === filterType.value)) {
      filterType.value = null
    }
  }
  function setTypeFilter(typeId) { filterType.value = typeId }
  function setStatusFilter(status) { filterStatus.value = status }

  return {
    items,
    types,
    organizations,
    fieldFormats,
    statuses,
    pendingRentals,
    loading,
    error,
    rentals,
    rentalsLoading,
    rentalsError,
    filterSearch,
    filterOrganization,
    filterType,
    filterStatus,
    filteredItems,
    filterableTypes,
    creatableOrgs,
    canCreatePrivate,
    manageableTypeOrgs,
    requestableOrgs,
    typesForOrg,
    itemTypes,
    vehicleTypes,
    roomTypes,
    kindTypes,
    vehicleCount,
    filterKind,
    setKindFilter,
    itemCountForType,
    itemsOfGroup,
    rentalContextFor,
    orgById,
    fetchItems,
    fetchItemDetail,
    createItem,
    updateItem,
    deleteItem,
    uploadFiles,
    removeFile,
    duplicateItem,
    shareItem,
    fetchPublicItem,
    swapRentalItem,
    groupedItems,
    createType,
    updateType,
    deleteType,
    fetchRentals,
    fetchRentalDetail,
    fetchRentalOptions,
    createRental,
    decideRental,
    setRentalStatus,
    reportDamage,
    resolveDamage,
    setSearchFilter,
    setOrganizationFilter,
    setTypeFilter,
    setStatusFilter,
  }
})
