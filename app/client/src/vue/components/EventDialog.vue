<template>
  <Teleport to="body">
    <dialog
      ref="dialogEl"
      :class="[
        'event-modal',
        `event-modal--${event.Status}`]"
      @click="handleBackdropClick"
    >
      <div class="dialog-content" @click.stop>
        <!-- Header -->
        <div class="dialog-header">
            <img
                v-if="event.OrganizationLogoURL"
                :src="event.OrganizationLogoURL"
                class="event-dialog_org-logo"
                alt=""
            >
            <div class="event_title">
            <h2 class="hl2">{{ event.Title }}</h2>
                <p v-if="event.Status=='Suggested'" class="event-card_status">(Vorschlag)</p>
                <p v-else-if="event.Status=='Cancelled'" class="event-card_status">(Abgesagt)</p>
            </div>
            <button @click="$emit('close')" class="button button--close" aria-label="Schließen">
                ✕
            </button>
        </div>

        <!-- Event Info -->
        <div class="dialog-infobox">
          <div class="event-info">
            <p v-if="event.EventTitle"><strong>Event:</strong> {{ event.EventTitle }}</p>
            <p v-if="event.Type"><strong>Typ:</strong> {{ event.Type }}</p>
            <p><strong>Datum:</strong> {{ formatDate(event.DateStart) }}</p>
            <p v-if="event.TimeStart && event.TimeEnd">
              <strong>Zeit:</strong> {{ formatTime(event.TimeStart) }} - {{ formatTime(event.TimeEnd) }}
            </p>
            <p v-else>
                <strong>Ganztägig</strong>
            </p>
            <p v-if="event.Location"><strong>Ort:</strong> {{ event.Location }}</p>
            <p v-if="event.Description"><strong>Beschreibung:</strong> {{ event.Description }}</p>
          </div>

          <!-- Event Image -->
          <div v-if="event.ImageURL" class="event-image">
            <img :src="event.ImageURL" :alt="event.Title">
          </div>

          <!-- Participation Form (only for Scheduled and Suggested events) -->
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

              <!-- Zeit & Notiz hinzufügen -->
              <div
                v-if="(userParticipationType === 'Accept' || userParticipationType === 'Maybe') && (!showTimeInput || !showNoteInput)"
                class="add-actions-row"
              >
                <button
                  v-if="!showTimeInput"
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

              <!-- Zeit-Eingabe -->
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

              <!-- Notiz-Eingabe -->
              <div
                v-if="(userParticipationType === 'Accept' || userParticipationType === 'Maybe') && showNoteInput"
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

          <!-- Meals -->
          <div v-if="event.Meals && event.Meals.length > 0" class="meals-section">
            <h3 class="event-meals_title">Mahlzeiten</h3>
            <div class="meals-list">
              <div v-for="meal in event.Meals" :key="meal.ID" class="meal">
                <div class="meal-info">
                  <strong>{{ meal.Title }}</strong>
                  <span v-if="meal.RenderTime"> ({{ meal.RenderTime }})</span>
                </div>
                <form class="event-response-actions" @submit.prevent>
                  <fieldset class="fieldset-availability">
                    <button
                      type="button"
                      class="event-response-button event-response-accept"
                      :class="{
                        'selected': meal.UserResponse === 'Accept',
                        'unselected': meal.UserResponse && meal.UserResponse !== 'Accept'
                      }"
                      @click="changeFoodParticipation(meal.ID, 'Accept')"
                      :disabled="submitting"
                    >
                      Dabei
                    </button>
                    <button
                      type="button"
                      class="event-response-button event-response-decline"
                      :class="{
                        'selected': meal.UserResponse === 'Decline',
                        'unselected': meal.UserResponse && meal.UserResponse !== 'Decline'
                      }"
                      @click="changeFoodParticipation(meal.ID, 'Decline')"
                      :disabled="submitting"
                    >
                      Nicht dabei
                    </button>
                  </fieldset>
                </form>
              </div>
            </div>
          </div>

          <!-- Participants List -->
          <div v-if="groupedParticipations" class="participants-section">
            <h3 class="event-participation_title">Teilnehmer</h3>
            <div class="participants-list">
              <!-- Accepted -->
              <template v-if="groupedParticipations.Accept.length > 0">
                <h5 class="participant-group_title">Zugesagt</h5>
                <ParticipantCard
                  v-for="p in groupedParticipations.Accept"
                  :key="p.ID"
                  :participation="p"
                  :note-expanded="expandedNoteIds.has(p.ID)"
                  @toggle-note="toggleNoteExpanded(p.ID)"
                />
              </template>

              <!-- Maybe -->
              <template v-if="groupedParticipations.Maybe.length > 0">
                <h5 class="participant-group_title">Vielleicht</h5>
                <ParticipantCard
                  v-for="p in groupedParticipations.Maybe"
                  :key="p.ID"
                  :participation="p"
                  :note-expanded="expandedNoteIds.has(p.ID)"
                  @toggle-note="toggleNoteExpanded(p.ID)"
                />
              </template>

              <!-- Declined -->
              <template v-if="groupedParticipations.Decline.length > 0">
                <h5 class="participant-group_title">Abgesagt</h5>
                <ParticipantCard
                  v-for="p in groupedParticipations.Decline"
                  :key="p.ID"
                  :participation="p"
                  :note-expanded="expandedNoteIds.has(p.ID)"
                  @toggle-note="toggleNoteExpanded(p.ID)"
                />
              </template>
            </div>
          </div>

          <!-- Edit / Delete actions for mods/admins -->
          <div v-if="canManageContent" class="event-manage-actions">
            <button type="button" class="button event-manage-button" @click="$emit('edit-appointment', event)">
              <img :src="EditIcon" alt="Edit" />
            </button>
          </div>

        </div>

        <!-- Status Message -->
        <Transition name="fade">
          <div v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]">
            {{ statusMessage.text }}
          </div>
        </Transition>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import { useOrganizationsStore } from '@stores/organizations'
