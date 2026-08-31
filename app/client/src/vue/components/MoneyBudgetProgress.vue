<template>
  <div v-if="budget.HasBudget" class="money-progress">
    <div class="money-progress_bar">
      <div class="money-progress_fill" :style="{ width: withinPercent + '%' }"></div>
      <div v-if="isOver" class="money-progress_fill money-progress_fill--over" :style="{ width: overPercent + '%' }"></div>
      <div v-if="pendingPercent > 0" class="money-progress_fill money-progress_fill--pending" :style="{ width: pendingPercent + '%' }"></div>
      <div v-if="showMarker" class="money-progress_marker" :style="{ left: markerPercent + '%' }"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  budget: { type: Object, required: true },
})

const isOver = computed(() => props.budget.Remaining < 0)
const pendingAmount = computed(() => props.budget.PendingAmount || 0)

// Skaliert die Anzeige auf max(Ausgegeben + Ausstehend, Budget): Genehmigter Überschuss
// und noch nicht freigegebene Buchungen werden als eigene Segmente sichtbar, ohne dass
// sich die angezeigte Summe (Spent/Remaining) selbst ändert — nur der Balken wächst mit.
const total = computed(() => Math.max(props.budget.Spent + pendingAmount.value, props.budget.Budget, 0.01))
const withinPercent = computed(() => (Math.min(props.budget.Spent, props.budget.Budget) / total.value) * 100)
const overPercent = computed(() => (Math.max(0, -props.budget.Remaining) / total.value) * 100)
const pendingPercent = computed(() => (pendingAmount.value / total.value) * 100)
const markerPercent = computed(() => (props.budget.Budget / total.value) * 100)
const showMarker = computed(() => props.budget.Spent + pendingAmount.value > props.budget.Budget)
</script>
