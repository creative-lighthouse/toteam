<template>
  <div class="money-entry_row">
    <div class="money-entry_main">
      <div class="money-entry_top">
        <span class="money-entry_reason">{{ entry.ChangeReason }}</span>
        <span class="money-entry_amount" :class="amountClass">{{ sign }} {{ formatCurrency(entry.ChangeAmount) }}</span>
      </div>
      <div class="money-entry_meta">
        <span>{{ formatDate(entry.ChangeDate) }}</span>
        <span v-if="entry.User">· {{ entry.User.Name }}</span>
        <span v-if="entry.Budget" class="money-entry_budget-tag">{{ entry.Budget.Title }}</span>
        <button
          v-if="showSettleBadge"
          type="button"
          class="money-entry_settle-badge"
          :class="`money-entry_settle-badge--${settleStatus}`"
          :title="settleTitle"
          @click.stop="$emit('settle', entry)"
        >{{ settleLabel }}</button>
        <a
          v-if="entry.ReceiptURL"
          class="money-entry_receipt"
          :href="entry.ReceiptURL"
          :data-type="entry.ReceiptURL.toLowerCase().endsWith('.pdf') ? 'external' : 'image'"
        >Beleg</a>
        <span v-if="!entry.Approved" class="money-entry_pending-badge">Ausstehend</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  entry: { type: Object, required: true },
  canSettle: { type: Boolean, default: false },
})
defineEmits(['settle'])

const sign = computed(() => (props.entry.ChangeType === 'Deposit' ? '+' : '-'))
const amountClass = computed(() =>
  props.entry.ChangeType === 'Deposit' ? 'money-entry_amount--deposit' : 'money-entry_amount--withdrawal'
)

// Begleichen ist nur für freigegebene Ausgaben relevant — bei Einnahmen gibt es
// nichts zu begleichen, bei noch nicht freigegebenen Buchungen ist der Betrag noch nicht final.
const showSettleBadge = computed(() => props.canSettle && props.entry.ChangeType === 'Withdrawal' && props.entry.Approved)

const settledAmount = computed(() => props.entry.SettledAmount || 0)

const settleStatus = computed(() => {
  if (settledAmount.value <= 0) return 'none'
  if (settledAmount.value >= props.entry.ChangeAmount) return 'full'
  return 'partial'
})

const settleLabel = computed(() => {
  if (settleStatus.value === 'none') return 'Offen'
  if (settleStatus.value === 'full') return 'Beglichen'
  return `${formatCurrency(settledAmount.value)} beglichen`
})

const settleTitle = computed(() =>
  `${formatCurrency(settledAmount.value)} von ${formatCurrency(props.entry.ChangeAmount)} beglichen – Zahlung erfassen`
)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}
</script>