import actionTime from '../../../icons/actions/action_time.svg'
import actionEdit from '../../../icons/actions/action_edit.svg'
import ParticipantCard from './ParticipantCard.vue'
import EditIcon from '../../../icons/actions/action_edit.svg'

const eventsStore = useEventsStore()
const orgsStore = useOrganizationsStore()

const canManageContent = computed(() => {
  const eventOrgIds = props.event.OrganizationIDs ?? []
  return orgsStore.organizations.some(o =>
    eventOrgIds.includes(o.ID) && ['moderator', 'admin'].includes(o.MembershipStatus)
  )
})

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'participation-changed', 'time-changed', 'notes-changed', 'food-changed', 'edit-appointment'])

const dialogEl = ref(null)
const submitting = ref(false)
const statusMessage = ref(null)
const timeStart = ref('')
const timeEnd = ref('')
const showTimeInput = ref(false)
const showNoteInput = ref(false)
const noteText = ref('')
const expandedNoteIds = ref(new Set())

const userParticipationType = computed(() => {
  return props.event.UserParticipation?.Type || null
})

const groupedParticipations = computed(() => {
  if (!props.event.Participations) return null

  return {
    Accept: props.event.Participations.filter(p => p.Type === 'Accept'),
    Maybe: props.event.Participations.filter(p => p.Type === 'Maybe'),
    Decline: props.event.Participations.filter(p => p.Type === 'Decline')
  }
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('de-DE', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  }).format(date)
}

function formatTime(timeStr) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5) // HH:mm
}

function getStatusLabel(status) {
  const labels = {
    'Scheduled': 'Geplant',
    'Suggested': 'Vorgeschlagen',
    'Cancelled': 'Abgesagt'
  }
  return labels[status] || status
}

async function changeParticipation(type) {
  if (submitting.value) return

  submitting.value = true

  try {
    const response = await eventsStore.changeParticipation(props.event.ID, type)

    // Update time inputs
    if (response.TimeStart && response.TimeEnd) {
      timeStart.value = formatTime(response.TimeStart)
      timeEnd.value = formatTime(response.TimeEnd)
    }

    // Emit event to update parent
    emit('participation-changed', props.event.ID, response)

    showStatusMessage('Gespeichert', 'success')
  } catch (err) {
    console.error('Error changing participation:', err)
    showStatusMessage('Fehler beim Speichern', 'error')
  } finally {
    submitting.value = false
  }
}

