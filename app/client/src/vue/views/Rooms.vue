<template>
  <div class="section section--RoomsPage">
    <div class="section_content">

      <div class="rooms-toolbar">
        <input
          type="search"
          class="rooms-toolbar_search input"
          placeholder="Räume suchen…"
          :value="store.filterSearch"
          @input="store.setSearchFilter($event.target.value)"
        />

        <select
          class="rooms-toolbar_select input"
          :value="store.filterOrganization?.ID ?? ''"
          @change="onOrgChange($event.target.value)"
        >
          <option value="">Alle Organisationen</option>
          <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
        </select>

        <AppButton variant="primary" @click="formModal?.open()">+ Neuer Raum</AppButton>
      </div>

      <div v-if="store.loading" class="section_infobox">
        <p>Lade Räume…</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.refresh()">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="store.filteredRooms.length === 0" class="section_infobox">
        <p>Keine Räume gefunden.</p>
      </div>

      <ul v-else class="rooms-list">
        <li v-for="room in store.filteredRooms" :key="room.ID">
          <button type="button" class="room-entry" @click="detailModal?.open(room.ID)">
            <div class="room-entry_info">
              <h3 class="room-entry_title">{{ room.Title }}</h3>
              <p v-if="room.Description" class="room-entry_description">{{ room.Description }}</p>
            </div>
            <span v-if="room.Organization" class="room-entry_org">{{ room.Organization.Title }}</span>
          </button>
        </li>
      </ul>

    </div>

    <RoomFormModal ref="formModal" @saved="onRoomSaved" />
    <RoomDetailModal ref="detailModal" @edit="onEditRoom" @deleted="onRoomDeleted" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/AppButton.vue'
import RoomFormModal from '@components/RoomFormModal.vue'
import RoomDetailModal from '@components/RoomDetailModal.vue'

usePageHeaderStore().setHeader('Räume', 'Räume deiner Organisationen und ihre Aufgaben.')

const store = useRoomsStore()
const formModal = ref(null)
const detailModal = ref(null)

function onOrgChange(value) {
  if (!value) {
    store.setOrganizationFilter(null)
    return
  }
  const org = store.organizations.find(o => o.ID === parseInt(value))
  store.setOrganizationFilter(org ?? null)
}

function onRoomSaved() {
  store.fetchRooms(true)
}

function onRoomDeleted() {
  store.fetchRooms(true)
}

async function onEditRoom(room) {
  detailModal.value?.close()
  const detail = await store.fetchRoomDetail(room.ID)
  if (detail) formModal.value?.openForEdit(detail)
}

onMounted(() => {
  store.fetchRooms()
})
</script>
