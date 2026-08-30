<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="room-form-modal" @cancel.prevent="close">
      <div class="room-form-modal_content" @click.stop>

        <div class="room-form-modal_header">
          <h2 class="hl2 room-form-modal_title">{{ isEdit ? 'Raum bearbeiten' : 'Neuer Raum' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="room-form-modal_body" @submit.prevent="submit">

          <div v-if="!isEdit" class="form-field">
            <label class="form-label">Organisation</label>
            <div class="multiselect-group">
              <label v-for="org in store.organizations" :key="org.ID" class="checkbox-label">
                <input type="radio" :value="org.ID" v-model="form.OrganizationID" :aria-label="org.Title" />
                {{ org.Title }}
              </label>
            </div>
          </div>

          <div class="form-field">
            <label class="form-label" for="room-title">Titel *</label>
            <input
              id="room-title"
              v-model="form.Title"
              type="text"
              class="input"
              placeholder="Raumtitel"
              required
              autofocus
            />
          </div>

          <div class="form-field">
            <label class="form-label" for="room-description">Beschreibung</label>
            <textarea
              id="room-description"
              v-model="form.Description"
              class="input"
              rows="3"
              placeholder="Optionale Beschreibung…"
            />
          </div>

          <div class="form-field">
            <label class="form-label">Aufgaben</label>
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

          <div v-if="error" class="room-form-modal_error">
            {{ error }}
          </div>

          <div class="room-form-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">
              Abbrechen
            </AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
              {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const emit = defineEmits(['saved'])
const store = useRoomsStore()

const dialogEl = ref(null)
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
  dialogEl.value?.showModal()
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
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
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
