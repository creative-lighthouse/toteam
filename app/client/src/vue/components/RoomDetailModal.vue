<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="room-detail-modal" @cancel.prevent="close">
      <div class="room-detail-modal_content" @click.stop>

        <div class="room-detail-modal_header">
          <h2 class="hl2 room-detail-modal_title">{{ room?.Title || 'Raum' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div v-if="loading" class="room-detail-modal_loading">Lade Raum…</div>

        <div v-else-if="room" class="room-detail-modal_body">
          <p v-if="room.Organization" class="room-detail-modal_org">{{ room.Organization.Title }}</p>
          <p v-if="room.Description" class="room-detail-modal_description">{{ room.Description }}</p>

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

          <div class="room-detail-modal_actions">
            <AppButton v-if="room.CanDelete" variant="danger" @click="remove">Löschen</AppButton>
            <AppButton v-if="room.CanEdit" variant="primary" @click="edit">Bearbeiten</AppButton>
          </div>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppAvatar from '@components/AppAvatar.vue'

const emit = defineEmits(['edit', 'deleted'])
const router = useRouter()
const store = useRoomsStore()

const dialogEl = ref(null)
const room = ref(null)
const loading = ref(false)

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
  dialogEl.value?.showModal()
  room.value = await store.fetchRoomDetail(roomId)
  loading.value = false
}

function close() {
  dialogEl.value?.close()
}

function openTask(task) {
  close()
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
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
