<template>
  <div v-if="visible" class="meals-section">
    <div class="section-feature-header">
      <h3 class="event-meals_title">Mahlzeiten</h3>
      <button
        v-if="canManageContent && !showAddForm"
        type="button"
        class="btn-feature-add"
        @click="startAdd"
        :disabled="submitting"
        aria-label="Mahlzeit hinzufügen"
      >+</button>
    </div>

    <!-- Inline add form -->
    <fieldset v-if="showAddForm" class="fieldset-update-time">
      <div class="time-input-row">
        <label for="add-meal-title">Titel</label>
        <input
          id="add-meal-title"
          type="text"
          v-model="addTitle"
          :disabled="submitting"
          placeholder="z.B. Mittagessen"
          maxlength="255"
          aria-label="Neue Mahlzeit Titel"
        >
      </div>
      <div class="time-input-row">
        <label for="add-meal-time">Uhrzeit</label>
        <input
          id="add-meal-time"
          type="time"
          v-model="addTime"
          :disabled="submitting"
          aria-label="Uhrzeit der neuen Mahlzeit"
        >
      </div>
      <div class="time-button-row">
        <button
          type="button"
          class="button button--primary button--small"
          @click="saveAdd"
          :disabled="submitting || !addTitle || !addTime"
        >Speichern</button>
        <button
          type="button"
          class="btn-remove-time"
          @click="cancelAdd"
          :disabled="submitting"
        >Abbrechen</button>
      </div>
    </fieldset>

    <div class="meals-list">
      <div v-for="meal in event.Meals" :key="meal.ID" class="meal">
        <div class="meal-info">
          <div class="meal-info-row">
            <span>
              <strong>{{ meal.Title }}</strong>
              <span v-if="meal.RenderTime"> ({{ meal.RenderTime }})</span>
            </span>
            <div v-if="canManageContent" class="meal-manage-actions">
              <button
                type="button"
                class="button event-manage-button"
                @click="startEdit(meal)"
                :disabled="submitting"
                aria-label="Mahlzeit bearbeiten"
              >
                <img :src="EditIcon" alt="Bearbeiten">
              </button>
              <button
                type="button"
                class="button event-manage-button"
                @click="deleteMeal(meal.ID)"
                :disabled="submitting"
                aria-label="Mahlzeit löschen"
              >
                <img :src="TrashIcon" alt="Löschen">
              </button>
            </div>
          </div>

          <fieldset v-if="editingMealId === meal.ID" class="fieldset-update-time">
            <div class="time-input-row">
              <label :for="`edit-meal-title-${meal.ID}`">Titel</label>
              <input
                :id="`edit-meal-title-${meal.ID}`"
                type="text"
                v-model="editTitle"
                :disabled="submitting"
                maxlength="255"
                aria-label="Mahlzeit Titel"
              >
            </div>
            <div class="time-input-row">
              <label :for="`edit-meal-time-${meal.ID}`">Uhrzeit</label>
              <input
                :id="`edit-meal-time-${meal.ID}`"
                type="time"
                v-model="editTime"
                :disabled="submitting"
                aria-label="Uhrzeit der Mahlzeit"
              >
            </div>
            <div class="time-button-row">
              <button
                type="button"
                class="button button--primary button--small"
                @click="saveEdit(meal.ID)"
                :disabled="submitting || !editTitle || !editTime"
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

        <form class="event-response-actions" @submit.prevent>
          <fieldset class="fieldset-availability">
            <button
              type="button"
              class="event-response-button event-response-accept"
              :class="{
                'selected': meal.UserResponse === 'Accept',
                'unselected': meal.UserResponse && meal.UserResponse !== 'Accept'
              }"
              @click="changeFoodParticipation(meal.ID, 'Accept')"
              :disabled="submitting"
            >Dabei</button>
            <button
              type="button"
              class="event-response-button event-response-decline"
              :class="{
                'selected': meal.UserResponse === 'Decline',
                'unselected': meal.UserResponse && meal.UserResponse !== 'Decline'
              }"
              @click="changeFoodParticipation(meal.ID, 'Decline')"
              :disabled="submitting"
            >Nicht dabei</button>
          </fieldset>
        </form>
      </div>
    </div>

    <p v-if="!event.Meals || event.Meals.length === 0" class="event-section-empty">
      Noch keine Mahlzeiten geplant.
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

const emit = defineEmits(['food-changed', 'show-status'])

const eventsStore = useEventsStore()
const submitting = ref(false)

// Admins/Mods see the section whenever feature is on; members only when content exists
const visible = computed(() => {
  if (!props.event.EnableMeals) return false
  if (props.canManageContent) return true
  return props.event.Meals && props.event.Meals.length > 0
})

// Add form
const showAddForm = ref(false)
const addTitle = ref('')
const addTime = ref('')

function startAdd() {
  addTitle.value = ''
  addTime.value = ''
  showAddForm.value = true
}

function cancelAdd() {
  showAddForm.value = false
}

async function saveAdd() {
  if (!addTitle.value || !addTime.value || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.addMeal(props.event.ID, addTitle.value, addTime.value)
    showAddForm.value = false
    addTitle.value = ''
    addTime.value = ''
    emit('show-status', { text: 'Mahlzeit hinzugefügt', type: 'success' })
  } catch (err) {
    console.error('Error adding meal:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}

// Edit form
const editingMealId = ref(null)
const editTitle = ref('')
const editTime = ref('')

function startEdit(meal) {
  editingMealId.value = meal.ID
  editTitle.value = meal.Title
  editTime.value = meal.RenderTime ?? ''
}

function cancelEdit() {
  editingMealId.value = null
  editTitle.value = ''
  editTime.value = ''
}

async function saveEdit(mealId) {
  if (!editTitle.value || !editTime.value || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.updateMeal(mealId, editTitle.value, editTime.value)
    editingMealId.value = null
    emit('show-status', { text: 'Mahlzeit aktualisiert', type: 'success' })
  } catch (err) {
    console.error('Error updating meal:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}

async function deleteMeal(mealId) {
  if (submitting.value) return
  submitting.value = true
  try {
    await eventsStore.deleteMeal(mealId)
    emit('show-status', { text: 'Mahlzeit gelöscht', type: 'success' })
  } catch (err) {
    console.error('Error deleting meal:', err)
    emit('show-status', { text: 'Fehler beim Löschen', type: 'error' })
  } finally {
    submitting.value = false
  }
}

async function changeFoodParticipation(mealId, type) {
  if (submitting.value) return
  submitting.value = true
  try {
    await eventsStore.changeFoodParticipation(mealId, type)
    emit('food-changed', mealId, type)
    emit('show-status', { text: 'Essensauswahl gespeichert', type: 'success' })
  } catch (err) {
    console.error('Error changing food participation:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>
