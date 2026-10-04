<template>
  <AppModal ref="modal" class="org-event-money-add-modal" title="Kasse hinzufügen" @close="close">
    <form id="org-event-money-add-form" class="modalform" @submit.prevent="submit">
      <AppSegmentedToggle v-if="modes.length > 1" v-model="mode" :options="modes" />

      <label v-if="mode === 'existing'" class="field">
        Vorhandene Kasse
        <select v-model="accountId" required>
          <option :value="null" disabled>— Kasse wählen —</option>
          <option v-for="a in availableAccounts" :key="a.ID" :value="a.ID">
            {{ a.Title }}{{ a.EventTitle ? ` (bisher: ${a.EventTitle})` : '' }}
          </option>
        </select>
      </label>

      <template v-else>
        <label class="field">
          Titel *
          <input v-model="title" type="text" placeholder="z.B. Kasse Halloweenhaus" required />
        </label>
        <label class="field">
          Zielbetrag (€)
          <input v-model="targetAmount" type="number" step="0.01" min="0" placeholder="optional" />
        </label>
      </template>

      <p class="org-event-money-add-modal_hint">
        {{ mode === 'existing'
          ? 'Die Kasse wird diesem Event zugeordnet — gehört sie schon zu einem anderen Event, wird sie umgehängt.'
          : 'Die neue Kasse gehört zur Organisation des Events. Budgets und weitere Einstellungen legst du danach in der Kasse fest.' }}
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-event-money-add-form" variant="primary" :disabled="saving || !valid">
        {{ saving ? 'Speichern…' : 'Hinzufügen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useMoneyStore } from '@stores/money'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
  // Stand aus MoneyApiController::formatEventAccounts()
  availableAccounts: { type: Array, default: () => [] },
  canCreate: { type: Boolean, default: false },
})

// Neuer Gesamtstand nach dem Hinzufügen
const emit = defineEmits(['saved'])
const store = useMoneyStore()

const modal = ref(null)
const mode = ref('existing')
const accountId = ref(null)
const title = ref('')
const targetAmount = ref('')
const saving = ref(false)
const error = ref(null)

const modes = computed(() => [
  props.availableAccounts.length && { value: 'existing', label: 'Vorhandene Kasse' },
  props.canCreate && { value: 'new', label: 'Neue Kasse' },
].filter(Boolean))

const valid = computed(() => (mode.value === 'existing' ? !!accountId.value : !!title.value.trim()))

function open() {
  mode.value = modes.value[0]?.value ?? 'new'
  accountId.value = null
  title.value = ''
  targetAmount.value = ''
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  try {
    const data = mode.value === 'existing'
      ? { AccountID: accountId.value }
      : { Title: title.value.trim(), TargetAmount: parseFloat(targetAmount.value) || 0 }
    emit('saved', await store.attachEventAccount(props.eventId, data))
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
