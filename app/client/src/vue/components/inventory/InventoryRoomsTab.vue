<template>
  <div class="inventory-rooms-tab">
    <AppSearchBar
      :model-value="store.filterSearch"
      placeholder="Name oder Beschreibung suchen…"
      @update:model-value="store.setSearchFilter"
    >
      <template #actions>
        <AppIconButton
          v-if="inventoryStore.manageableTypeOrgs.length"
          variant="neutral"
          aria-label="Raum-Arten verwalten"
          title="Raum-Arten verwalten"
          @click="emit('manage-types')"
        >
          <span class="icon-mask" :style="typesIconStyle" />
        </AppIconButton>
        <AppIconButton v-if="nfcSupported" variant="neutral" aria-label="NFC-Tag scannen" title="NFC-Tag scannen" @click="emit('scan-nfc')">
          <span class="icon-mask" :style="nfcIconStyle" />
        </AppIconButton>
        <AppButton variant="primary" @click="formModal?.open()">+ Neuer Raum</AppButton>
      </template>
      <template #filters>
        <select
          v-if="store.organizations.length > 1"
          :value="store.filterOrganization?.ID ?? ''"
          @change="onOrgChange($event.target.value)"
        >
          <option value="">Alle Organisationen</option>
          <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
        </select>
        <select
          v-if="inventoryStore.roomTypes.length"
          :value="store.filterType ?? ''"
          @change="store.setTypeFilter($event.target.value ? parseInt($event.target.value) : null)"
        >
          <option value="">Alle Arten</option>
          <option v-for="type in inventoryStore.roomTypes" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
        </select>
        <select :value="store.filterAvailability ?? ''" @change="store.setAvailabilityFilter($event.target.value || null)">
          <option value="">Alle Räume</option>
          <option value="rentable">Reservierbar</option>
          <option value="occupied">Heute belegt</option>
        </select>
      </template>
    </AppSearchBar>

    <div v-if="store.loading" class="section_infobox">
      <p>Lade Räume…</p>
    </div>

    <div v-else-if="store.error" class="section_infobox error">
      <p>Fehler: {{ store.error }}</p>
      <AppButton variant="primary" @click="store.refresh()">Erneut versuchen</AppButton>
    </div>

    <div v-else-if="store.filteredRooms.length === 0" class="section_infobox">
      <p>{{ store.rooms.length ? 'Keine passenden Räume gefunden.' : 'Noch keine Räume angelegt.' }}</p>
    </div>

    <!-- Gleiche Darstellung wie die Objektliste (Styles aus pages/InventoryPage.scss) -->
    <ul v-else class="inventory-list">
      <li v-for="room in store.filteredRooms" :key="room.ID">
        <button type="button" class="inventory-entry" @click="detailModal?.open(room.ID)">
          <img v-if="room.Thumbnail" :src="room.Thumbnail" :alt="room.Title" class="inventory-entry_thumb" loading="lazy">
          <span v-else class="inventory-entry_thumb inventory-entry_thumb--placeholder" aria-hidden="true">{{ initials(room.Title) }}</span>

          <div class="inventory-entry_info">
            <div class="inventory-entry_headline">
              <h3 class="inventory-entry_title">{{ room.Title }}</h3>
            </div>
            <p class="inventory-entry_meta">
              <span v-for="(part, i) in metaParts(room)" :key="i">{{ i ? '· ' : '' }}{{ part }}</span>
            </p>
          </div>

          <span v-if="room.IsOccupied" class="inventory-status-badge inventory-status-badge--rented_out">Heute belegt</span>
          <span v-else-if="room.IsRentable" class="inventory-status-badge inventory-status-badge--available">Reservierbar</span>
        </button>
      </li>
    </ul>

    <RoomFormModal ref="formModal" @saved="store.fetchRooms(true)" />
    <RoomDetailModal
      ref="detailModal"
      show-reserve
      @edit="onEditRoom"
      @deleted="store.fetchRooms(true)"
      @reserve="room => emit('reserve', room)"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import { useInventoryStore } from '@stores/inventory'
import { formatFieldValue } from '@utils/inventory'
import actionPages from '../../../../icons/actions/action_pages.svg'
import actionNfc from '../../../../icons/actions/action_nfc.svg'
import { isNfcSupported } from '@utils/nfc'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import RoomFormModal from '@components/rooms/RoomFormModal.vue'
import RoomDetailModal from '@components/rooms/RoomDetailModal.vue'

const emit = defineEmits(['reserve', 'manage-types', 'scan-nfc'])

const store = useRoomsStore()
const formModal = ref(null)
const detailModal = ref(null)

const inventoryStore = useInventoryStore()
const typesIconStyle = { maskImage: `url("${actionPages}")`, WebkitMaskImage: `url("${actionPages}")` }
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }
const nfcSupported = isNfcSupported()

// "Proberaum · 40 m² · Organisation · Beschreibung": Art, Felder mit "In Liste", Organisation, Beschreibung
function metaParts(room) {
  const facts = (room.Fields || [])
    .filter(field => field.ShowInList)
    .map(field => formatFieldValue(field, room.Values?.[field.ID]))
  return [
    room.Type?.Title,
    ...facts,
    store.organizations.length > 1 ? room.Organization?.Title : null,
    room.Description,
  ].filter(Boolean)
}

function initials(title) {
  return (title || '?').trim().slice(0, 2).toUpperCase()
}

function onOrgChange(value) {
  if (!value) {
    store.setOrganizationFilter(null)
    return
  }
  const org = store.organizations.find(o => o.ID === parseInt(value))
  store.setOrganizationFilter(org ?? null)
}

async function onEditRoom(room) {
  detailModal.value?.close()
  const detail = await store.fetchRoomDetail(room.ID)
  if (detail) formModal.value?.openForEdit(detail)
}

onMounted(() => {
  store.fetchRooms()
})

/** Raum-Detail von außen öffnen (z.B. nach einem NFC-Scan) */
function openRoom(id) {
  detailModal.value?.open(id)
}

defineExpose({ openRoom })
</script>
