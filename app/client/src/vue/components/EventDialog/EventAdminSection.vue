<template>
  <div v-if="canManageContent" class="event-admin-section">
    <hr>
    <p>Zu Termin hinzufügen:</p>
    <!-- Bearbeiten-Aktion -->
    <div class="event-manage-actions">
      <button type="button" class="button event-manage-button" @click="$emit('edit-appointment', event)">
        <img :src="EditIcon" alt="Bearbeiten" />
      </button>
    </div>

    <!-- Mahlzeit hinzufügen -->
    <div class="add-actions-row">
      <button
        v-if="!showMealInput"
        type="button"
        class="btn-add-time"
        @click="startAddMeal"
        :disabled="submitting"
      >
        <img :src="AddFoodIcon" alt="Mahlzeit Icon" class="action-icon">
        <p>Mahlzeit +</p>
      </button>
    </div>

    <fieldset v-if="showMealInput" class="fieldset-update-time">
      <div class="time-input-row">
        <label for="meal-title">Titel</label>
        <input
          id="meal-title"
          type="text"
          v-model="mealTitle"
          :disabled="submitting"
          placeholder="z.B. Mittagessen"
          maxlength="255"
          aria-label="Mahlzeit Titel"
        >
      </div>
      <div class="time-input-row">
        <label for="meal-time">Uhrzeit</label>
        <input
          id="meal-time"
          type="time"
          v-model="mealTime"
          :disabled="submitting"
          aria-label="Uhrzeit der Mahlzeit"
        >
      </div>
      <div class="time-button-row">
        <button
          type="button"
          class="button button--primary button--small"
          @click="saveMeal"
          :disabled="submitting || !mealTitle || !mealTime"
        >
          Speichern
        </button>
        <button
          type="button"
          class="btn-remove-time"
          @click="cancelMeal"
          :disabled="submitting"
        >
          Abbrechen
        </button>
      </div>
    </fieldset>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useEventsStore } from '@stores/events'
import EditIcon from '../../../../icons/actions/action_edit.svg'
import AddFoodIcon from '../../../../icons/actions/action_addfood.svg'

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, required: true }
})

const emit = defineEmits(['edit-appointment', 'show-status'])

const eventsStore = useEventsStore()
const submitting = ref(false)
const showMealInput = ref(false)
const mealTitle = ref('')
const mealTime = ref('')

function startAddMeal() {
  mealTitle.value = ''
  mealTime.value = ''
  showMealInput.value = true
}

function cancelMeal() {
  showMealInput.value = false
  mealTitle.value = ''
  mealTime.value = ''
}

async function saveMeal() {
  if (!mealTitle.value || !mealTime.value || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.addMeal(props.event.ID, mealTitle.value, mealTime.value)
    showMealInput.value = false
    mealTitle.value = ''
    mealTime.value = ''
    emit('show-status', { text: 'Mahlzeit hinzugefügt', type: 'success' })
  } catch (err) {
    console.error('Error adding meal:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>
