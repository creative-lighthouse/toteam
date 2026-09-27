<template>
  <AppModal ref="modal" class="poll-finalize-modal" title="Termin festlegen" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <label class="field">
        Welcher Termin soll es werden?
        <select v-model="selectedId" required>
          <option v-for="option in options" :key="option.OptionID" :value="option.OptionID">
            {{ optionLabel(option) }}
          </option>
        </select>
      </label>

      <dl v-if="selected" class="field poll-finalize-modal_voters">
        <template v-for="group in VOTE_GROUPS" :key="group.type">
          <dt :class="['poll-finalize-modal_count', `poll-finalize-modal_count--${group.tone}`]">
            {{ count(selected, group.type) }} {{ group.label }}
          </dt>
          <dd>{{ names(selected, group.type) || '–' }}</dd>
        </template>
      </dl>

      <p class="field poll-finalize-modal_hint">
        Aus der Terminfindung wird ein fester Termin. Die übrigen Optionen und die Terminfindung selbst werden danach gelöscht.
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !selectedId">
        {{ saving ? 'Wird festgelegt…' : 'Termin festlegen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const emit = defineEmits(['finalized'])
const eventsStore = useEventsStore()

const formId = `poll-finalize-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const pollId = ref(null)
const options = ref([])
const selectedId = ref(null)
const saving = ref(false)
const error = ref(null)

const selected = computed(() => options.value.find(o => o.OptionID === selectedId.value) ?? null)

function count(option, type) {
  return (option.Participations || []).filter(p => p.Type === type).length
}

const VOTE_GROUPS = [
  { type: 'Accept', label: 'Zugesagt', tone: 'accept' },
  { type: 'Maybe', label: 'Vielleicht', tone: 'maybe' },
  { type: 'Decline', label: 'Abgesagt', tone: 'decline' },
]

function names(option, type) {
  return (option.Participations || [])
    .filter(p => p.Type === type)
    .map(p => p.MemberName)
    .sort((a, b) => a.localeCompare(b, 'de'))
    .join(', ')
}

function optionLabel(option) {
  const time = option.RenderTime ? `, ${option.RenderTime}` : ''
  return `${option.RenderDate}${time} – ${count(option, 'Accept')} Zu, ${count(option, 'Maybe')} Vielleicht, ${count(option, 'Decline')} Ab`
}

/**
 * @param {{ pollId: number, options: object[] }} poll Optionen chronologisch sortiert
 */
function open({ pollId: id, options: list }) {
  pollId.value = id
  options.value = list
  // Vorschlag: meiste Zusagen, dann meiste Vielleicht, dann wenigste Absagen
  const best = [...list].sort((a, b) =>
    (count(b, 'Accept') - count(a, 'Accept'))
    || (count(b, 'Maybe') - count(a, 'Maybe'))
    || (count(a, 'Decline') - count(b, 'Decline'))
  )[0]
  selectedId.value = best?.OptionID ?? null
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!selected.value) return
  saving.value = true
  error.value = null
  try {
    await eventsStore.finalizePoll(pollId.value, selected.value.OptionID)
    close()
    emit('finalized')
  } catch (err) {
    error.value = err.message || 'Termin konnte nicht festgelegt werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
