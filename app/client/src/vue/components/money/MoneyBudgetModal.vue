<template>
  <AppModal ref="modal" class="money-budget-modal" :title="isEdit ? 'Budget bearbeiten' : 'Neues Budget'" @close="close">
    <form id="money-budget-form" class="modalform" @submit.prevent="submit">

      <label class="field">
        Titel *
        <input id="budget-title" v-model="form.Title" type="text" placeholder="z.B. Sommerfest" required />
      </label>

      <AppToggle v-model="form.HasBudget" label="Budget-Limit festlegen" />

      <div v-if="form.HasBudget" class="field">
        <label for="budget-amount">Budget (€)</label>
        <input id="budget-amount" v-model="form.Budget" type="number" step="0.01" min="0" />
        <p v-if="amountError" class="money-field-error">{{ amountError }}</p>
      </div>

      <AppToggle v-if="form.HasBudget" v-model="form.CanBeOverBudget" label="Kann über Budget gehen" />

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton
        v-if="isEdit"
        variant="danger"
        :disabled="saving || deleting"
        @click="remove"
      >{{ deleting ? 'Wird gelöscht…' : 'Löschen' }}</AppButton>
      <AppButton type="submit" form="money-budget-form" variant="primary" :disabled="saving || deleting || !form.Title.trim() || !!amountError">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'

const props = defineProps({
  accountId: { type: Number, required: true },
})
const emit = defineEmits(['saved'])
const store = useMoneyStore()

const modal = ref(null)
const saving = ref(false)
const deleting = ref(false)
const error = ref(null)
const serverAmountError = ref(null)

// Deckt sich mit MAX_AMOUNT in MoneyApiController — Feedback direkt im Formular statt
// erst nach einem fehlschlagenden Server-Roundtrip.
const MAX_AMOUNT = 999999999.99

// Wird direkt über das Argument von open() gesetzt statt über einen Prop:
// ein Prop, der von einer Klick-Handler-Funktion im selben Tick gesetzt wird,
// ist im Kind beim synchronen open()-Aufruf noch nicht aktualisiert (Vue
// patched Props erst beim nächsten Render), sonst greifen beim ersten Öffnen
// noch alte Werte.
const currentBudget = ref(null)
const isEdit = computed(() => !!currentBudget.value)

const defaultForm = () => ({
  Title: '',
  HasBudget: false,
  Budget: 0,
  CanBeOverBudget: false,
})

const form = reactive(defaultForm())

const clientAmountError = computed(() => {
  const value = parseFloat(form.Budget)
  if (!form.HasBudget || form.Budget === '' || Number.isNaN(value)) return null
  if (value > MAX_AMOUNT) return `Der Betrag darf maximal ${formatCurrency(MAX_AMOUNT)} betragen`
  return null
})

const amountError = computed(() => clientAmountError.value || serverAmountError.value)

function fillFromBudget(budget) {
  if (!budget) return
  form.Title = budget.Title
  form.HasBudget = budget.HasBudget
  form.Budget = budget.Budget
  form.CanBeOverBudget = budget.CanBeOverBudget
}

function open(budgetToEdit = null) {
  currentBudget.value = budgetToEdit
  Object.assign(form, defaultForm())
  if (budgetToEdit) fillFromBudget(budgetToEdit)
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

async function submit() {
  if (!form.Title.trim() || amountError.value) return

  saving.value = true
  error.value = null
  serverAmountError.value = null

  const payload = {
    Title: form.Title.trim(),
    HasBudget: form.HasBudget,
    Budget: parseFloat(form.Budget) || 0,
    CanBeOverBudget: form.CanBeOverBudget,
  }

  try {
    const response = isEdit.value
      ? await store.updateBudget(currentBudget.value.ID, payload)
      : await store.createBudget({ ...payload, AccountID: props.accountId })

    if (response.success) {
      emit('saved', response.data.budget)
      close()
    } else {
      const msg = response.error || 'Fehler beim Speichern des Budgets.'
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

async function remove() {
  if (!isEdit.value || deleting.value) return
  if (!confirm(`Budget "${currentBudget.value.Title}" wirklich löschen? Buchungen bleiben erhalten, verlieren aber die Budget-Zuordnung.`)) return

  deleting.value = true
  error.value = null

  try {
    const response = await store.removeBudget(currentBudget.value.ID)
    if (response.success) {
      emit('saved', null)
      close()
    } else {
      error.value = response.error || 'Fehler beim Löschen des Budgets.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    deleting.value = false
  }
}

defineExpose({ open, close })
</script>
