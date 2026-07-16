<template>
  <div v-if="visible" class="meals-section">
    <div class="section-feature-header">
      <h3 class="event-meals_title">Mahlzeiten</h3>
      <AppIconButton
        v-if="canManageContent"
        variant="primary"
        aria-label="Mahlzeit hinzufügen"
        @click="mealModal?.open()"
      >
        <span class="icon-mask" :style="addIconStyle"></span>
      </AppIconButton>
    </div>

    <div class="meals-list">
      <div v-for="meal in event.Meals" :key="meal.ID" class="meal">
        <div class="meal-info">
          <div class="meal-info-row">
            <span>
              <router-link :to="`/food/meal/${meal.ID}`" class="meal-title-link">{{ meal.Title }}</router-link>
              <span v-if="meal.RenderTime"> ({{ meal.RenderTime }})</span>
            </span>
            <AppIconButton
              v-if="canManageContent"
              variant="primary"
              aria-label="Mahlzeit bearbeiten"
              @click="mealModal?.open(meal)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
          </div>
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

    <EventMealModal
      v-if="canManageContent"
      ref="mealModal"
      :event-id="event.ID"
      @show-status="$emit('show-status', $event)"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import AppIconButton from '@components/AppIconButton.vue'
import AppButtonGroup from '@components/AppButtonGroup.vue'
import MealCard from './MealCard.vue'
import EventMealModal from './EventMealModal.vue'
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
const mealModal = ref(null)

// Admins/Mods see the section whenever feature is on; members only when content exists
const visible = computed(() => {
  if (!props.event.EnableMeals) return false
  if (props.canManageContent) return true
  return props.event.Meals && props.event.Meals.length > 0
})

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
