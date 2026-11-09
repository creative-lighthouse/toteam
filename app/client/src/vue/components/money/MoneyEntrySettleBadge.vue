<!--
  Begleichen-Status einer Buchung ("Offen" / "x € beglichen" / "Beglichen"), klickbar
  zum Erfassen bzw. Ansehen der Zahlungen (MoneySettleModal). Rendert nichts, wenn
  der Status hier nicht relevant ist. Genutzt in Liste, Tabelle und Budget-Board.
-->
<template>
  <button
    v-if="visible"
    type="button"
    class="money-entry_settle-badge"
    :class="`money-entry_settle-badge--${status}`"
    :title="title"
    @click.stop="$emit('settle', entry)"
  >{{ label }}</button>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'

const props = defineProps({
  entry: { type: Object, required: true },
  canSettle: { type: Boolean, default: false },
})
defineEmits(['settle'])

const authStore = useAuthStore()
const isOwnEntry = computed(() => !!props.entry.User && props.entry.User.ID === authStore.user?.ID)

// Begleichen ist nur für freigegebene Ausgaben relevant — bei Einnahmen gibt es
// nichts zu begleichen, bei noch nicht freigegebenen Buchungen ist der Betrag noch nicht final.
// Freigeber dürfen begleichen, Buchende dürfen den Status/die Historie ihrer eigenen
// Buchungen einsehen (das Modal öffnet dann nur lesend, siehe MoneySettleModal).
const visible = computed(() =>
  (props.canSettle || isOwnEntry.value) && props.entry.ChangeType === 'Withdrawal' && props.entry.Approved
)

const settledAmount = computed(() => props.entry.SettledAmount || 0)

const status = computed(() => {
  if (settledAmount.value <= 0) return 'none'
  if (settledAmount.value >= props.entry.ChangeAmount) return 'full'
  return 'partial'
})

const label = computed(() => {
  if (status.value === 'none') return 'Offen'
  if (status.value === 'full') return 'Beglichen'
  return `${formatCurrency(settledAmount.value)} beglichen`
})

const title = computed(() => {
  const base = `${formatCurrency(settledAmount.value)} von ${formatCurrency(props.entry.ChangeAmount)} beglichen`
  return props.canSettle ? `${base} – Zahlung erfassen` : `${base} – Details ansehen`
})

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}
</script>
