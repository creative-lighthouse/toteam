<template>
  <AppModal ref="modal" class="room-detail-modal" :title="room?.Title || 'Raum'" @close="close">
    <div v-if="loading" class="room-detail-modal_loading">Lade Raum…</div>

    <template v-else-if="room">
      <div class="room-detail-modal_head">
        <p v-if="room.Organization || room.Type" class="room-detail-modal_org">
          {{ [room.Type?.Title, room.Organization?.Title].filter(Boolean).join(' · ') }}
        </p>
        <div v-if="room.CanEdit" class="room-detail-modal_toolbar">
          <AppIconButton variant="neutral" aria-label="Teilen" title="Öffentlichen Link teilen" @click="openShare">
            <span class="icon-mask" :style="shareIconStyle" />
          </AppIconButton>
          <AppIconButton
            v-if="nfcSupported"
            variant="neutral"
            aria-label="NFC-Tag beschreiben"
            title="NFC-Tag beschreiben"
            :disabled="nfcLoading"
            @click="openNfc"
          >
            <span class="icon-mask" :style="nfcIconStyle" />
          </AppIconButton>
        </div>
      </div>
      <ul v-if="room.Images?.length" class="room-detail-modal_images">
        <li v-for="(img, index) in room.Images" :key="img.ID">
          <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(room.Images, index)">
            <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
          </a>
        </li>
      </ul>

      <p v-if="room.Description" class="room-detail-modal_description"><AppLinkifiedText :text="room.Description" /></p>

      <dl v-if="facts.length" class="room-detail-modal_facts">
        <div v-for="fact in facts" :key="fact.label">
          <dt>{{ fact.label }}</dt>
          <dd>{{ fact.value }}</dd>
        </div>
      </dl>

      <template v-if="room.Documents?.length">
        <h3 class="hl3 room-detail-modal_tasks-title">Dokumente</h3>
        <ul class="room-detail-modal_documents">
          <li v-for="doc in room.Documents" :key="doc.ID">
            <a :href="doc.URL" target="_blank" rel="noopener">{{ doc.Name }}</a>
            <span v-if="doc.Size" class="room-detail-modal_doc-size">{{ formatFileSize(doc.Size) }}</span>
          </li>
        </ul>
      </template>

      <h3 class="hl3 room-detail-modal_tasks-title">Aufgaben</h3>
      <ul v-if="room.Tasks?.length" class="room-detail-modal_tasks">
        <li v-for="task in room.Tasks" :key="task.ID">
          <button type="button" class="room-detail-modal_task" @click="openTask(task)">
            <span class="task-card_state-badge" :class="`task-card_state-badge--${task.State || 'open'}`">
              {{ stateLabel(task.State) }}
            </span>
            <span class="room-detail-modal_task-title">{{ task.Title }}</span>
            <AppAvatar
              v-if="task.Owner"
              :src="task.Owner.Avatar"
              :alt="task.Owner.Name"
              :title="task.Owner.Name"
              img-class="room-detail-modal_task-avatar"
            />
          </button>
        </li>
      </ul>
      <p v-else class="room-detail-modal_no-tasks">Diesem Raum sind noch keine Aufgaben zugeordnet.</p>

      <template v-if="room.Damages?.length">
        <h3 class="hl3 room-detail-modal_tasks-title">
          Schäden ({{ room.Damages.filter(d => d.IsOpen).length }} offen<template v-if="room.Damages.some(d => !d.IsOpen)">, {{ room.Damages.filter(d => !d.IsOpen).length }} behoben</template>)
        </h3>
        <InventoryDamageList :damages="room.Damages" show-rental @changed="reloadRoom" />
      </template>

      <template v-if="room.IsRentable">
        <h3 class="hl3 room-detail-modal_tasks-title">Reservierungen</h3>
        <ul v-if="room.Rentals?.length" class="room-detail-modal_tasks">
          <li v-for="rental in room.Rentals" :key="rental.ID">
            <button type="button" class="room-detail-modal_task" @click="openRental(rental)">
              <span class="inventory-rental-badge" :class="`inventory-rental-badge--${rental.Status}`">{{ rental.StatusLabel }}</span>
              <span class="room-detail-modal_task-title">{{ formatDateRange(rental.StartDate, rental.EndDate) }}</span>
              <span v-if="rental.Member" class="room-detail-modal_rental-member">{{ rental.Member.Name }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="room-detail-modal_no-tasks">Keine anstehenden Reservierungen.</p>
      </template>
    </template>

    <template v-if="room && (room.CanDelete || room.CanEdit || canReserve)" #actions>
      <AppButton v-if="room.CanDelete" variant="danger" @click="remove">Löschen</AppButton>
      <AppButton v-if="room.CanEdit" :variant="canReserve ? 'secondary' : 'primary'" @click="edit">Bearbeiten</AppButton>
      <AppButton v-if="canReserve" variant="primary" @click="emit('reserve', room)">Reservieren</AppButton>
    </template>
  </AppModal>

  <AppLightbox ref="lightbox" />
  <ShareLinkModal ref="shareModal" />
  <WriteNFCModal ref="nfcModal" />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import ShareLinkModal from '@components/ui/ShareLinkModal.vue'
import InventoryDamageList from '@components/inventory/InventoryDamageList.vue'
import WriteNFCModal, { isNfcWriteSupported } from '@components/ui/WriteNFCModal.vue'
import actionQrcode from '../../../../icons/actions/action_qrcode.svg'
import actionNfc from '../../../../icons/actions/action_nfc.svg'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import { formatDateRange, formatFieldValue, formatFileSize } from '@utils/inventory'

const props = defineProps({
  // Zeigt "Reservieren" (sofern der Raum reservierbar ist und die Berechtigung
  // passt) — nur dort, wo der Aufrufer das `reserve`-Event auch behandelt
  showReserve: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'deleted', 'reserve'])
const router = useRouter()
const store = useRoomsStore()

const modal = ref(null)
const lightbox = ref(null)
const shareModal = ref(null)
const nfcModal = ref(null)
const nfcLoading = ref(false)

const shareIconStyle = { maskImage: `url("${actionQrcode}")`, WebkitMaskImage: `url("${actionQrcode}")` }
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }

