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
        <button
          v-if="!showTimeInput && (userParticipationType === 'Accept' || userParticipationType === 'Maybe')"
          type="button"
          class="btn-add-time"
          @click="startAddTime"
          :disabled="submitting"
        >
          <img :src="actionTime" alt="Zeit Icon" class="action-icon">
          <p>{{ event.UserParticipation?.CustomTimeframe ? 'Zeit bearbeiten' : 'Zeit hinzufügen' }}</p>
        </button>
        <button
          v-if="!showNoteInput"
          type="button"
          class="btn-add-note"
          @click="startAddNote"
          :disabled="submitting"
        >
          <img :src="actionEdit" alt="Notiz Icon" class="action-icon">
          <p>{{ event.UserParticipation?.Notes ? 'Notiz bearbeiten' : 'Notiz hinzufügen' }}</p>
        </button>
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
import actionTime from '../../../../icons/actions/action_time.svg'
import actionEdit from '../../../../icons/actions/action_edit.svg'
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
