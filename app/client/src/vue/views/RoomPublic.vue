<template>
  <div class="section section--InventoryItemPublicPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Raum…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
      </div>

      <!-- Gleiches Layout wie die geteilte Objektseite (pages/InventoryItemPublicPage.scss) -->
      <article v-else-if="room" class="inventory-public">
        <ul v-if="room.Images?.length" class="inventory-public_images">
          <li v-for="(img, index) in room.Images" :key="img.ID">
            <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(room.Images, index)">
              <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
            </a>
          </li>
        </ul>

        <div class="inventory-public_head">
          <span v-if="typeTitle" class="inventory-public_type">{{ typeTitle }}</span>
          <span v-if="isMember && room.IsOccupied" class="inventory-status-badge inventory-status-badge--rented_out">Heute belegt</span>
          <span v-else-if="isMember && room.IsRentable" class="inventory-status-badge inventory-status-badge--available">Reservierbar</span>
        </div>

        <p v-if="room.Description" class="inventory-public_description"><AppLinkifiedText :text="room.Description" /></p>

        <dl class="inventory-public_facts">
          <div v-if="orgTitle">
            <dt>Organisation</dt>
            <dd>{{ orgTitle }}</dd>
          </div>
          <div v-for="fact in facts" :key="fact.label">
            <dt>{{ fact.label }}</dt>
            <dd>{{ fact.value }}</dd>
          </div>
        </dl>

        <template v-if="isMember && room.Documents?.length">
          <h2 class="hl3 inventory-public_subtitle">Dokumente</h2>
          <ul class="inventory-public_documents">
            <li v-for="doc in room.Documents" :key="doc.ID">
              <a :href="doc.URL" target="_blank" rel="noopener">{{ doc.Name }}</a>
              <span v-if="doc.Size" class="inventory-public_doc-size">{{ formatFileSize(doc.Size) }}</span>
            </li>
          </ul>
        </template>

        <div class="inventory-public_actions">
          <template v-if="isMember">
            <AppButton variant="secondary" @click="router.push({ name: 'Inventory', query: { tab: 'rooms' } })">Zu den Räumen</AppButton>
            <AppButton v-if="room.CanEdit" variant="secondary" @click="formModal?.openForEdit(room)">Bearbeiten</AppButton>
            <AppButton v-if="room.CanReserve" variant="primary" @click="reserve">Reservieren</AppButton>
          </template>
          <template v-else-if="!authStore.isAuthenticated">
            <p class="inventory-public_login-hint">Mitglieder sehen nach der Anmeldung alle Details und können den Raum reservieren.</p>
            <AppButton variant="primary" @click="router.push({ name: 'Login', query: { redirect: route.fullPath } })">Anmelden</AppButton>
          </template>
          <p v-else class="inventory-public_login-hint">Du hast keinen Zugriff auf weitere Details dieses Raums.</p>
        </div>
      </article>

    </div>

    <AppLightbox ref="lightbox" />

    <template v-if="isMember">
      <RoomFormModal ref="formModal" @saved="load" />
      <InventoryRentalRequestModal ref="rentalModal" @saved="rental => router.push({ name: 'InventoryRentals', params: { id: rental.ID } })" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useRoomsStore } from '@stores/rooms'
import { useInventoryStore } from '@stores/inventory'
import { usePageHeaderStore } from '@stores/pageHeader'
import { formatFieldValue, formatFileSize } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import RoomFormModal from '@components/rooms/RoomFormModal.vue'
import InventoryRentalRequestModal from '@components/inventory/InventoryRentalRequestModal.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const store = useRoomsStore()
const inventoryStore = useInventoryStore()
const pageHeader = usePageHeaderStore()

const room = ref(null)
const isMember = ref(false)
const loading = ref(true)
const error = ref(null)
const lightbox = ref(null)
const formModal = ref(null)
const rentalModal = ref(null)

// Öffentliche Antwort: Art/Organisation als Text; Mitglieder-Antwort: volle Raumdaten
const typeTitle = computed(() => (isMember.value ? room.value?.Type?.Title : room.value?.Type))
const orgTitle = computed(() => (isMember.value ? room.value?.Organization?.Title : room.value?.Organization))

// Mitglieder sehen alle ausgefüllten Felder, sonst liefert der Server nur die öffentlichen (mit Wert)
const facts = computed(() =>
  (room.value?.Fields || [])
    .map(field => ({
      label: field.Label,
      value: formatFieldValue(field, isMember.value ? room.value.Values?.[field.ID] : field.Value),
    }))
    .filter(fact => fact.value !== '')
)

async function load() {
  loading.value = !room.value
  error.value = null
  try {
    const response = await store.fetchPublicRoom(route.params.token)
    if (!response?.room) {
      error.value = response?.error || 'Dieser Link ist ungültig oder wurde deaktiviert.'
      return
    }
    room.value = response.room
    isMember.value = !!response.isMember
    pageHeader.setHeader(room.value.Title, isMember.value ? 'Raum' : 'Geteilter Raum')
    // Raum-Arten und Organisationen für Bearbeiten/Reservieren
    if (isMember.value && !inventoryStore.organizations.length) await inventoryStore.fetchItems()
  } catch (err) {
    error.value = err.message || 'Raum konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

function reserve() {
  rentalModal.value?.open({ organizationId: room.value.Organization?.ID, roomIds: [room.value.ID] })
}

onMounted(load)
</script>
