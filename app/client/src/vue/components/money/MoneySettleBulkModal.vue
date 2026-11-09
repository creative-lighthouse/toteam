<!--
  Mehrere ausgewählte Buchungen auf einmal begleichen (aus der Mehrfachauswahl im
  Buchungsverlauf): jede offene Ausgabe wird um ihren Rest beglichen. Buchungen ohne
  offenen Betrag (Einnahmen, ausstehende, bereits beglichene) werden übersprungen.

    bulkModal.value.open(entries)  →  @saved="({ settled, skipped, amount }) => …"
-->
<template>
  <AppModal ref="modal" class="money-settle-bulk-modal" title="Zahlungen erfassen" @close="close">
    <form id="money-settle-bulk-form" class="modalform" @submit.prevent="submit">
      <p class="money-settle-bulk-modal_summary">
        <strong>{{ openEntries.length === 1 ? '1 Buchung' : `${openEntries.length} Buchungen` }}</strong>
        werden vollständig beglichen — zusammen <strong>{{ formatCurrency(openAmount) }}</strong>.
        <template v-if="skippedCount">
          {{ skippedCount === 1 ? '1 weitere ausgewählte Buchung hat' : `${skippedCount} weitere ausgewählte Buchungen haben` }}
          nichts offen und {{ skippedCount === 1 ? 'bleibt' : 'bleiben' }} unverändert.
        </template>
      </p>

      <ul class="money-settle-bulk-modal_list">
        <li v-for="e in openEntries" :key="e.ID">
          <span class="money-settle-bulk-modal_reason">{{ e.ChangeReason }}</span>
          <span v-if="e.User" class="money-settle-bulk-modal_person">{{ e.User.Name }}</span>
          <span class="money-settle-bulk-modal_amount">{{ formatCurrency(openOf(e)) }}</span>
        </li>
      </ul>

      <label class="field">
        Datum
        <input id="settle-bulk-date" v-model="form.Date" type="date" />
      </label>

      <AppSegmentedToggle
        v-model="form.PaymentMethod"
        label="Zahlungsart"
        :options="[{ value: 'Bar', label: 'Bar' }, { value: 'EC', label: 'EC' }]"
      />

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="money-settle-bulk-form" variant="primary" :disabled="saving || !openEntries.length">
        {{ saving ? 'Speichern…' : `${formatCurrency(openAmount)} begleichen` }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import { openAmountOf } from '@utils/moneyEntries'

const props = defineProps({
  accountId: { type: Number, required: true },
})
const emit = defineEmits(['saved'])
const store = useMoneyStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
// Über open() gesetzt (siehe Begründung in MoneyEntryModal.vue)
const entries = ref([])

const today = () => new Date().toISOString().slice(0, 10)
const form = reactive({ Date: today(), PaymentMethod: 'Bar' })

const openOf = openAmountOf
const openEntries = computed(() => entries.value.filter(e => openAmountOf(e) > 0))
const openAmount = computed(() => openEntries.value.reduce((sum, e) => sum + openAmountOf(e), 0))
const skippedCount = computed(() => entries.value.length - openEntries.value.length)

function open(selectedEntries) {
  entries.value = selectedEntries
  form.Date = today()
  form.PaymentMethod = 'Bar'
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!openEntries.value.length) return
  saving.value = true
  error.value = null
  try {
    const response = await store.settleEntries(props.accountId, openEntries.value.map(e => e.ID), {
      Date: form.Date,
      PaymentMethod: form.PaymentMethod,
    })
    if (response.success) {
      emit('saved', response.data)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erfassen der Zahlungen.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

defineExpose({ open, close })
</script>
