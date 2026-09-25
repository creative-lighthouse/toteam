<template>
  <div class="section section--InventoryPage">
    <div class="section_content">

      <div class="inventory-page_topbar">
        <div class="inventory-page_tabs" role="tablist">
          <button
            v-for="t in TABS"
            :key="t.id"
            type="button"
            role="tab"
            class="inventory-page_tab"
            :class="{ 'inventory-page_tab--active': tab === t.id }"
            :aria-selected="tab === t.id"
            @click="setTab(t.id)"
          >
            <span class="icon-mask" :style="t.iconStyle" aria-hidden="true" />
            <span>{{ t.label }}</span>
          </button>
        </div>
        <div class="inventory-page_topbar-actions">
          <AppButton variant="secondary" class="inventory-page_rentals-button" @click="router.push({ name: 'InventoryRentals' })">
            Anträge
            <span v-if="store.pendingRentals" class="inventory-page_pending-badge" :title="`${store.pendingRentals} offene Anträge`">{{ store.pendingRentals }}</span>
          </AppButton>
          <AppButton v-if="store.organizations.length" variant="primary" @click="rentalModal?.open()">Ausleihen</AppButton>
        </div>
      </div>

      <InventoryRoomsTab v-if="tab === 'rooms'" ref="roomsTab" @reserve="onReserveRoom" @manage-types="typeManagerModal?.open('room')" @scan-nfc="scanModal?.open()" />

      <template v-else>
        <AppSearchBar
          :model-value="store.filterSearch"
