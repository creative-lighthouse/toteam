<!--
  Budget-Ansicht des Buchungsverlaufs: eine Spalte je Budget plus "Ohne Budget",
  seitwärts scrollbar wie ein Kanban-Board; jede Spalte scrollt und lädt einzeln.
  Ist im Filter ein Budget gewählt, wird nur dessen Spalte gezeigt.
-->
<template>
  <div class="money-budget-board" tabindex="0" aria-label="Buchungen nach Budget">
    <MoneyBudgetColumn
      v-for="column in columns"
      :key="column.key"
      :account-id="accountId"
      :budget="column.budget"
      :query="query"
      :can-settle="canSettle"
      :version="version"
      @open="$emit('open', $event)"
      @settle="$emit('settle', $event)"
      @loaded="$emit('loaded')"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import MoneyBudgetColumn from '@components/money/MoneyBudgetColumn.vue'

const props = defineProps({
  accountId: { type: Number, required: true },
  budgets: { type: Array, default: () => [] },
  // Suche/Filter/Sortierung des Buchungsverlaufs; budget: '' (alle), 'none' oder eine ID
  query: { type: Object, required: true },
  canSettle: { type: Boolean, default: false },
  version: { type: Number, default: 0 },
})
defineEmits(['open', 'settle', 'loaded'])

const columns = computed(() => {
  const all = [
    ...props.budgets.map(budget => ({ key: budget.ID, budget })),
    { key: 'none', budget: null },
  ]
  const selected = props.query.budget
  if (!selected) return all
  return all.filter(c => (selected === 'none' ? c.budget === null : String(c.budget?.ID) === String(selected)))
})

// Die Spalten bekommen den Filter ohne Budget — das Budget ist die Spalte selbst
const query = computed(() => {
  const { budget, ...rest } = props.query
  return rest
})
</script>
