<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="money-entry-modal" @cancel.prevent="close">
      <div class="money-entry-modal_content" @click.stop>

        <div class="money-entry-modal_header">
          <h2 class="hl2 money-entry-modal_title">{{ isEdit ? 'Buchung bearbeiten' : 'Buchung erfassen' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="money-entry-modal_body" @submit.prevent="submit">

          <div class="form-field">
            <label class="form-label">Typ</label>
            <div class="multiselect-group">
              <label class="checkbox-label" :class="{ 'checkbox-label--disabled': !canEnterWithdrawal }">
                <input type="radio" value="Withdrawal" v-model="form.ChangeType" :disabled="!canEnterWithdrawal" />
                Ausgabe
              </label>
              <label class="checkbox-label" :class="{ 'checkbox-label--disabled': !canEnterDeposit }">
                <input type="radio" value="Deposit" v-model="form.ChangeType" :disabled="!canEnterDeposit" />
                Einnahme
              </label>
            </div>
          </div>

          <div class="form-field">
            <label class="form-label" for="entry-amount">Betrag (€) *</label>
            <input id="entry-amount" v-model="form.ChangeAmount" type="number" step="0.01" min="0.01" class="input" placeholder="0,00" required />
            <p v-if="amountError" class="money-field-error">{{ amountError }}</p>
          </div>

          <div class="form-field">
            <label class="form-label" for="entry-reason">Grund *</label>
            <input id="entry-reason" v-model="form.ChangeReason" type="text" class="input" placeholder="z.B. Getränkeeinkauf" required />
          </div>

          <div class="form-field">
            <label class="form-label" for="entry-date">Datum</label>
            <input id="entry-date" v-model="form.ChangeDate" type="date" class="input" />
          </div>

          <div v-if="form.ChangeType === 'Withdrawal' && budgets.length" class="form-field">
            <label class="form-label" for="entry-budget">Budget</label>
            <select id="entry-budget" v-model="form.BudgetID" class="input">
              <option value="">Kein Budget</option>
              <option v-for="b in budgets" :key="b.ID" :value="b.ID">{{ b.Title }}</option>
            </select>
          </div>

          <div class="form-field">
            <label class="form-label" for="entry-notes">Anmerkungen</label>
            <textarea id="entry-notes" v-model="form.Notes" class="input" rows="3" placeholder="Weitere Details zu dieser Buchung…"></textarea>
          </div>

          <div class="form-field">
            <label class="form-label">
              Beleg{{ requiresReceipt && !existingReceiptURL ? ' *' : '' }}
            </label>
            <label class="button button--secondary money-entry-modal_file-label">
              {{ receiptFile || existingReceiptURL ? 'Anderen Beleg wählen' : 'Beleg fotografieren / auswählen' }}
              <input
                type="file"
                accept="image/*,application/pdf"
                capture="environment"
                class="file-input-hidden"
                @change="onFileSelected"
              />
            </label>
            <img v-if="receiptPreview" :src="receiptPreview" alt="Beleg-Vorschau" class="money-entry-modal_preview" />
            <p v-else-if="receiptFile" class="money-entry-modal_filename">{{ receiptFile.name }}</p>
            <p v-else-if="existingReceiptURL" class="money-entry-modal_filename">Aktueller Beleg bleibt erhalten, falls kein neuer gewählt wird.</p>
          </div>

          <div v-if="error" class="money-entry-modal_error">{{ error }}</div>

          <div class="money-entry-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !canSubmit">
              {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erfassen') }}
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

const props = defineProps({
  accountId: { type: Number, required: true },
  budgets: { type: Array, default: () => [] },
  requiresReceiptDeposit: { type: Boolean, default: false },
  requiresReceiptWithdrawal: { type: Boolean, default: false },
  canEnterDeposit: { type: Boolean, default: true },
  canEnterWithdrawal: { type: Boolean, default: true },
})
const emit = defineEmits(['saved'])
const store = useMoneyStore()

// Wird direkt über das Argument von open() gesetzt statt über einen Prop:
// ein Prop, der von einer Klick-Handler-Funktion im selben Tick gesetzt wird,
// ist im Kind beim synchronen open()-Aufruf noch nicht aktualisiert (Vue
// patched Props erst beim nächsten Render), sonst greifen beim ersten Öffnen
// noch alte Werte.
const currentEntry = ref(null)
const isEdit = computed(() => !!currentEntry.value)

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)
const receiptFile = ref(null)
const receiptPreview = ref(null)
const existingReceiptURL = ref(null)

// Deckt sich mit MAX_AMOUNT in MoneyApiController — Feedback direkt im Formular statt
// erst nach einem fehlschlagenden Server-Roundtrip.
const MAX_AMOUNT = 999999999.99

const today = () => new Date().toISOString().slice(0, 10)

const defaultForm = () => ({
  ChangeType: props.canEnterWithdrawal ? 'Withdrawal' : 'Deposit',
  ChangeAmount: '',
  ChangeReason: '',
  ChangeDate: today(),
  BudgetID: '',
  Notes: '',
})

const form = reactive(defaultForm())

const requiresReceipt = computed(() =>
  form.ChangeType === 'Deposit' ? props.requiresReceiptDeposit : props.requiresReceiptWithdrawal
)

const serverAmountError = ref(null)

const clientAmountError = computed(() => {
  const value = parseFloat(form.ChangeAmount)
  if (form.ChangeAmount === '' || Number.isNaN(value)) return null
  if (value > MAX_AMOUNT) return `Der Betrag darf maximal ${formatCurrency(MAX_AMOUNT)} betragen`
  return null
})

const amountError = computed(() => clientAmountError.value || serverAmountError.value)

const canSubmit = computed(() => {
  if (!form.ChangeAmount || !form.ChangeReason.trim()) return false
  if (amountError.value) return false
  if (requiresReceipt.value && !receiptFile.value && !existingReceiptURL.value) return false
  return true
})

function fillFromEntry(entry) {
  if (!entry) return
  form.ChangeType = entry.ChangeType
  form.ChangeAmount = entry.ChangeAmount
  form.ChangeReason = entry.ChangeReason
  form.ChangeDate = entry.ChangeDate ? entry.ChangeDate.slice(0, 10) : today()
  form.BudgetID = entry.Budget?.ID ?? ''
  form.Notes = entry.Notes || ''
  existingReceiptURL.value = entry.ReceiptURL || null
}

function open(entryToEdit = null, defaultBudgetId = null) {
  currentEntry.value = entryToEdit
  Object.assign(form, defaultForm())
  error.value = null
  serverAmountError.value = null
  resetFile()
  if (entryToEdit) {
    fillFromEntry(entryToEdit)
  } else if (defaultBudgetId) {
    form.BudgetID = defaultBudgetId
  }
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function resetFile() {
  if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value)
  receiptFile.value = null
  receiptPreview.value = null
  existingReceiptURL.value = null
}

function onFileSelected(e) {
  const file = e.target.files[0]
  if (!file) return

  if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) {
    error.value = 'Nur PNG, JPEG und PDF sind erlaubt.'
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    error.value = 'Die Datei darf maximal 5 MB groß sein.'
    return
  }

  error.value = null
  if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value)
  receiptFile.value = file
  receiptPreview.value = file.type === 'application/pdf' ? null : URL.createObjectURL(file)
}

async function submit() {
  if (!canSubmit.value) return

  saving.value = true
  error.value = null
  serverAmountError.value = null

  try {
    const fd = new FormData()
    fd.append('AccountID', props.accountId)
    fd.append('ChangeType', form.ChangeType)
    fd.append('ChangeAmount', form.ChangeAmount)
    fd.append('ChangeReason', form.ChangeReason.trim())
    fd.append('ChangeDate', form.ChangeDate)
    fd.append('Notes', form.Notes.trim())
    if (form.BudgetID) fd.append('BudgetID', form.BudgetID)
    if (receiptFile.value) fd.append('receipt', receiptFile.value)

    const response = isEdit.value
      ? await store.updateEntry(currentEntry.value.ID, fd)
      : await store.createEntry(fd)

    if (response.success) {
      emit('saved', response.data.entry)
      close()
    } else {
      const msg = response.error || 'Fehler beim Speichern der Buchung.'
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
