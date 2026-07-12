<template>
  <div v-if="event.Status !== 'Cancelled'" class="event-participation">
    <h3 class="event-participation_title">Deine Teilnahme</h3>

    <form class="event-response-actions" @submit.prevent>
      <AppButtonGroup
        :options="participationOptions"
        :model-value="userParticipationType"
        :disabled="submitting"
        @select="changeParticipation"
      />

      <div
        v-if="!showTimeInput || !showNoteInput"
        class="add-actions-row"
      >
        <AppButton
          v-if="!showTimeInput && (userParticipationType === 'Accept' || userParticipationType === 'Maybe')"
          size="small"
          variant="secondary"
          @click="startAddTime"
          :disabled="submitting"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          {{ event.UserParticipation?.CustomTimeframe ? 'Zeit bearbeiten' : 'Zeit hinzufügen' }}
        </AppButton>
        <AppButton
          v-if="!showNoteInput"
          size="small"
          variant="secondary"
          @click="startAddNote"
          :disabled="submitting"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          {{ event.UserParticipation?.Notes ? 'Notiz bearbeiten' : 'Notiz hinzufügen' }}
        </AppButton>
      </div>

      <fieldset
        v-if="(userParticipationType === 'Accept' || userParticipationType === 'Maybe') && showTimeInput"
        class="fieldset-update-time"
      >
        <div class="time-input-row">
          <label for="time-start">Von</label>
          <input id="time-start" type="time" v-model="timeStart" :disabled="submitting" aria-label="Startzeit">
          <label for="time-end">Bis</label>
          <input id="time-end" type="time" v-model="timeEnd" :disabled="submitting" aria-label="Endzeit">
        </div>
        <div class="time-button-row">
          <AppButton
            size="small"
            variant="primary"
            :disabled="submitting || !timeStart || !timeEnd"
            @click="saveTime"
          >
            Speichern
          </AppButton>
          <button
            type="button"
            class="btn-remove-time"
            @click="clearTime"
            :disabled="submitting"
          >
            Entfernen
          </button>
        </div>
      </fieldset>

      <div
        v-if="showNoteInput"
        class="fieldset-update-note"
      >
        <textarea
          v-model="noteText"
          :disabled="submitting"
          placeholder="Deine Notiz..."
          maxlength="512"
          rows="3"
          class="note-textarea"
        ></textarea>
        <div class="note-button-row">
          <AppButton
            size="small"
            variant="primary"
            :disabled="submitting"
            @click="saveNote"
          >
            Speichern
          </AppButton>
          <button
            type="button"
            class="btn-remove-note"
            @click="clearNote"
            :disabled="submitting"
          >
            Entfernen
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useEventParticipation } from './useEventParticipation'
import AppButton from '@components/AppButton.vue'
import AppButtonGroup from '@components/AppButtonGroup.vue'

const props = defineProps({
  event: { type: Object, required: true }
})

const participationOptions = [
  { value: 'Accept', label: 'Zusagen', tone: 'positive' },
  { value: 'Maybe', label: 'Vielleicht', tone: 'warning' },
  { value: 'Decline', label: 'Absagen', tone: 'negative' },
]

const emit = defineEmits(['participation-changed', 'time-changed', 'notes-changed', 'show-status'])

const {
  submitting,
  timeStart,
  timeEnd,
  showTimeInput,
  showNoteInput,
  noteText,
  userParticipationType,
  changeParticipation,
  startAddTime,
  saveTime,
  clearTime,
  startAddNote,
  saveNote,
  clearNote,
} = useEventParticipation(props, emit)
</script>
