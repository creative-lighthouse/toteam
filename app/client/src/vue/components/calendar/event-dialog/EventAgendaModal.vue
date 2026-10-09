<template>
  <AppModal ref="modal" class="event-agenda-modal" title="Tagesordnung bearbeiten" @close="close">
    <template v-for="item in items" :key="item.key">
      <!-- Mahlzeiten nur zur Orientierung (bearbeitet werden sie im Termin) -->
      <div v-if="item.meal" class="event-agenda-modal_meal">
        <span class="event-agenda-modal_meal-time">{{ item.meal.RenderTime }}</span>
        <span class="icon-mask event-agenda-modal_meal-icon" :style="foodIconStyle" aria-hidden="true"></span>
        <span class="event-agenda-modal_meal-title">{{ item.meal.Title }}</span>
      </div>

      <div v-else class="event-agenda-modal_row">
        <div class="event-agenda-modal_row-top">
          <input
            type="text"
            v-model="item.row.Title"
            placeholder="Titel *"
            class="input"
            maxlength="255"
            aria-label="Titel des Tagesordnungspunkts"
            @input="scheduleSave(item.row)"
            @blur="saveNow(item.row)"
          >
          <AppIconButton
            variant="danger"
            :disabled="item.row.saving"
            aria-label="Tagesordnungspunkt entfernen"
            @click="removeRow(item.row)"
          >
            <span class="icon-mask" :style="trashIconStyle" />
          </AppIconButton>
        </div>

        <div class="event-agenda-modal_row-times">
          <label>
            Von
            <input type="time" v-model="item.row.StartTime" class="input" aria-label="Startzeit" @change="saveNow(item.row)">
          </label>
          <label>
            Bis
            <input type="time" v-model="item.row.EndTime" class="input" aria-label="Endzeit" @change="saveNow(item.row)">
          </label>
        </div>

        <textarea
          v-model="item.row.Description"
          placeholder="Beschreibung"
          class="input"
          rows="2"
          aria-label="Beschreibung"
          @input="scheduleSave(item.row)"
          @blur="saveNow(item.row)"
        ></textarea>

        <p v-if="item.row.error" class="event-agenda-modal_row-error">{{ item.row.error }}</p>
      </div>
    </template>

    <p v-if="!rows.length" class="event-agenda-modal_empty">Noch keine Tagesordnungspunkte geplant.</p>

    <AppButton variant="secondary" size="small" @click="addRow">+ Tagesordnungspunkt</AppButton>

    <template #actions>
      <AppButton variant="primary" @click="close">Fertig</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import actionTrash from '../../../../../icons/actions/action_trash.svg'
import actionFood from '../../../../../icons/actions/action_food.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }
const foodIconStyle = { maskImage: `url("${actionFood}")`, WebkitMaskImage: `url("${actionFood}")` }

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

// Mahlzeiten zwischen die Punkte einsortieren: Die Punkte behalten beim Bearbeiten ihre
// Reihenfolge, jede Mahlzeit steht vor dem ersten Punkt, der später beginnt — so wandert
// sie beim Ändern einer Startzeit mit. Punkte ohne Startzeit werden übersprungen.
const items = computed(() => {
  const meals = props.event.EnableMeals
    ? [...(props.event.Meals || [])].sort((a, b) => (a.Time || '').localeCompare(b.Time || ''))
    : []
  const result = []
  let m = 0
  for (const row of rows.value) {
    while (row.StartTime && m < meals.length && (meals[m].Time || '').substring(0, 5) < row.StartTime) {
      result.push({ key: `meal-${meals[m].ID}`, meal: meals[m++] })
    }
    result.push({ key: row._key, row })
  }
  for (; m < meals.length; m++) result.push({ key: `meal-${meals[m].ID}`, meal: meals[m] })
  return result
})

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