// Web NFC nur in Chrome auf Android — auf iOS/Desktop bleibt der Button unsichtbar
const nfcSupported = isNfcWriteSupported()

// Nach "behoben": neu laden (der Raum kann wieder reservierbar sein)
async function reloadRoom() {
  const detail = await store.fetchRoomDetail(room.value.ID)
  if (detail) room.value = detail
  store.fetchRooms(true)
}

function openShare() {
  const id = room.value.ID
  shareModal.value?.open({ title: room.value.Title, heading: 'Raum teilen', request: revoke => store.shareRoom(id, revoke) })
}

// Auf den Tag kommt der öffentliche Teilen-Link (wird bei Bedarf angelegt)
async function openNfc() {
  nfcLoading.value = true
  try {
    const response = await store.shareRoom(room.value.ID)
    if (!response.success) {
      alert(response.error || 'Link konnte nicht erstellt werden.')
      return
    }
    nfcModal.value?.open({ title: room.value.Title, url: response.data.url })
  } finally {
    nfcLoading.value = false
  }
}
const room = ref(null)
const loading = ref(false)

// Ausgefüllte Zusatzfelder der Raum-Art
const facts = computed(() =>
  (room.value?.Fields || [])
    .map(field => ({ label: field.Label, value: formatFieldValue(field, room.value.Values?.[field.ID]) }))
    .filter(fact => fact.value !== '')
)

const canReserve = computed(() => props.showReserve && !!room.value?.CanReserve)

const STATE_LABELS = {
  open:        'Offen',
  in_progress: 'In Bearbeitung',
  feedback:    'Feedback',
  finished:    'Abgeschlossen',
}
function stateLabel(state) {
  return STATE_LABELS[state] || 'Offen'
}

async function open(roomId) {
  room.value = null
  loading.value = true
  modal.value?.open()
  room.value = await store.fetchRoomDetail(roomId)
  loading.value = false
}

function close() {
  modal.value?.close()
}

function openTask(task) {
  close()
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
}

function openRental(rental) {
  close()
  router.push({ name: 'InventoryRentals', params: { id: rental.ID } })
}

function edit() {
  emit('edit', room.value)
}

async function remove() {
  if (!room.value) return
  if (!confirm(`Raum "${room.value.Title}" wirklich löschen?`)) return
  const response = await store.deleteRoom(room.value.ID)
  if (response.success) {
    emit('deleted', room.value.ID)
    close()
  } else {
    alert(response.error || 'Fehler beim Löschen des Raums.')
  }
}

defineExpose({ open, close })
</script>
