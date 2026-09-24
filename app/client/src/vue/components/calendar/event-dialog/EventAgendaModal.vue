<template>
  <AppModal ref="modal" class="event-agenda-modal" title="Tagesordnung bearbeiten" @close="close">
    <div v-for="row in rows" :key="row._key" class="event-agenda-modal_row">
      <div class="event-agenda-modal_row-top">
        <input
          type="text"
          v-model="row.Title"
          placeholder="Titel *"
          class="input"
          maxlength="255"
          aria-label="Titel des Tagesordnungspunkts"
          @input="scheduleSave(row)"
          @blur="saveNow(row)"
        >
        <AppIconButton
          variant="danger"
          :disabled="row.saving"
          aria-label="Tagesordnungspunkt entfernen"
          @click="removeRow(row)"
        >
          <span class="icon-mask" :style="trashIconStyle" />
        </AppIconButton>
      </div>

      <div class="event-agenda-modal_row-times">
        <label>
          Von
          <input type="time" v-model="row.StartTime" class="input" aria-label="Startzeit" @change="saveNow(row)">
        </label>
        <label>
          Bis
          <input type="time" v-model="row.EndTime" class="input" aria-label="Endzeit" @change="saveNow(row)">
        </label>
      </div>

      <textarea
        v-model="row.Description"
        placeholder="Beschreibung"
        class="input"
        rows="2"
        aria-label="Beschreibung"
        @input="scheduleSave(row)"
        @blur="saveNow(row)"
      ></textarea>

      <p v-if="row.error" class="event-agenda-modal_row-error">{{ row.error }}</p>
    </div>

    <p v-if="!rows.length" class="event-agenda-modal_empty">Noch keine Tagesordnungspunkte geplant.</p>

    <AppButton variant="secondary" size="small" @click="addRow">+ Tagesordnungspunkt</AppButton>

    <template #actions>
      <AppButton variant="primary" @click="close">Fertig</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import actionTrash from '../../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  event: { type: Object, required: true },
})

const emit = defineEmits(['show-status'])
const eventsStore = useEventsStore()

const modal = ref(null)
const rows = ref([])
let nextTempKey = -1

function makeRow(point = null) {
  return {
    _key: point ? point.ID : nextTempKey--,
    ID: point ? point.ID : null,
    Title: point?.Title ?? '',
    StartTime: point?.StartTime ? point.StartTime.substring(0, 5) : '',
    EndTime: point?.EndTime ? point.EndTime.substring(0, 5) : '',
    Description: point?.Description ?? '',
    saving: false,
    error: null,
  }
}

function open() {
  rows.value = (props.event.AgendaPoints || []).map(p => makeRow(p))
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function addRow() {
  rows.value.push(makeRow())
}

async function removeRow(row) {
  if (row.saving) return
  clearTimeout(saveTimers[row._key])
  if (row.ID) {
    if (!confirm('Diesen Tagesordnungspunkt wirklich löschen?')) return
    row.saving = true
    try {
      await eventsStore.deleteAgendaPoint(row.ID)
      emit('show-status', { text: 'Tagesordnungspunkt gelöscht', type: 'success' })
    } catch (err) {
      console.error('Error deleting agenda point:', err)
      row.saving = false
      row.error = 'Fehler beim Löschen'
      emit('show-status', { text: 'Fehler beim Löschen', type: 'error' })
      return
    }
  }
  rows.value = rows.value.filter(r => r._key !== row._key)
}

async function saveRow(row) {
  if (!row.Title.trim() || row.saving) return
  row.saving = true
  row.error = null
  try {
    const payload = {
      title: row.Title.trim(),
      startTime: row.StartTime || null,
      endTime: row.EndTime || null,
      description: row.Description,
    }
    if (row.ID) {
      await eventsStore.updateAgendaPoint(row.ID, payload)
    } else {
      const created = await eventsStore.addAgendaPoint(props.event.ID, payload)
      row.ID = created.ID
    }
    emit('show-status', { text: 'Gespeichert', type: 'success' })
  } catch (err) {
    console.error('Error saving agenda point:', err)
    row.error = 'Fehler beim Speichern'
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    row.saving = false
  }
}

// Debounced Auto-Save für Titel/Beschreibung nach kurzer Tipppause; Zeiten
// speichern sofort über @change (native Time-Inputs feuern das nur bei Commit).
const saveTimers = {}

function scheduleSave(row) {
  clearTimeout(saveTimers[row._key])
  saveTimers[row._key] = setTimeout(() => saveRow(row), 900)
}

function saveNow(row) {
  clearTimeout(saveTimers[row._key])
  saveRow(row)
}

onBeforeUnmount(() => {
  Object.values(saveTimers).forEach(clearTimeout)
})

defineExpose({ open, close })
</script>
