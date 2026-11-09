<!--
  Balken für ausgelegte Ausgaben: ausgezahlter Teil (grün) und noch offener Teil (gelb).
  debt: { Total, Paid, Open } aus account.Debts (MoneyApiController::formatDebts)
-->
<template>
  <div class="money-progress money-debt-progress">
    <div
      class="money-progress_bar"
      role="img"
      :aria-label="`${formatCurrency(debt.Paid)} von ${formatCurrency(debt.Total)} ausgezahlt`"
    >
      <div class="money-progress_fill money-progress_fill--paid" :style="{ width: paidPercent + '%' }"></div>
      <div class="money-progress_fill money-progress_fill--open" :style="{ width: openPercent + '%' }"></div>
    </div>
    <span v-if="showLabel" class="money-progress_label">
      {{ formatCurrency(debt.Paid) }} von {{ formatCurrency(debt.Total) }} ausgezahlt
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  debt: { type: Object, required: true },
  showLabel: { type: Boolean, default: true },
})

const total = computed(() => Math.max(props.debt.Paid + props.debt.Open, 0.01))
const paidPercent = computed(() => (props.debt.Paid / total.value) * 100)
const openPercent = computed(() => (props.debt.Open / total.value) * 100)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}
</script>
