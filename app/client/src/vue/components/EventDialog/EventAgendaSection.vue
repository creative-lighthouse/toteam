<template>
  <div v-if="visible" class="agenda-section">
    <div class="section-feature-header">
      <h3 class="event-agenda_title">Tagesordnung</h3>
      <button
        v-if="canManageContent && !showAddForm"
        type="button"
        class="btn-feature-add"
        @click="startAdd"
        :disabled="submitting"
        aria-label="Tagesordnungspunkt hinzufügen"
      >+</button>
    </div>

    <!-- Inline add form -->
    <fieldset v-if="showAddForm" class="fieldset-update-time">
      <div class="time-input-row">
        <label for="add-agenda-title">Titel *</label>
        <input
          id="add-agenda-title"
          type="text"
          v-model="addForm.title"
          :disabled="submitting"
          placeholder="z.B. Begrüßung"
          maxlength="255"
          aria-label="Titel des Tagesordnungspunkts"
        >
      </div>
      <div class="time-input-row">
        <label for="add-agenda-start">Startzeit</label>
        <input
          id="add-agenda-start"
          type="time"
          v-model="addForm.startTime"
          :disabled="submitting"
          aria-label="Startzeit des Tagesordnungspunkts"
        >
      </div>
      <div class="time-input-row">
        <label for="add-agenda-end">Endzeit</label>
        <input
          id="add-agenda-end"
          type="time"
          v-model="addForm.endTime"
          :disabled="submitting"
          aria-label="Endzeit des Tagesordnungspunkts"
        >
      </div>
      <div class="time-input-row">
        <label for="add-agenda-desc">Beschreibung</label>
        <textarea
          id="add-agenda-desc"
          v-model="addForm.description"
          :disabled="submitting"
          rows="2"
          aria-label="Beschreibung des Tagesordnungspunkts"
        ></textarea>
      </div>
      <div class="time-button-row">
        <button
          type="button"
          class="button button--primary button--small"
          @click="saveAdd"
          :disabled="submitting || !addForm.title"
        >Speichern</button>
        <button
          type="button"
          class="btn-remove-time"
          @click="cancelAdd"
          :disabled="submitting"
        >Abbrechen</button>
      </div>
    </fieldset>

    <div class="agenda-list">
      <div v-for="point in event.AgendaPoints" :key="point.ID" class="agenda-point">
        <div class="agenda-point-row">
          <span>
            <strong>{{ point.Title }}</strong>
            <span v-if="point.RenderTime" class="agenda-point_time"> ({{ point.RenderTime }})</span>
          </span>
          <div v-if="canManageContent" class="meal-manage-actions">
            <button
              type="button"
              class="button event-manage-button"
              @click="startEdit(point)"
              :disabled="submitting"
              aria-label="Tagesordnungspunkt bearbeiten"
            >
              <img :src="EditIcon" alt="Bearbeiten">
            </button>
            <button
              type="button"
              class="button event-manage-button"
              @click="deletePoint(point.ID)"
              :disabled="submitting"
              aria-label="Tagesordnungspunkt löschen"
            >
              <img :src="TrashIcon" alt="Löschen">
            </button>
          </div>
        </div>

        <p v-if="point.Description && editingPointId !== point.ID" class="agenda-point_desc">
          {{ point.Description }}
        </p>

        <fieldset v-if="editingPointId === point.ID" class="fieldset-update-time">
          <div class="time-input-row">
            <label :for="`edit-agenda-title-${point.ID}`">Titel *</label>
            <input
              :id="`edit-agenda-title-${point.ID}`"
              type="text"
              v-model="editForm.title"
              :disabled="submitting"
              maxlength="255"
              aria-label="Titel des Tagesordnungspunkts"
            >
          </div>
          <div class="time-input-row">
            <label :for="`edit-agenda-start-${point.ID}`">Startzeit</label>
            <input
              :id="`edit-agenda-start-${point.ID}`"
              type="time"
              v-model="editForm.startTime"
              :disabled="submitting"
              aria-label="Startzeit"
            >
          </div>
          <div class="time-input-row">
            <label :for="`edit-agenda-end-${point.ID}`">Endzeit</label>
            <input
              :id="`edit-agenda-end-${point.ID}`"
              type="time"
              v-model="editForm.endTime"
              :disabled="submitting"
              aria-label="Endzeit"
            >
          </div>
          <div class="time-input-row">
            <label :for="`edit-agenda-desc-${point.ID}`">Beschreibung</label>
            <textarea
              :id="`edit-agenda-desc-${point.ID}`"
              v-model="editForm.description"
              :disabled="submitting"
              rows="2"
              aria-label="Beschreibung"
            ></textarea>
          </div>
          <div class="time-button-row">
            <button
              type="button"
              class="button button--primary button--small"
              @click="saveEdit(point.ID)"
              :disabled="submitting || !editForm.title"
            >Speichern</button>
            <button
              type="button"
              class="btn-remove-time"
              @click="cancelEdit"
              :disabled="submitting"
            >Abbrechen</button>
          </div>
        </fieldset>
      </div>
    </div>

    <p v-if="!event.AgendaPoints || event.AgendaPoints.length === 0" class="event-section-empty">
      Noch keine Tagesordnungspunkte geplant.
    </p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import EditIcon from '../../../../icons/actions/action_edit.svg'
