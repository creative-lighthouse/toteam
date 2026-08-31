<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="money-settle-modal" @cancel.prevent="close">
      <div class="money-settle-modal_content" @click.stop>

        <div class="money-settle-modal_header">
          <h2 class="hl2 money-settle-modal_title">Zahlung erfassen</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="money-settle-modal_body" @submit.prevent="submit">

          <p v-if="entry" class="money-settle-modal_summary">
            {{ entry.ChangeReason }} — bereits beglichen: <strong>{{ formatCurrency(settledAmount) }}</strong> von {{ formatCurrency(entry.ChangeAmount) }}
          </p>

          <div class="form-field">
            <label class="form-label" for="settle-amount">Betrag (€) *</label>
            <input id="settle-amount" v-model="form.Amount" type="number" step="0.01" min="0.01" class="input" placeholder="0,00" required />
          </div>

          <div class="form-field">
            <label class="form-label" for="settle-date">Datum</label>
            <input id="settle-date" v-model="form.Date" type="date" class="input" />
          </div>

          <div class="form-field">
            <label class="form-label">Zahlungsart</label>
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

          <div v-if="entry?.Settlements?.length" class="money-settle-modal_history">
            <p class="form-label">Bisherige Zahlungen</p>
            <ul class="money-settle-modal_history-list">
              <li v-for="s in entry.Settlements" :key="s.ID">
                {{ formatCurrency(s.Amount) }} ({{ s.PaymentMethod }}) am {{ formatDate(s.Date) }}<span v-if="s.User"> · {{ s.User.Name }}</span>
              </li>
            </ul>
          </div>

          <div v-if="error" class="money-settle-modal_error">{{ error }}</div>

          <div class="money-settle-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !form.Amount">
              {{ saving ? 'Speichern…' : 'Erfassen' }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const emit = defineEmits(['saved'])
const store = useMoneyStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)

// Wird direkt über das Argument von open() gesetzt statt über einen Prop — siehe
// die gleiche Begründung in MoneyEntryModal.vue / MoneyBudgetModal.vue.
const entry = ref(null)

const today = () => new Date().toISOString().slice(0, 10)

const form = reactive({
  Amount: '',
  Date: today(),
  PaymentMethod: 'Bar',
})

const settledAmount = computed(() => entry.value?.SettledAmount || 0)

function open(entryToSettle) {
  entry.value = entryToSettle
  const remaining = entryToSettle ? entryToSettle.ChangeAmount - (entryToSettle.SettledAmount || 0) : 0
  form.Amount = remaining > 0 ? remaining.toFixed(2) : ''
  form.Date = today()
  form.PaymentMethod = 'Bar'
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}

async function submit() {
  if (!entry.value || !form.Amount) return

  saving.value = true
  error.value = null

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
      error.value = response.error || 'Fehler beim Erfassen der Zahlung.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
