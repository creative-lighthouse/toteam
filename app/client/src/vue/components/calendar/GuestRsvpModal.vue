<template>
  <!-- "RSVP anpassen" für einen Gast (kein eigener Account): Antwort und — bei Terminen —
       Notiz, eigene Zeit und Mitfahrt, wie sie ein Mitglied selbst eintragen würde.
       Gespeichert wird über die an open() übergebene Funktion. -->
  <AppModal ref="modal" class="guest-rsvp-modal" :title="`RSVP anpassen – ${name}`" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <p class="field guest-rsvp-modal_hint">{{ name }} ist Gast ohne eigenen Account – seine Antwort trägst du hier ein.</p>

      <AppSegmentedToggle v-model="form.type" label="Antwort" :options="typeOptions" />

      <template v-if="mode === 'appointment' && ['Accept', 'Maybe'].includes(form.type)">
        <label class="field field--3">
          Eigene Zeit von
          <input v-model="form.timeStart" type="time">
        </label>
        <label class="field field--3">
          bis
          <input v-model="form.timeEnd" type="time">
        </label>
        <p class="field guest-rsvp-modal_hint">Leer lassen = Zeit des Termins.</p>

        <label class="field" :class="form.rideType === 'Offer' ? 'field--4' : ''">
          Mitfahrt
          <select v-model="form.rideType">
            <option value="None">Keine Angabe</option>
            <option value="Need">Braucht eine Mitfahrgelegenheit</option>
            <option value="Offer">Bietet Mitfahrgelegenheit</option>
          </select>
        </label>
        <label v-if="form.rideType === 'Offer'" class="field field--2">
          Freie Plätze
          <input v-model.number="form.rideSeats" type="number" min="0" max="20" inputmode="numeric">
        </label>
      </template>

      <label v-if="mode === 'appointment' && form.type !== 'none'" class="field">
        Notiz
        <textarea v-model="form.notes" rows="2" maxlength="512" placeholder="z.B. kommt später, bringt Kuchen mit…" />
      </label>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const formId = `guest-rsvp-${Math.random().toString(36).slice(2)}`

const modal = ref(null)
const name = ref('')
// 'appointment' (Termin, alle Angaben) oder 'meal' (Mahlzeit, nur Zu-/Absage)
const mode = ref('appointment')
const saving = ref(false)
const error = ref(null)
let onSubmit = null

const form = reactive({ type: 'none', timeStart: '', timeEnd: '', rideType: 'None', rideSeats: 0, notes: '' })

const typeOptions = computed(() => [
  { value: 'Accept', label: 'Zusage' },
  ...(mode.value === 'appointment' ? [{ value: 'Maybe', label: 'Vielleicht' }] : []),
  { value: 'Decline', label: 'Absage' },
  { value: 'none', label: 'Keine' },
])

// "18:00:00" → "18:00" für <input type="time">
const toInputTime = value => (value ? String(value).slice(0, 5) : '')

/**
 * @param {{ name: string, mode?: 'appointment'|'meal', current?: object,
 *           submit: (type: string|null, details: object|null) => Promise<void> }} options
 *   current: bisherige Teilnahme ({ Type, TimeStart, TimeEnd, CustomTimeframe, RideType, RideSeats, Notes })
 */
function open({ name: guestName, mode: openMode = 'appointment', current = null, submit }) {
  name.value = guestName
  mode.value = openMode
  onSubmit = submit
  const type = current?.Type && current.Type !== 'Pending' ? current.Type : 'none'
  Object.assign(form, {
    type,
    timeStart: current?.CustomTimeframe ? toInputTime(current.TimeStart) : '',
    timeEnd: current?.CustomTimeframe ? toInputTime(current.TimeEnd) : '',
    rideType: current?.RideType || 'None',
    rideSeats: current?.RideSeats ?? 0,
    notes: current?.Notes ?? '',
  })
  error.value = null
  saving.value = false
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (saving.value) return
  const type = form.type === 'none' ? null : form.type
  let details = null
  if (mode.value === 'appointment' && type) {
    const withTime = ['Accept', 'Maybe'].includes(type)
    if (withTime && (!!form.timeStart !== !!form.timeEnd)) {
      error.value = 'Bitte Beginn und Ende der eigenen Zeit angeben – oder beides leer lassen.'
      return
    }
    details = {
      Notes: form.notes,
      TimeStart: withTime && form.timeStart ? `${form.timeStart}:00` : '',
      TimeEnd: withTime && form.timeEnd ? `${form.timeEnd}:00` : '',
      RideType: withTime ? form.rideType : 'None',
      RideSeats: withTime && form.rideType === 'Offer' ? form.rideSeats : 0,
    }
  }
  saving.value = true
  error.value = null
  try {
    await onSubmit(type, details)
    close()
  } catch (err) {
    error.value = err.message || 'Antwort konnte nicht gespeichert werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
