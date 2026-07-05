<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="money-entry-modal" @cancel.prevent="close">
      <div class="money-entry-modal_content" @click.stop>

        <div class="money-entry-modal_header">
          <h2 class="hl2 money-entry-modal_title">Buchung erfassen</h2>
          <button class="button--close" aria-label="Schließen" @click="close">✕</button>
        </div>

        <form class="money-entry-modal_body" @submit.prevent="submit">

          <div class="form-field">
            <label class="form-label">Typ</label>
            <div class="multiselect-group">
              <label class="checkbox-label">
                <input type="radio" value="Withdrawal" v-model="form.ChangeType" />
                Ausgabe
              </label>
              <label class="checkbox-label">
                <input type="radio" value="Deposit" v-model="form.ChangeType" />
                Einnahme
              </label>
            </div>
          </div>

          <div class="form-field">
            <label class="form-label" for="entry-amount">Betrag (€) *</label>
            <input id="entry-amount" v-model="form.ChangeAmount" type="number" step="0.01" min="0.01" class="input" placeholder="0,00" required />
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
            <label class="form-label">
              Beleg{{ requiresReceipt ? ' *' : '' }}
            </label>
            <label class="button button--secondary money-entry-modal_file-label">
              {{ receiptFile ? 'Anderen Beleg wählen' : 'Beleg fotografieren / auswählen' }}
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
          </div>

          <div v-if="error" class="money-entry-modal_error">{{ error }}</div>

          <div class="money-entry-modal_actions">
            <button type="button" class="button" @click="close" :disabled="saving">Abbrechen</button>
            <button type="submit" class="button button--primary" :disabled="saving || !canSubmit">
              {{ saving ? 'Speichern…' : 'Erfassen' }}
            </button>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useMoneyStore } from '@stores/money'

const props = defineProps({
  accountId: { type: Number, required: true },
  budgets: { type: Array, default: () => [] },
  requiresReceiptDeposit: { type: Boolean, default: false },
  requiresReceiptWithdrawal: { type: Boolean, default: false },
})
const emit = defineEmits(['created'])
const store = useMoneyStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)
const receiptFile = ref(null)
const receiptPreview = ref(null)

const today = () => new Date().toISOString().slice(0, 10)

const defaultForm = () => ({
  ChangeType: 'Withdrawal',
  ChangeAmount: '',
  ChangeReason: '',
  ChangeDate: today(),
  BudgetID: '',
})

const form = reactive(defaultForm())

const requiresReceipt = computed(() =>
  form.ChangeType === 'Deposit' ? props.requiresReceiptDeposit : props.requiresReceiptWithdrawal
)

const canSubmit = computed(() => {
  if (!form.ChangeAmount || !form.ChangeReason.trim()) return false
  if (requiresReceipt.value && !receiptFile.value) return false
  return true
})

function open() {
  Object.assign(form, defaultForm())
  error.value = null
  resetFile()
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

function resetFile() {
  if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value)
  receiptFile.value = null
  receiptPreview.value = null
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

  try {
    const fd = new FormData()
    fd.append('AccountID', props.accountId)
    fd.append('ChangeType', form.ChangeType)
    fd.append('ChangeAmount', form.ChangeAmount)
    fd.append('ChangeReason', form.ChangeReason.trim())
    fd.append('ChangeDate', form.ChangeDate)
    if (form.BudgetID) fd.append('BudgetID', form.BudgetID)
    if (receiptFile.value) fd.append('receipt', receiptFile.value)

    const response = await store.createEntry(fd)

    if (response.success) {
      emit('created', response.data.entry)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erfassen der Buchung.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
