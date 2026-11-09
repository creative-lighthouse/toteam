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
        <MoneyEntrySettleBadge :entry="entry" :can-settle="canSettle" @settle="$emit('settle', $event)" />
        <a
          v-if="entry.ReceiptURL"
          class="money-entry_receipt"
          :href="entry.ReceiptURL"
          :data-type="entry.ReceiptURL.toLowerCase().endsWith('.pdf') ? 'external' : 'image'"
          @click.stop
        >Beleg</a>
        <span v-if="!entry.Approved" class="money-entry_pending-badge">Ausstehend</span>
        <span v-if="entry.Budget" class="money-entry_budget-tag">{{ entry.Budget.Title }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import MoneyEntrySettleBadge from '@components/money/MoneyEntrySettleBadge.vue'

const props = defineProps({
  entry: { type: Object, required: true },
  canSettle: { type: Boolean, default: false },
})
defineEmits(['settle'])

const sign = computed(() => (props.entry.ChangeType === 'Deposit' ? '+' : '-'))
const amountClass = computed(() =>
  props.entry.ChangeType === 'Deposit' ? 'money-entry_amount--deposit' : 'money-entry_amount--withdrawal'
)

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}
</script>
