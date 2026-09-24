<template>
  <div v-if="event.Status !== 'Cancelled'" class="event-participation">
    <h3 class="event-participation_title">Deine Teilnahme</h3>

    <div class="event-response-actions">
      <AppButtonGroup
        :options="participationOptions"
        :model-value="userParticipationType"
        :disabled="submitting"
        @select="changeParticipation"
      />

      <div v-if="userParticipationType" class="rsvp-chip-row">
        <button
          v-if="canShowTimeRide"
          type="button"
          class="rsvp-chip"
          :class="{ 'rsvp-chip--active': showTimeInput }"
          :disabled="submitting"
          @click="toggleTimeInput"
        >{{ showTimeInput ? '– Zeitraum' : (event.UserParticipation?.CustomTimeframe ? '✓ Zeitraum' : '+ Zeitraum') }}</button>

        <button
          v-if="canShowTimeRide"
          type="button"
          class="rsvp-chip"
          :class="{ 'rsvp-chip--active': showRideInput }"
          :disabled="submitting"
          @click="toggleRideInput"
        >{{ showRideInput ? '– Anfahrt' : (rideType ? '✓ Anfahrt' : '+ Anfahrt') }}</button>

        <button
          type="button"
          class="rsvp-chip"
          :class="{ 'rsvp-chip--active': showNoteInput }"
          :disabled="submitting"
          @click="toggleNoteInput"
        >{{ showNoteInput ? '– Hinweis' : (event.UserParticipation?.Notes ? '✓ Hinweis' : '+ Hinweis') }}</button>
      </div>

      <div class="rsvp-expand" :class="{ 'rsvp-expand--open': canShowTimeRide && showTimeInput }">
        <fieldset class="fieldset-update-time">
          <div class="time-input-row">
            <label for="time-start">Von</label>
            <input id="time-start" type="time" v-model="timeStart" aria-label="Startzeit" @change="saveTime">
            <label for="time-end">Bis</label>
            <input id="time-end" type="time" v-model="timeEnd" aria-label="Endzeit" @change="saveTime">
            <AppIconButton v-if="event.UserParticipation?.CustomTimeframe" variant="danger" aria-label="Zeit entfernen" :disabled="submitting" @click="clearTime">
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </div>
        </fieldset>
      </div>

      <div class="rsvp-expand" :class="{ 'rsvp-expand--open': canShowTimeRide && showRideInput }">
        <div class="rsvp-ride-options">
          <button
            type="button"
            class="rsvp-ride-option"
            :class="{ 'rsvp-ride-option--selected': rideType === 'Need' }"
            :disabled="submitting"
            @click="selectRideNeed"
          >Ich brauche eine Mitfahrgelegenheit</button>
          <button
            type="button"
            class="rsvp-ride-option"
            :class="{ 'rsvp-ride-option--selected': rideType === 'Offer' }"
            :disabled="submitting"
            @click="selectRideOffer"
          >Ich fahre selbst</button>
        </div>
        <div v-if="rideType === 'Offer'" class="rsvp-seat-stepper">
          <span class="rsvp-seat-stepper_label">Freie Plätze</span>
          <div class="rsvp-seat-stepper_controls">
            <AppIconButton variant="neutral" aria-label="Weniger Plätze" :disabled="submitting || rideSeats <= 0" @click="changeRideSeats(-1)">−</AppIconButton>
            <span class="rsvp-seat-stepper_value">{{ rideSeats }}</span>
            <AppIconButton variant="neutral" aria-label="Mehr Plätze" :disabled="submitting || rideSeats >= 8" @click="changeRideSeats(1)">+</AppIconButton>
          </div>
        </div>
      </div>

      <div class="rsvp-expand" :class="{ 'rsvp-expand--open': showNoteInput }">
        <div class="fieldset-update-note">
          <textarea
            v-model="noteText"
            placeholder="Deine Notiz..."
            maxlength="512"
            rows="3"
            class="note-textarea"
          ></textarea>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useEventParticipation } from './useEventParticipation'
import AppButtonGroup from '@components/ui/AppButtonGroup.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionTrash from '../../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  event: { type: Object, required: true }
})

const participationOptions = [
  { value: 'Decline', label: 'Absagen', tone: 'negative' },
  { value: 'Maybe', label: 'Vielleicht', tone: 'warning' },
  { value: 'Accept', label: 'Zusagen', tone: 'positive' },
]

const emit = defineEmits(['participation-changed', 'time-changed', 'notes-changed', 'ride-changed', 'show-status'])

const {
  submitting,
  timeStart,
  timeEnd,
  showTimeInput,
  showNoteInput,
  noteText,
  showRideInput,
  rideType,
  rideSeats,
  userParticipationType,
  changeParticipation,
  startAddTime,
  saveTime,
  clearTime,
  startAddNote,
  toggleRideInput,
  selectRideNeed,
  selectRideOffer,
  changeRideSeats,
} = useEventParticipation(props, emit)

const canShowTimeRide = computed(() => userParticipationType.value === 'Accept' || userParticipationType.value === 'Maybe')

function toggleTimeInput() {
  if (showTimeInput.value) {
    showTimeInput.value = false
  } else {
    startAddTime()
  }
}

function toggleNoteInput() {
  if (showNoteInput.value) {
    showNoteInput.value = false
  } else {
    startAddNote()
  }
}
</script>
