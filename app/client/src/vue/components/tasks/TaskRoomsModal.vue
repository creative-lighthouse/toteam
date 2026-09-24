<template>
  <AppModal ref="modal" class="task-rooms-modal" title="Räume verwalten" @close="close">
    <form id="task-rooms-form" @submit.prevent="submit">

      <div v-if="loadingRooms" class="task-rooms-modal_loading">Lade Räume…</div>

      <div v-else-if="roomOptions.length === 0" class="task-rooms-modal_loading">
        Keine Räume in dieser Organisation.
      </div>

      <div v-else class="task-rooms-modal_list">
        <label v-for="r in roomOptions" :key="r.ID" class="task-rooms-modal_option">
          <input type="checkbox" :value="r.ID" v-model="selected" />
          <span>{{ r.Title }}</span>
        </label>
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="task-rooms-form" variant="primary" :disabled="saving || loadingRooms">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { useTasksStore } from '@stores/tasks'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const props = defineProps({
  taskId: { type: Number, required: true },
  organizationId: { type: Number, required: true },
  currentRoomIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const tasksStore = useTasksStore()
const roomsStore = useRoomsStore()

const modal = ref(null)
const saving = ref(false)
const loadingRooms = ref(false)
const error = ref(null)
const roomOptions = ref([])
const selected = ref([])

async function open() {
  error.value = null
  selected.value = [...props.currentRoomIds]
  loadingRooms.value = true
  try {
    await roomsStore.fetchRooms()
    roomOptions.value = roomsStore.rooms.filter(r => r.Organization?.ID === props.organizationId)
  } finally {
    loadingRooms.value = false
  }
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    const response = await tasksStore.updateTask(props.taskId, { RoomIDs: selected.value.map(id => parseInt(id)) })

    if (response.success) {
      emit('saved', response.data.task)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Räume.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
