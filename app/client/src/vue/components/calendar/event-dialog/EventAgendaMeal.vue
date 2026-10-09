<template>
  <!-- Eine Mahlzeit als Zeile der Tagesordnung (EventAgendaSection) -->
  <div class="agenda-point agenda-point--meal">
    <span class="agenda-point_time">
      {{ meal.RenderTime }}
      <span class="icon-mask agenda-meal_icon" :style="foodIconStyle" aria-hidden="true"></span>
    </span>

    <!-- Bearbeiten per Rechtsklick bzw. langem Drücken (nur mit Rechten) -->
    <div
      v-context-menu="canManageContent ? (e => $emit('open-menu', e, meal)) : null"
      class="agenda-point_content"
      :class="{ 'agenda-point_content--manageable': canManageContent }"
    >
      <div class="agenda-meal_head">
        <strong class="agenda-point_title agenda-meal_title">
          <router-link :to="`/food/meal/${meal.ID}`" class="meal-title-link">{{ meal.Title }}</router-link>
        </strong>

        <AppButtonGroup
          class="agenda-meal_response"
          :options="foodParticipationOptions"
          :model-value="meal.UserResponse"
          size="compact"
          :disabled="submitting"
          :label="`Teilnahme an ${meal.Title}`"
          @select="changeFoodParticipation"
        />
      </div>

      <!-- Alle Gerichte einheitlich; bestellen kann man erst nach Zusage zur Mahlzeit.
           Bearbeiten per Rechtsklick bzw. langem Drücken (Essensplaner/Mahlzeit-Verwalter). -->
      <div v-if="foods.length" class="meal-entries">
        <MealFoodRow
          v-for="food in foods"
          :key="food.ID"
          v-context-menu="meal.CanEditFoods ? (e => $emit('open-food-menu', e, food)) : null"
          :class="{ 'meal-food-row--manageable': meal.CanEditFoods }"
          :title="food.Title"
          :preference="food.Preference"
          :supplier="food.Supplier"
          :max-quantity="food.MaxQuantity ?? 0"
          :orderable="food.Orderable"
          :can-order="meal.UserResponse === 'Accept'"
          :quantity="localOrders[food.ID] ?? 0"
          :disabled="submitting"
          @increment="changeQty(food, 1)"
          @decrement="changeQty(food, -1)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButtonGroup from '@components/ui/AppButtonGroup.vue'
import MealFoodRow from '@components/food/MealFoodRow.vue'
import { vContextMenu } from '@utils/contextMenu'
import FoodIcon from '../../../../../icons/actions/action_food.svg'

const foodIconStyle = { maskImage: `url("${FoodIcon}")`, WebkitMaskImage: `url("${FoodIcon}")` }

const props = defineProps({
  meal: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false }
})

const emit = defineEmits(['food-changed', 'show-status', 'open-menu', 'open-food-menu'])

const foodParticipationOptions = [
  { value: 'Decline', label: 'Nicht dabei', tone: 'negative' },
  { value: 'Accept', label: 'Dabei', tone: 'positive' },
]

const eventsStore = useEventsStore()
const submitting = ref(false)

async function changeFoodParticipation(type) {
  if (submitting.value) return
  submitting.value = true
  try {
    await eventsStore.changeFoodParticipation(props.meal.ID, type)
    emit('food-changed', props.meal.ID, type)
    emit('show-status', { text: 'Essensauswahl gespeichert', type: 'success' })
  } catch (err) {
    console.error('Error changing food participation:', err)
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    submitting.value = false
  }
}

// Bestellbare (Products) und feste Gerichte (Foods) kommen vom Backend getrennt,
// stammen aber vom selben Food-Model — hier eine Liste, unterschieden per `Orderable`
const foods = computed(() => [
  ...(props.meal.Products || []).map(p => ({ ...p, Orderable: true })),
  ...(props.meal.Foods || []).map(f => ({ ...f, Orderable: false })),
])

// Product orders
const localOrders = ref({})   // { productId: qty }
const saving      = ref(false)
let saveTimer     = null

watch(() => props.meal.Products, (products) => {
  if (saveTimer) return
  const orders = {}
  for (const p of products || []) orders[p.ID] = p.UserQuantity ?? 0
  localOrders.value = orders
}, { immediate: true })

function changeQty(product, delta) {
  const current = localOrders.value[product.ID] ?? 0
  let next = current + delta
  if (next < 0) next = 0
  if (product.MaxQuantity > 0 && next > product.MaxQuantity) next = product.MaxQuantity
  localOrders.value = { ...localOrders.value, [product.ID]: next }

  // Debounce: reset timer on every change, save after 1 s of inactivity
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    saveTimer = null
    saveMealOrders()
  }, 1000)
}

async function saveMealOrders() {
  if (saving.value) return
  saving.value = true
  try {
    await eventsStore.saveMealProductOrders(props.meal.ID, localOrders.value)
    emit('show-status', { text: 'Bestellung gespeichert', type: 'success' })
  } catch (err) {
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    saving.value = false
  }
}
</script>
