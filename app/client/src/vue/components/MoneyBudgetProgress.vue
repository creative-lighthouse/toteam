<template>
  <div v-if="budget.HasBudget" class="money-progress">
    <div class="money-progress_bar">
      <div class="money-progress_fill" :style="{ width: withinPercent + '%' }"></div>
      <div v-if="isOver" class="money-progress_fill money-progress_fill--over" :style="{ width: overPercent + '%' }"></div>
      <div v-if="isOver" class="money-progress_marker" :style="{ left: markerPercent + '%' }"></div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  budget: { type: Object, required: true },
})

const isOver = computed(() => props.budget.Remaining < 0)

// Skaliert die Anzeige auf max(Ausgegeben, Budget), damit der Überschuss als eigenes
// Segment über die Budget-Marke hinaus sichtbar wird, statt den Balken nur voll-rot zu färben.
const total = computed(() => Math.max(props.budget.Spent, props.budget.Budget, 0.01))
const withinPercent = computed(() => (Math.min(props.budget.Spent, props.budget.Budget) / total.value) * 100)
const overPercent = computed(() => (Math.max(0, -props.budget.Remaining) / total.value) * 100)
const markerPercent = computed(() => withinPercent.value)
</script>