:placeholder="kind === 'vehicle' ? 'Name, Kennzeichen/Nummer oder Besitzer suchen…' : 'Name, Inventarnummer oder Besitzer suchen…'"
          @update:model-value="store.setSearchFilter"
        >
          <template #actions>
            <AppIconButton
              v-if="canManageTypes"
              variant="neutral"
              aria-label="Arten verwalten"
              title="Arten verwalten"
              @click="typeManagerModal?.open(kind)"
            >
              <span class="icon-mask" :style="typesIconStyle" />
            </AppIconButton>
            <AppIconButton v-if="nfcSupported" variant="neutral" aria-label="NFC-Tag scannen" title="NFC-Tag scannen" @click="scanModal?.open()">
              <span class="icon-mask" :style="nfcIconStyle" />
            </AppIconButton>
            <AppButton v-if="store.canCreatePrivate" variant="primary" @click="itemFormModal?.open({ kind })">+ Neu</AppButton>
          </template>
          <template #filters>
            <select :value="store.filterOrganization ?? ''" @change="onOwnerFilterChange($event.target.value)">
              <option value="">Alle Besitzer</option>
              <optgroup v-if="store.organizations.length" label="Organisationen">
                <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
              </optgroup>
              <optgroup label="Privates Equipment">
                <option value="private">Privat (alle Mitglieder)</option>
                <option value="mine">Nur meine</option>
              </optgroup>
            </select>
            <select
              v-if="store.filterableTypes.length"
              :value="store.filterType ?? ''"
              @change="store.setTypeFilter($event.target.value ? parseInt($event.target.value) : null)"
            >
              <option value="">Alle Arten</option>
              <option v-for="type in store.filterableTypes" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
            </select>
            <select
              :value="store.filterStatus ?? ''"
              @change="store.setStatusFilter($event.target.value || null)"
            >
              <option value="">Alle Zustände</option>
              <option value="rented_out">Ausgeliehen</option>
              <option v-for="s in store.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </template>
        </AppSearchBar>

        <div v-if="store.loading" class="section_infobox">
          <p>Lade Inventar…</p>
        </div>

        <div v-else-if="store.error" class="section_infobox error">
          <p>Fehler: {{ store.error }}</p>
          <AppButton variant="primary" @click="store.fetchItems(true)">Erneut versuchen</AppButton>
        </div>

        <div v-else-if="!store.organizations.length" class="section_infobox">
          <p>In deinen Organisationen ist das Inventar nicht aktiviert.</p>
        </div>

        <div v-else-if="store.filteredItems.length === 0" class="section_infobox">
          <p v-if="kind === 'vehicle'">{{ store.vehicleCount ? 'Keine passenden Fahrzeuge gefunden.' : 'Noch keine Fahrzeuge im Inventar.' }}</p>
          <p v-else>{{ store.items.length ? 'Keine passenden Objekte gefunden.' : 'Noch keine Objekte im Inventar.' }}</p>
        </div>

        <ul v-else class="inventory-list">
          <li v-for="group in store.groupedItems" :key="group.key">
            <button type="button" class="inventory-entry" @click="openGroup(group)">
              <img v-if="group.first.Thumbnail" :src="group.first.Thumbnail" :alt="group.first.Title" class="inventory-entry_thumb" loading="lazy">
              <span v-else class="inventory-entry_thumb inventory-entry_thumb--placeholder" aria-hidden="true">{{ initials(group.first.Title) }}</span>

              <div class="inventory-entry_info">
                <div class="inventory-entry_headline">
                  <span class="inventory-entry_number">{{ numberLabel(group.items) }}</span>
                  <h3 class="inventory-entry_title">{{ group.first.Title }}</h3>
                </div>
                <p class="inventory-entry_meta">
                  <span v-if="group.first.Type">{{ group.first.Type.Title }}</span>
                  <span v-for="fact in listFacts(group.items)" :key="fact" class="inventory-entry_fact">· {{ fact }}</span>
                  <span v-if="group.first.IsPrivate" class="inventory-entry_private">· {{ group.first.IsMine ? 'Privat (du)' : `Privat: ${group.first.Owner.Name}` }}</span>
                  <span v-else-if="group.first.Organization && !store.orgById(group.first.OrganizationID)" class="inventory-entry_private">· von {{ group.first.Organization.Title }}</span>
                  <span v-else-if="store.organizations.length > 1 && group.first.Organization">· {{ group.first.Organization.Title }}</span>
                </p>
              </div>

              <span class="inventory-entry_states">
                <span
                  v-for="badge in groupBadges(group.items)"
                  :key="badge.status"
                  class="inventory-state-icon"
                  :class="`inventory-state-icon--${badge.status}`"
                  :title="badge.label"
                  role="img"
                  :aria-label="badge.label"
                >
                  <span class="icon-mask" :style="stateIconStyle(badge.status)" />
                  <span v-if="group.items.length > 1" class="inventory-state-icon_count">{{ badge.count }}</span>
                </span>
              </span>
              <!-- Immer ganz rechts und gleich breit, damit die Anzahl in jeder Zeile an derselben Stelle steht -->
              <span class="inventory-entry_quantity">{{ group.items.length > 1 ? `${group.items.length}×` : '' }}</span>
            </button>
          </li>
        </ul>
      </template>

    </div>

    <InventoryItemFormModal ref="itemFormModal" @saved="onItemSaved" />
    <InventoryItemDetailModal
      ref="detailModal"
      @edit="onEditItem"
      @deleted="detailModal?.close()"
      @rent="onRentItem"
      @show-group="item => groupModal?.open(item.GroupKey)"
    />
    <InventoryGroupModal
      ref="groupModal"
      @open-item="item => detailModal?.open(item.ID)"
      @edit-group="onEditGroup"
      @rent="onRentGroup"
    />
    <InventoryRentalRequestModal ref="rentalModal" @saved="onRentalSaved" />
    <InventoryTypeManagerModal ref="typeManagerModal" />
    <ScanNFCModal ref="scanModal" @scanned="onNfcScanned" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import InventoryItemFormModal from '@components/inventory/InventoryItemFormModal.vue'
