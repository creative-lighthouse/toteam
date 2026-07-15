<template>
  <div v-if="visible" class="meals-section">
    <div class="section-feature-header">
      <h3 class="event-meals_title">Mahlzeiten</h3>
      <AppIconButton
        v-if="canManageContent && !showAddForm"
        variant="primary"
        :disabled="submitting"
        aria-label="Mahlzeit hinzufügen"
        @click="startAdd"
      >
        <span class="icon-mask" :style="addIconStyle"></span>
      </AppIconButton>
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
      <label class="checkbox-label">
        <input
          type="checkbox"
          v-model="addAcceptsContributions"
          :disabled="submitting"
          aria-label="Mitglieder dürfen Gerichte vorschlagen"
        >
        Mitglieder dürfen Gerichte vorschlagen
      </label>
      <div class="time-button-row">
        <AppButton
          size="small"
          variant="primary"
          :disabled="submitting || !addTitle || !addTime"
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

    <div class="meals-list">
      <div v-for="meal in event.Meals" :key="meal.ID" class="meal">
        <div class="meal-info">
          <div class="meal-info-row">
            <span>
              <router-link :to="`/food/meal/${meal.ID}`" class="meal-title-link">{{ meal.Title }}</router-link>
              <span v-if="meal.RenderTime"> ({{ meal.RenderTime }})</span>
            </span>
            <div v-if="canManageContent" class="meal-manage-actions">
              <AppIconButton
                variant="primary"
                :disabled="submitting"
                aria-label="Mahlzeit bearbeiten"
                @click="startEdit(meal)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </AppIconButton>
              <AppIconButton
                variant="danger"
                :disabled="submitting"
                aria-label="Mahlzeit löschen"
                @click="deleteMeal(meal.ID)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </AppIconButton>
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
            <label class="checkbox-label">
              <input
                type="checkbox"
                v-model="editAcceptsContributions"
                :disabled="submitting"
                aria-label="Mitglieder dürfen Gerichte vorschlagen"
              >
              Mitglieder dürfen Gerichte vorschlagen
            </label>
            <div class="time-button-row">
              <AppButton
                size="small"
                variant="primary"
                :disabled="submitting || !editTitle || !editTime"
                @click="saveEdit(meal.ID)"
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

        <form class="event-response-actions" @submit.prevent>
          <AppButtonGroup
            :options="foodParticipationOptions"
            :model-value="meal.UserResponse"
            size="compact"
            :disabled="submitting"
            @select="type => changeFoodParticipation(meal.ID, type)"
          />
        </form>

        <!-- Bestellbare + feste Gerichte (nur nach Zusage zur Mahlzeit) -->
        <div v-if="meal.UserResponse === 'Accept' && mealEntries(meal).length" class="meal-entries">
          <MealCard
            v-for="entry in mealEntries(meal)"
            :key="`${entry.Orderable ? 'p' : 'f'}-${entry.ID}`"
            :title="entry.Title"
            :preference="entry.Preference"
            :supplier="entry.Supplier"
            :max-quantity="entry.MaxQuantity"
            :orderable="entry.Orderable"
            :quantity="entry.Orderable ? (localOrders[meal.ID]?.[entry.ID] ?? 0) : 0"
            :disabled="submitting"
            @increment="changeQty(meal, entry, 1)"
            @decrement="changeQty(meal, entry, -1)"
          />
        </div>
      </div>
    </div>

    <p v-if="!event.Meals || event.Meals.length === 0" class="event-section-empty">
      Noch keine Mahlzeiten geplant.
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppButtonGroup from '@components/AppButtonGroup.vue'
import MealCard from './MealCard.vue'
import AddIcon from '../../../../icons/actions/action_add.svg'

const addIconStyle = { maskImage: `url("${AddIcon}")`, WebkitMaskImage: `url("${AddIcon}")` }

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false }
})

const foodParticipationOptions = [
  { value: 'Decline', label: 'Nicht dabei', tone: 'negative' },
  { value: 'Accept', label: 'Dabei', tone: 'positive' },
]

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
const addAcceptsContributions = ref(false)

function startAdd() {
  addTitle.value = ''
  addTime.value = ''
  addAcceptsContributions.value = false
  showAddForm.value = true
}

function cancelAdd() {
  showAddForm.value = false
}

async function saveAdd() {
  if (!addTitle.value || !addTime.value || submitting.value) return
  submitting.value = true
  try {
    await eventsStore.addMeal(props.event.ID, addTitle.value, addTime.value, addAcceptsContributions.value)
    showAddForm.value = false
    addTitle.value = ''
    addTime.value = ''
    addAcceptsContributions.value = false
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
const editAcceptsContributions = ref(false)

function startEdit(meal) {
  editingMealId.value = meal.ID
  editTitle.value = meal.Title
  editTime.value = meal.RenderTime ?? ''
  editAcceptsContributions.value = !!meal.AcceptsContributions
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
    await eventsStore.updateMeal(mealId, editTitle.value, editTime.value, editAcceptsContributions.value)
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

// Bestellbare und feste Gerichte kommen vom Backend getrennt (Products/Foods),
// stammen aber beide vom selben Food-Model — für die einheitliche MealCard-Darstellung
// werden sie hier zu einer Liste zusammengeführt und nur per `Orderable`-Flag unterschieden.
function mealEntries(meal) {
  return [
    ...(meal.Products || []).map(p => ({ ...p, Orderable: true })),
    ...(meal.Foods || []).map(f => ({ ...f, Orderable: false })),
  ]
}

// Product orders
const localOrders  = ref({})   // { mealId: { productId: qty } }
const savingMeals  = ref({})   // { mealId: bool }
const saveTimers   = ref({})   // { mealId: timeoutId }

watch(() => props.event.Meals, (meals) => {
  for (const meal of meals || []) {
    if (!saveTimers.value[meal.ID]) {
      const orders = {}
      for (const p of meal.Products || []) orders[p.ID] = p.UserQuantity ?? 0
      localOrders.value[meal.ID] = orders
    }
  }
}, { immediate: true, deep: false })

function changeQty(meal, product, delta) {
  const current = localOrders.value[meal.ID]?.[product.ID] ?? 0
  let next = current + delta
  if (next < 0) next = 0
  if (product.MaxQuantity > 0 && next > product.MaxQuantity) next = product.MaxQuantity
  localOrders.value[meal.ID] = { ...localOrders.value[meal.ID], [product.ID]: next }

  // Debounce: reset timer on every change, save after 2 s of inactivity
  clearTimeout(saveTimers.value[meal.ID])
  saveTimers.value[meal.ID] = setTimeout(() => {
    saveTimers.value[meal.ID] = null
    saveMealOrders(meal)
  }, 1000)
}

async function saveMealOrders(meal) {
  if (savingMeals.value[meal.ID]) return
  savingMeals.value[meal.ID] = true
  try {
    await eventsStore.saveMealProductOrders(meal.ID, localOrders.value[meal.ID] ?? {})
    emit('show-status', { text: 'Bestellung gespeichert', type: 'success' })
  } catch (err) {
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    savingMeals.value[meal.ID] = false
  }
}
</script>
