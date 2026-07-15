<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="money-account-modal" @cancel.prevent="close">
      <div class="money-account-modal_content" @click.stop>

        <div class="money-account-modal_header">
          <h2 class="hl2 money-account-modal_title">{{ mode === 'create' ? 'Neue Kasse' : 'Kasse bearbeiten' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="money-account-modal_body" @submit.prevent="submit">

          <div v-if="mode === 'create'" class="form-field">
            <label class="form-label">Organisation</label>
            <div class="multiselect-group">
              <label v-for="org in adminOrgs" :key="org.ID" class="checkbox-label">
                <input type="radio" :value="org.ID" v-model="form.OrganizationID" />
                {{ org.Title }}
              </label>
            </div>
          </div>

          <div class="form-field">
            <label class="form-label" for="account-title">Titel *</label>
            <input id="account-title" v-model="form.Title" type="text" class="input" placeholder="z.B. Vereinskasse" required />
          </div>

          <div class="form-field">
            <label class="form-label" for="account-iban">IBAN</label>
            <input id="account-iban" v-model="form.IBAN" type="text" class="input" placeholder="DE00 0000 0000 0000 0000 00" />
          </div>

          <div class="form-field-row">
            <div class="form-field">
              <label class="form-label" for="account-start">Startbetrag (€)</label>
              <input id="account-start" v-model="form.StartingAmount" type="number" step="0.01" class="input" />
            </div>
            <div class="form-field">
              <label class="form-label" for="account-target">Zielbetrag (€)</label>
              <input id="account-target" v-model="form.TargetAmount" type="number" step="0.01" class="input" />
            </div>
          </div>

          <label class="form-checkbox">
            <input type="checkbox" v-model="form.RequiresApproval" />
            Buchungen müssen freigegeben werden
          </label>

          <label class="form-checkbox">
            <input type="checkbox" v-model="form.RequiresReceiptDeposit" />
            Beleg für Einnahmen erforderlich
          </label>

          <label class="form-checkbox">
            <input type="checkbox" v-model="form.RequiresReceiptWithdrawal" />
            Beleg für Ausgaben erforderlich
          </label>

          <div v-if="error" class="money-account-modal_error">{{ error }}</div>

          <div class="money-account-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !canSubmit">
              {{ saving ? 'Speichern…' : (mode === 'create' ? 'Erstellen' : 'Speichern') }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const props = defineProps({
  mode: { type: String, default: 'create' },
  account: { type: Object, default: null },
  adminOrgs: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const store = useMoneyStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)

const defaultForm = () => ({
  OrganizationID: props.adminOrgs.length === 1 ? props.adminOrgs[0].ID : 0,
  Title: '',
  IBAN: '',
  StartingAmount: 0,
  TargetAmount: 0,
  RequiresApproval: false,
  RequiresReceiptDeposit: false,
  RequiresReceiptWithdrawal: false,
})

const form = reactive(defaultForm())

const canSubmit = computed(() => {
  if (!form.Title.trim()) return false
  if (props.mode === 'create' && !form.OrganizationID) return false
  return true
})

function fillFromAccount(account) {
  if (!account) return
  form.Title = account.Title
  form.IBAN = account.IBAN
  form.StartingAmount = account.StartingAmount
  form.TargetAmount = account.TargetAmount
  form.RequiresApproval = account.RequiresApproval
  form.RequiresReceiptDeposit = account.RequiresReceiptDeposit
  form.RequiresReceiptWithdrawal = account.RequiresReceiptWithdrawal
}

watch(() => props.account, fillFromAccount)

function open() {
  Object.assign(form, defaultForm())
  if (props.mode === 'edit') fillFromAccount(props.account)
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function submit() {
  if (!canSubmit.value) return

  saving.value = true
  error.value = null

  const payload = {
    Title: form.Title.trim(),
    IBAN: form.IBAN,
    StartingAmount: parseFloat(form.StartingAmount) || 0,
    TargetAmount: parseFloat(form.TargetAmount) || 0,
    RequiresApproval: form.RequiresApproval,
    RequiresReceiptDeposit: form.RequiresReceiptDeposit,
    RequiresReceiptWithdrawal: form.RequiresReceiptWithdrawal,
  }
  if (props.mode === 'create') {
    payload.OrganizationID = parseInt(form.OrganizationID)
  }

  try {
    const response = props.mode === 'create'
      ? await store.createAccount(payload)
      : await store.updateAccount(props.account.ID, payload)

    if (response.success) {
      emit('saved', response.data.account)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Kasse.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