import TrashIcon from '../../../../icons/actions/action_trash_blue.svg'

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false }
})

const emit = defineEmits(['show-status'])

const eventsStore = useEventsStore()
const submitting = ref(false)

const visible = computed(() => {
  if (!props.event.EnableAgenda) return false
  if (props.canManageContent) return true
  return props.event.AgendaPoints && props.event.AgendaPoints.length > 0
})

// Add form
const showAddForm = ref(false)
const addForm = ref({ title: '', startTime: '', endTime: '', description: '' })

function startAdd() {
  addForm.value = { title: '', startTime: '', endTime: '', description: '' }
  showAddForm.value = true
}

function cancelAdd() {
  showAddForm.value = false
}

async function saveAdd() {
  if (!addForm.value.title || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.addAgendaPoint(props.event.ID, {
      title: addForm.value.title,
      startTime: addForm.value.startTime || null,
      endTime: addForm.value.endTime || null,
      description: addForm.value.description,
    })
    showAddForm.value = false
    emit('show-status', { text: 'Tagesordnungspunkt hinzugefügt', type: 'success' })
  } catch (err) {
    console.error('Error adding agenda point:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}

// Edit form
const editingPointId = ref(null)
const editForm = ref({ title: '', startTime: '', endTime: '', description: '' })

function startEdit(point) {
  editingPointId.value = point.ID
  editForm.value = {
    title: point.Title ?? '',
    startTime: point.StartTime ? point.StartTime.substring(0, 5) : '',
    endTime: point.EndTime ? point.EndTime.substring(0, 5) : '',
    description: point.Description ?? '',
  }
}

function cancelEdit() {
  editingPointId.value = null
}

async function saveEdit(pointId) {
  if (!editForm.value.title || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.updateAgendaPoint(pointId, {
      title: editForm.value.title,
      startTime: editForm.value.startTime || null,
      endTime: editForm.value.endTime || null,
      description: editForm.value.description,
    })
    editingPointId.value = null
    emit('show-status', { text: 'Tagesordnungspunkt aktualisiert', type: 'success' })
  } catch (err) {
    console.error('Error updating agenda point:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}

async function deletePoint(pointId) {
  if (submitting.value) return
  submitting.value = true
  try {
    await eventsStore.deleteAgendaPoint(pointId)
    emit('show-status', { text: 'Tagesordnungspunkt gelöscht', type: 'success' })
  } catch (err) {
    console.error('Error deleting agenda point:', err)
    emit('show-status', { text: 'Fehler beim Löschen', type: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>
