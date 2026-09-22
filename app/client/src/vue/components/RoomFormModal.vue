<template>
  <AppModal ref="modal" class="room-form-modal" :title="isEdit ? 'Raum bearbeiten' : 'Neuer Raum'" @close="close">
    <form id="room-form-form" class="modalform" @submit.prevent="submit">

      <div v-if="!isEdit" class="field">
        <label>Organisation</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.organizations" />
      </div>

      <label class="field">
        Titel *
        <input
          id="room-title"
          v-model="form.Title"
          type="text"
          placeholder="Raumtitel"
          required
          autofocus
        />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="room-description"
          v-model="form.Description"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </label>

      <div class="field">
        <label>Aufgaben</label>
        <p v-if="loadingTasks" class="room-form-modal_tasks-loading">Lade Aufgaben…</p>
        <p v-else-if="!form.OrganizationID" class="room-form-modal_tasks-loading">Bitte zuerst eine Organisation wählen.</p>
        <div v-else-if="attachableTasks.length" class="multiselect-group room-form-modal_tasks">
          <label v-for="t in attachableTasks" :key="t.ID" class="checkbox-label">
            <input type="checkbox" :value="t.ID" v-model="form.TaskIDs" />
            {{ t.Title }}
          </label>
        </div>
        <p v-else class="room-form-modal_tasks-loading">Keine Aufgaben in dieser Organisation.</p>
      </div>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="room-form-form" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'
import OrganizationPicker from '@components/OrganizationPicker.vue'

const emit = defineEmits(['saved'])
const store = useRoomsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const attachableTasks = ref([])
const loadingTasks = ref(false)
const editingRoomId = ref(null)

const isEdit = computed(() => editingRoomId.value !== null)

const defaultForm = () => ({
  OrganizationID: store.filterOrganization?.ID ?? (store.organizations.length === 1 ? store.organizations[0].ID : 0),
  Title: '',
  Description: '',
  TaskIDs: [],
})

const form = reactive(defaultForm())

async function loadAttachableTasks(orgId, keepSelection = []) {
  if (!orgId) {
    attachableTasks.value = []
    form.TaskIDs = []
    return
  }

  loadingTasks.value = true
  try {
    attachableTasks.value = await store.fetchAttachableTasks(orgId)
  } finally {
    loadingTasks.value = false
  }

  const validIds = new Set(attachableTasks.value.map(t => t.ID))
  form.TaskIDs = keepSelection.filter(id => validIds.has(id))
}

watch(() => form.OrganizationID, orgId => loadAttachableTasks(orgId))

// Create a new room
function open() {
  editingRoomId.value = null
  Object.assign(form, defaultForm())
  error.value = null
  loadAttachableTasks(form.OrganizationID)
  modal.value?.open()
}

// Edit an existing room — `room` is the full detail object from fetchRoomDetail (incl. Tasks)
function openForEdit(room) {
  editingRoomId.value = room.ID
  Object.assign(form, {
    OrganizationID: room.Organization?.ID ?? 0,
    Title: room.Title,
    Description: room.Description || '',
    TaskIDs: (room.Tasks || []).map(t => t.ID),
  })
  error.value = null
  loadAttachableTasks(form.OrganizationID, form.TaskIDs)
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationID) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      TaskIDs: form.TaskIDs,
    }

    const response = isEdit.value
      ? await store.updateRoom(editingRoomId.value, payload)
      : await store.createRoom({ ...payload, OrganizationID: parseInt(form.OrganizationID) })

    if (response.success) {
      emit('saved', response.data.room)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern des Raums.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
