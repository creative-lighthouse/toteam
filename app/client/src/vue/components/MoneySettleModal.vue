<template>
  <AppModal ref="modal" class="money-settle-modal" :title="canSettle ? 'Zahlung erfassen' : 'Zahlungen'" @close="close">
    <form id="money-settle-form" class="modalform" @submit.prevent="submit">

      <p v-if="entry" class="money-settle-modal_summary">
        {{ entry.ChangeReason }} — bereits beglichen: <strong>{{ formatCurrency(settledAmount) }}</strong> von {{ formatCurrency(entry.ChangeAmount) }}
      </p>

      <template v-if="canSettle">
        <div class="field">
          <label for="settle-amount">Betrag (€) *</label>
          <input id="settle-amount" v-model="form.Amount" type="number" step="0.01" min="0.01" placeholder="0,00" required />
          <p v-if="amountError" class="money-field-error">{{ amountError }}</p>
        </div>

        <label class="field">
          Datum
          <input id="settle-date" v-model="form.Date" type="date" />
        </label>

        <div class="field">
          <label>Zahlungsart</label>
          <div class="multiselect-group">
            <label class="checkbox-label">
              <input type="radio" value="Bar" v-model="form.PaymentMethod" />
              Bar
            </label>
            <label class="checkbox-label">
              <input type="radio" value="EC" v-model="form.PaymentMethod" />
              EC
            </label>
          </div>
        </div>
      </template>

      <div v-if="entry?.Settlements?.length" class="money-settle-modal_history">
        <p class="form-label">Bisherige Zahlungen</p>
        <ul class="money-settle-modal_history-list">
          <li v-for="s in entry.Settlements" :key="s.ID">
            {{ formatCurrency(s.Amount) }} ({{ s.PaymentMethod }}) am {{ formatDate(s.Date) }}<span v-if="s.User"> · {{ s.User.Name }}</span>
          </li>
        </ul>
      </div>
      <p v-else-if="!canSettle" class="money-settle-modal_summary">Noch keine Zahlungen erfasst.</p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton v-if="canSettle" variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton v-else variant="secondary" @click="close">Schließen</AppButton>
      <AppButton v-if="canSettle" type="submit" form="money-settle-form" variant="primary" :disabled="saving || !form.Amount || !!amountError">
        {{ saving ? 'Speichern…' : 'Erfassen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const props = defineProps({
  // Wer nicht begleichen darf (z.B. die einreichende Person selbst), darf das Modal
  // trotzdem öffnen, sieht dann aber nur Status/Historie statt des Erfassen-Formulars.
  canSettle: { type: Boolean, default: true },
})

const emit = defineEmits(['saved'])
const store = useMoneyStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const serverAmountError = ref(null)

// Wird direkt über das Argument von open() gesetzt statt über einen Prop — siehe
// die gleiche Begründung in MoneyEntryModal.vue / MoneyBudgetModal.vue.
const entry = ref(null)

// Deckt sich mit MAX_AMOUNT in MoneyApiController — Feedback direkt im Formular statt
// erst nach einem fehlschlagenden Server-Roundtrip.
const MAX_AMOUNT = 999999999.99

const today = () => new Date().toISOString().slice(0, 10)

const form = reactive({
  Amount: '',
  Date: today(),
  PaymentMethod: 'Bar',
})

const settledAmount = computed(() => entry.value?.SettledAmount || 0)

const clientAmountError = computed(() => {
  const value = parseFloat(form.Amount)
  if (form.Amount === '' || Number.isNaN(value)) return null
  if (value > MAX_AMOUNT) return `Der Betrag darf maximal ${formatCurrency(MAX_AMOUNT)} betragen`
  return null
})

const amountError = computed(() => clientAmountError.value || serverAmountError.value)

function open(entryToSettle) {
  entry.value = entryToSettle
  const remaining = entryToSettle ? entryToSettle.ChangeAmount - (entryToSettle.SettledAmount || 0) : 0
  form.Amount = remaining > 0 ? remaining.toFixed(2) : ''
  form.Date = today()
  form.PaymentMethod = 'Bar'
  error.value = null
  serverAmountError.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}

async function submit() {
  if (!props.canSettle || !entry.value || !form.Amount || amountError.value) return

  saving.value = true
  error.value = null
  serverAmountError.value = null

  try {
    const response = await store.settleEntry(entry.value.ID, {
      Amount: form.Amount,
      Date: form.Date,
      PaymentMethod: form.PaymentMethod,
    })

    if (response.success) {
      emit('saved', response.data.entry)
      close()
    } else {
      const msg = response.error || 'Fehler beim Erfassen der Zahlung.'
      if (msg.startsWith('Der Betrag')) {
        serverAmountError.value = msg
      } else {
        error.value = msg
      }
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
