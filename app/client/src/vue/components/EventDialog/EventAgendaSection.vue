<template>
  <div v-if="visible" class="agenda-section">
    <div class="section-feature-header">
      <h3 class="event-agenda_title">Tagesordnung</h3>
      <AppIconButton
        v-if="canManageContent && !showAddForm"
        variant="neutral"
        :disabled="submitting"
        aria-label="Tagesordnungspunkt hinzufügen"
        @click="startAdd"
      >+</AppIconButton>
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
        <AppButton
          size="small"
          variant="primary"
          :disabled="submitting || !addForm.title"
          @click="saveAdd"
        >Speichern</AppButton>
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
            <span v-if="point.RenderTime" class="agenda-point_time">{{ point.RenderTime }} · </span><strong>{{ point.Title }}</strong>
          </span>
          <div v-if="canManageContent" class="meal-manage-actions">
            <AppIconButton
              variant="primary"
              size="small"
              :disabled="submitting"
              aria-label="Tagesordnungspunkt bearbeiten"
              @click="startEdit(point)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              variant="danger"
              size="small"
              :disabled="submitting"
              aria-label="Tagesordnungspunkt löschen"
              @click="deletePoint(point.ID)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </AppIconButton>
          </div>
        </div>

        <p v-if="point.Description && editingPointId !== point.ID" class="agenda-point_desc agenda-point_desc--pre">
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
            <AppButton
              size="small"
              variant="primary"
              :disabled="submitting || !editForm.title"
              @click="saveEdit(point.ID)"
            >Speichern</AppButton>
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
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

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