function startAddTime() {
  const up = props.event.UserParticipation
  if (up?.CustomTimeframe) {
    timeStart.value = up.TimeStart ? formatTime(up.TimeStart) : ''
    timeEnd.value = up.TimeEnd ? formatTime(up.TimeEnd) : ''
  } else {
    timeStart.value = props.event.TimeStart ? formatTime(props.event.TimeStart) : ''
    timeEnd.value = props.event.TimeEnd ? formatTime(props.event.TimeEnd) : ''
  }
  showTimeInput.value = true
}

async function saveTime() {
  if (!timeStart.value || !timeEnd.value || submitting.value) return

  submitting.value = true
  try {
    const response = await eventsStore.changeParticipationTime(
      props.event.ID,
      timeStart.value + ':00',
      timeEnd.value + ':00'
    )
    showTimeInput.value = false
    emit('time-changed', props.event.ID, response)
    showStatusMessage('Zeiten gespeichert', 'success')
  } catch (err) {
    console.error('Error saving time:', err)
    showStatusMessage('Fehler beim Speichern', 'error')
  } finally {
    submitting.value = false
  }
}

async function clearTime() {
  if (submitting.value) return

  submitting.value = true
  try {
    const response = await eventsStore.changeParticipationTime(props.event.ID, null, null)
    showTimeInput.value = false
    timeStart.value = ''
    timeEnd.value = ''
    emit('time-changed', props.event.ID, response)
    showStatusMessage('Zeit entfernt', 'success')
  } catch (err) {
    console.error('Error clearing time:', err)
    showStatusMessage('Fehler beim Entfernen', 'error')
  } finally {
    submitting.value = false
  }
}

function startAddNote() {
  noteText.value = props.event.UserParticipation?.Notes || ''
  showNoteInput.value = true
}

async function saveNote() {
  if (submitting.value) return

  submitting.value = true
  try {
    const response = await eventsStore.changeParticipationNotes(props.event.ID, noteText.value || null)
    showNoteInput.value = false
    emit('notes-changed', props.event.ID, response)
    showStatusMessage('Notiz gespeichert', 'success')
  } catch (err) {
    console.error('Error saving note:', err)
    showStatusMessage('Fehler beim Speichern', 'error')
  } finally {
    submitting.value = false
  }
}

async function clearNote() {
  if (submitting.value) return

  submitting.value = true
  try {
    const response = await eventsStore.changeParticipationNotes(props.event.ID, null)
    showNoteInput.value = false
    noteText.value = ''
    emit('notes-changed', props.event.ID, response)
    showStatusMessage('Notiz entfernt', 'success')
  } catch (err) {
    console.error('Error clearing note:', err)
    showStatusMessage('Fehler beim Entfernen', 'error')
  } finally {
    submitting.value = false
  }
}

function toggleNoteExpanded(participationId) {
  const next = new Set(expandedNoteIds.value)
  if (next.has(participationId)) {
    next.delete(participationId)
  } else {
    next.add(participationId)
  }
  expandedNoteIds.value = next
}

async function changeFoodParticipation(mealId, type) {
  if (submitting.value) return

  submitting.value = true

  try {
    await eventsStore.changeFoodParticipation(mealId, type)

    emit('food-changed', mealId, type)

    showStatusMessage('Essensauswahl gespeichert', 'success')
  } catch (err) {
    console.error('Error changing food participation:', err)
    showStatusMessage('Fehler beim Speichern', 'error')
  } finally {
    submitting.value = false
  }
}

function showStatusMessage(text, type = 'success') {
  statusMessage.value = { text, type }

  setTimeout(() => {
    if (statusMessage.value) {
      statusMessage.value = null
    }
  }, 3000)
}

function handleBackdropClick(e) {
  if (e.target === dialogEl.value) {
    emit('close')
  }
}

function handleEscape(e) {
  if (e.key === 'Escape') {
    emit('close')
  }
}

// Initialize time/note input from user participation
watch(() => props.event.UserParticipation, (newVal) => {
  if (newVal?.CustomTimeframe) {
    timeStart.value = newVal.TimeStart ? formatTime(newVal.TimeStart) : ''
    timeEnd.value = newVal.TimeEnd ? formatTime(newVal.TimeEnd) : ''
  } else {
    timeStart.value = ''
    timeEnd.value = ''
  }
  showTimeInput.value = false

  noteText.value = newVal?.Notes || ''
  showNoteInput.value = false
}, { immediate: true })

onMounted(() => {
  dialogEl.value?.showModal()
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape)
  dialogEl.value?.close()
})
</script>
