<template>
  <!-- Ein Gericht im Essensplaner: per Griff/Maus ziehbar, alternativ über die Auswahl zuordnen -->
  <li
    class="food-plan-card"
    :class="{
      'food-plan-card--locked': food.isOrderable,
      'food-plan-card--dragging': dragging,
      'food-plan-card--editable': food.canEdit,
    }"
  >
    <span
      v-if="!food.isOrderable"
      class="food-plan-card_handle"
      data-drag-handle
      aria-hidden="true"
      title="Ziehen, um zuzuordnen"
    >
      <svg width="12" height="18" viewBox="0 0 12 18" fill="currentColor">
        <circle cx="3" cy="3" r="1.6" /><circle cx="9" cy="3" r="1.6" />
        <circle cx="3" cy="9" r="1.6" /><circle cx="9" cy="9" r="1.6" />
        <circle cx="3" cy="15" r="1.6" /><circle cx="9" cy="15" r="1.6" />
      </svg>
    </span>

    <div class="food-plan-card_info">
      <p class="food-plan-card_title">
        {{ food.title }}
        <span v-if="PREFERENCE_LABELS[food.preference]" class="food-plan-card_pref">{{ PREFERENCE_LABELS[food.preference] }}</span>
        <span v-if="food.isOrderable" class="food-plan-card_pref">Bestellbar</span>
      </p>
      <p v-if="provider || suggestedFor" class="food-plan-card_meta">
        <template v-if="provider">von {{ provider }}</template>
        <template v-if="provider && suggestedFor"> · </template>
        <template v-if="suggestedFor">gewünscht für {{ suggestedFor }}</template>
      </p>
    </div>

    <template v-if="!food.isOrderable">
      <select
        class="food-plan-card_assign"
        :aria-label="`${food.title} zuordnen`"
        :value="currentZone"
        @change="onSelect"
      >
        <option value="pool">Offen (nicht zugeordnet)</option>
        <optgroup v-for="day in days" :key="day.date" :label="day.label">
          <option v-for="meal in day.meals" :key="meal.id" :value="`meal-${meal.id}`">
            {{ meal.time }} {{ meal.title }}
          </option>
        </optgroup>
      </select>

      <AppIconButton
        v-if="currentZone === 'pool'"
        variant="ghost"
        :aria-label="`${food.title} ablehnen`"
        title="Ablehnen"
        class="food-plan-card_reject"
        @click="$emit('reject')"
      >✕</AppIconButton>
    </template>
  </li>
</template>

<script setup>
import { computed } from 'vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import { PREFERENCE_LABELS } from '@utils/food'

const props = defineProps({
  food: { type: Object, required: true },
  // "pool" oder "meal-<id>"
  currentZone: { type: String, required: true },
  // Tage mit Mahlzeiten für die Auswahl: [{ date, label, meals: [{ id, time, title }] }]
  days: { type: Array, default: () => [] },
  // Text wie "Mittag, Sa., 30.09.", falls direkt an einer Mahlzeit vorgeschlagen
  suggestedFor: { type: String, default: null },
  dragging: { type: Boolean, default: false },
})

const emit = defineEmits(['move', 'reject'])

// Person, die es mitbringt — oder die Organisation, die es selbst stellt
const provider = computed(() => props.food.supplier ?? props.food.organizationTitle ?? null)

function onSelect(event) {
  emit('move', event.target.value)
}
</script>
