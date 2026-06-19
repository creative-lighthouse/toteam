<template>
  <div v-if="event.Status !== 'Cancelled'" class="event-participation">
    <h3 class="event-participation_title">Deine Teilnahme</h3>

    <form class="event-response-actions" @submit.prevent>
      <fieldset class="fieldset-availability">
        <button
          type="button"
          class="event-response-button event-response-accept"
          :class="{
            'selected': userParticipationType === 'Accept',
            'unselected': userParticipationType && userParticipationType !== 'Accept'
          }"
          @click="changeParticipation('Accept')"
          :disabled="submitting"
        >
          Zusagen
        </button>
        <button
          type="button"
          class="event-response-button event-response-maybe"
          :class="{
            'selected': userParticipationType === 'Maybe',
            'unselected': userParticipationType && userParticipationType !== 'Maybe'
          }"
          @click="changeParticipation('Maybe')"
          :disabled="submitting"
        >
          Vielleicht
        </button>
        <button
          type="button"
          class="event-response-button event-response-decline"
          :class="{
            'selected': userParticipationType === 'Decline',
            'unselected': userParticipationType && userParticipationType !== 'Decline'
          }"
          @click="changeParticipation('Decline')"
          :disabled="submitting"
        >
          Absagen
        </button>
      </fieldset>

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
          <button
            type="button"
            class="button button--primary button--small"
            @click="saveTime"
            :disabled="submitting || !timeStart || !timeEnd"
          >
            Speichern
          </button>
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
          <button
            type="button"
            class="button button--primary button--small"
            @click="saveNote"
            :disabled="submitting"
          >
            Speichern
          </button>
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

const props = defineProps({
  event: { type: Object, required: true }
})

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