import InventoryItemDetailModal from '@components/inventory/InventoryItemDetailModal.vue'
import InventoryRentalRequestModal from '@components/inventory/InventoryRentalRequestModal.vue'
import InventoryTypeManagerModal from '@components/inventory/InventoryTypeManagerModal.vue'
import InventoryRoomsTab from '@components/inventory/InventoryRoomsTab.vue'
import InventoryGroupModal from '@components/inventory/InventoryGroupModal.vue'
import { formatFieldValue, groupStatusBadges, numberRangeLabel } from '@utils/inventory'
import actionPages from '../../../icons/actions/action_pages.svg'
import actionInventory from '../../../icons/actions/action_inventory.svg'
import actionCar from '../../../icons/actions/action_car.svg'
import actionRoom from '../../../icons/actions/action_room.svg'
import actionNfc from '../../../icons/actions/action_nfc.svg'
import { isNfcSupported, parseShareLink } from '@utils/nfc'
import ScanNFCModal from '@components/ui/ScanNFCModal.vue'
import { useRoomsStore } from '@stores/rooms'
import stateBroken from '../../../icons/states/state_broken.svg'
import stateRepair from '../../../icons/states/state_repair.svg'
// Noch kein eigenes Status-Icon für "ausgeliehen" — bei Bedarf hier austauschen
import stateRentedOut from '../../../icons/actions/action_forward.svg'

usePageHeaderStore().setHeader('Inventar', 'Objekte und Räume deiner Organisationen – ausleihen und reservieren.')

const maskStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })

const TABS = [
  { id: 'items', label: 'Objekte', iconStyle: maskStyle(actionInventory) },
  { id: 'vehicles', label: 'Fahrzeuge', iconStyle: maskStyle(actionCar) },
  { id: 'rooms', label: 'Räume', iconStyle: maskStyle(actionRoom) },
]

const typesIconStyle = { maskImage: `url("${actionPages}")`, WebkitMaskImage: `url("${actionPages}")` }

const route = useRoute()
const router = useRouter()
const store = useInventoryStore()
const itemFormModal = ref(null)
const detailModal = ref(null)
const rentalModal = ref(null)
const typeManagerModal = ref(null)
const groupModal = ref(null)
const roomsTab = ref(null)
const roomsStore = useRoomsStore()

// NFC: gescannte ToTeam-Links (Objekt oder Raum) öffnen direkt das passende Modal
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }
const nfcSupported = isNfcSupported()
const scanModal = ref(null)

/** Vom ScanNFCModal gelesener Link — bei Fehlern bleibt das Modal offen und scannt weiter */
async function onNfcScanned(url) {
  const link = url ? parseShareLink(url) : null
  if (!link) {
    scanModal.value?.showError('Auf diesem Tag ist kein ToTeam-Link gespeichert.')
    return
  }
  try {
    if (link.kind === 'item') {
      const response = await store.fetchPublicItem(link.token)
      if (!response?.isMember) {
        scanModal.value?.showError(response?.item ? 'Auf dieses Objekt hast du keinen Zugriff.' : 'Dieser Link ist ungültig oder wurde deaktiviert.')
        return
      }
      scanModal.value?.close()
      const itemTab = response.item.Kind === 'vehicle' ? 'vehicles' : 'items'
      if (tab.value !== itemTab) setTab(itemTab)
      detailModal.value?.open(response.item.ID)
    } else {
      const response = await roomsStore.fetchPublicRoom(link.token)
      if (!response?.isMember) {
        scanModal.value?.showError(response?.room ? 'Auf diesen Raum hast du keinen Zugriff.' : 'Dieser Link ist ungültig oder wurde deaktiviert.')
        return
      }
      scanModal.value?.close()
      if (tab.value !== 'rooms') setTab('rooms')
      await nextTick()
      roomsTab.value?.openRoom(response.room.ID)
    }
  } catch {
    scanModal.value?.showError('Der gescannte Link konnte nicht geöffnet werden.')
  }
}

// Aktiver Tab steht in der URL (?tab=rooms), damit z.B. der Link aus dem Lageplan direkt die Räume zeigt
const tab = computed(() => (['rooms', 'vehicles'].includes(route.query.tab) ? route.query.tab : 'items'))

