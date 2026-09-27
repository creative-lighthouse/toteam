<template>
  <!-- Eigene Essensvorschläge mit Status (offen, eingeplant, abgelehnt) -->
  <AppModal ref="modal" class="food-suggestions-modal" title="Meine Vorschläge" @close="close">
    <ul v-if="foods.length" class="food-suggestions-modal_list">
      <li v-for="food in foods" :key="food.id" class="food-suggestion">
        <div class="food-suggestion_info">
          <p class="food-suggestion_title">
            {{ food.title }}
            <span v-if="PREFERENCE_LABELS[food.preference]" class="food-suggestion_pref">{{ PREFERENCE_LABELS[food.preference] }}</span>
          </p>
          <p class="food-suggestion_context">{{ context(food) }}</p>
        </div>
        <span class="food-suggestion_badge" :class="`food-suggestion_badge--${food.status.toLowerCase()}`">
          {{ STATUS_LABELS[food.status] ?? food.status }}
        </span>
      </li>
    </ul>
    <p v-else class="food-suggestions-modal_empty">Du hast noch nichts vorgeschlagen.</p>

    <template #actions>
      <AppButton variant="secondary" @click="close">Schließen</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { formatMealDayShort, PREFERENCE_LABELS } from '@utils/food'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

defineProps({
  // myFoods aus GET /food
  foods: { type: Array, default: () => [] },
})

const STATUS_LABELS = { New: 'Offen', Accepted: 'Eingeplant', Rejected: 'Abgelehnt' }

const modal = ref(null)

function context(food) {
  if (food.mealId) {
    return `${food.mealTitle}, ${formatMealDayShort(food.date)} ${food.mealTime} Uhr · ${food.eventTitle ?? food.appointmentTitle}`
  }
  return food.eventTitle ? `für ${food.eventTitle} · noch keiner Mahlzeit zugeordnet` : food.organizationTitle
}

function open() {
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

defineExpose({ open, close })
</script>