// Objekte und Fahrzeuge teilen sich Liste und Modals — der Tab bestimmt die Art
const kind = computed(() => (tab.value === 'vehicles' ? 'vehicle' : 'item'))
watch(kind, value => store.setKindFilter(value), { immediate: true })
const canManageTypes = computed(() => store.organizations.some(o => o.CanManageTypes))

function setTab(id) {
  router.replace({ query: { ...route.query, tab: id === 'items' ? undefined : id } })
}

function initials(title) {
  return (title || '?').trim().slice(0, 2).toUpperCase()
}

function numberLabel(items) {
  return numberRangeLabel(items.map(i => i.InventoryNumber))
}

/**
 * Werte der Felder mit "In Listenansicht" (z.B. "5 m"). Bei Gruppen nur, wenn alle
 * Objekte denselben Wert haben — sonst "–" wäre irreführend, also weglassen.
 */
function listFacts(items) {
  // Einzelnes Fahrzeug: Kilometerstand mit anzeigen
  const mileage = items.length === 1 && items[0].Kind === 'vehicle' && items[0].Mileage
    ? [`${new Intl.NumberFormat('de-DE').format(items[0].Mileage)} km`]
    : []
  const fields = (items[0].Fields || []).filter(f => f.ShowInList)
  return [...mileage, ...fields
    .map(field => {
      const values = new Set(items.map(i => i.Values?.[field.ID] ?? ''))
      if (values.size !== 1) return ''
      return formatFieldValue(field, [...values][0])
    })
    .filter(Boolean)]
}

function groupBadges(items) {
  return groupStatusBadges(items)
}

const STATE_ICONS = {
  defective:  stateBroken,
  in_repair:  stateRepair,
  rented_out: stateRentedOut,
}

function stateIconStyle(status) {
  const icon = STATE_ICONS[status] || stateBroken
  return { maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` }
}

function openGroup(group) {
  // Auch bei nur einem aktiven Objekt die Gruppe zeigen, wenn ausgemusterte dazugehören
  if (store.itemsOfGroup(group.key).length > 1) groupModal.value?.open(group.key)
  else detailModal.value?.open(group.first.ID)
}

function onItemSaved(item, { createdCount = 1 } = {}) {
  // Mehrere gleiche Objekte angelegt → die Gruppe zeigen, sonst das Objekt
  if (createdCount > 1 || store.itemsOfGroup(item.GroupKey).length > 1) groupModal.value?.open(item.GroupKey)
  else detailModal.value?.open(item.ID)
}

async function onEditGroup(item) {
  groupModal.value?.close()
  const detail = await store.fetchItemDetail(item.ID)
  if (detail) itemFormModal.value?.openForEdit(detail, { applyToGroup: true })
}

function onRentGroup(item, count) {
  groupModal.value?.close()
  rentalModal.value?.open({ organizationId: store.rentalContextFor(item), groups: { [item.GroupKey]: count } })
}

function onEditItem(item) {
  detailModal.value?.close()
  itemFormModal.value?.openForEdit(item)
}

function onRentItem(item) {
  detailModal.value?.close()
  const orgId = store.rentalContextFor(item)
  rentalModal.value?.open({ organizationId: orgId, groups: { [item.GroupKey]: 1 } })
}

function onOwnerFilterChange(value) {
  if (!value) store.setOrganizationFilter(null)
  else if (value === 'private' || value === 'mine') store.setOrganizationFilter(value)
  else store.setOrganizationFilter(parseInt(value))
}

function onReserveRoom(room) {
  rentalModal.value?.open({ organizationId: room.Organization?.ID, roomIds: [room.ID] })
}

function onRentalSaved(rental) {
  router.push({ name: 'InventoryRentals', params: { id: rental.ID } })
}

onMounted(() => {
  store.fetchItems()
})
</script>
