<template>
  <Teleport to="body">
    <dialog
      ref="dialogEl"
      :class="['event-modal', `event-modal--${event.Status}`]"
      @click="handleBackdropClick"
    >
      <div class="dialog-content" @click.stop>
        <!-- Header -->
        <div class="dialog-header">
          <h2 class="hl2">{{ event.Title }}</h2>
          <button @click="$emit('close')" class="button button--close" aria-label="Schließen">
            ✕
          </button>
        </div>

        <!-- Event Info -->
        <div class="dialog-infobox">
          <div class="event-info">
            <p v-if="event.EventTitle"><strong>Event:</strong> {{ event.EventTitle }}</p>
            <p v-if="event.Type"><strong>Typ:</strong> {{ event.Type }}</p>
            <p><strong>Datum:</strong> {{ formatDate(event.Date) }}</p>
            <p v-if="event.TimeStart && event.TimeEnd">
              <strong>Zeit:</strong> {{ formatTime(event.TimeStart) }} - {{ formatTime(event.TimeEnd) }}
            </p>
            <p v-if="event.Location"><strong>Ort:</strong> {{ event.Location }}</p>
            <p v-if="event.Description"><strong>Beschreibung:</strong> {{ event.Description }}</p>
            <p><strong>Status:</strong> <span :class="`status--${event.Status}`">{{ getStatusLabel(event.Status) }}</span></p>
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

              <!-- Time Input (only if Accept or Maybe) -->
              <fieldset
                v-if="userParticipationType === 'Accept' || userParticipationType === 'Maybe'"
                class="fieldset-update-time"
              >
                <p>Von:</p>
                <input
                  type="time"
                  v-model="timeStart"
                  @blur="updateTime"
                  :disabled="submitting"
                >
                <p>Bis:</p>
                <input
                  type="time"
                  v-model="timeEnd"
                  @blur="updateTime"
                  :disabled="submitting"
                >
              </fieldset>
            </form>
          </div>

          <!-- Meals -->
          <div v-if="event.Meals && event.Meals.length > 0" class="meals-section">
            <h3 class="event-participation_title">Mahlzeiten</h3>
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
                <div
                  v-for="p in groupedParticipations.Accept"
                  :key="p.ID"
                  class="participant participant--status-Accept"
                >
                  <span class="participant-name" :data-me="p.IsCurrentUser ? 'true' : null">
                    {{ p.MemberName }}
                  </span>
                  <span class="participant-status" v-if="p.TimeStart && p.TimeEnd">
                    ({{ formatTime(p.TimeStart) }} - {{ formatTime(p.TimeEnd) }})
                  </span>
                </div>
              </template>

              <!-- Maybe -->
              <template v-if="groupedParticipations.Maybe.length > 0">
                <h5 class="participant-group_title">Vielleicht</h5>
                <div
                  v-for="p in groupedParticipations.Maybe"
                  :key="p.ID"
                  class="participant participant--status-Maybe"
                >
                  <span class="participant-name" :data-me="p.IsCurrentUser ? 'true' : null">
                    {{ p.MemberName }}
                  </span>
                  <span class="participant-status" v-if="p.TimeStart && p.TimeEnd">
                    ({{ formatTime(p.TimeStart) }} - {{ formatTime(p.TimeEnd) }})
                  </span>
                </div>
              </template>

              <!-- Declined -->
              <template v-if="groupedParticipations.Decline.length > 0">
                <h5 class="participant-group_title">Abgesagt</h5>
                <div
                  v-for="p in groupedParticipations.Decline"
                  :key="p.ID"
                  class="participant participant--status-Decline"
                >
                  <span class="participant-name" :data-me="p.IsCurrentUser ? 'true' : null">
                    {{ p.MemberName }}
                  </span>
                </div>
              </template>
            </div>
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

const eventsStore = useEventsStore()

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'participation-changed', 'time-changed', 'food-changed'])

const dialogEl = ref(null)
const submitting = ref(false)
const statusMessage = ref(null)
const timeStart = ref('')
const timeEnd = ref('')

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

async function updateTime() {
  if (!timeStart.value || !timeEnd.value || submitting.value) return

  submitting.value = true

  try {
    const response = await eventsStore.changeParticipationTime(
      props.event.ID,
      timeStart.value + ':00',
      timeEnd.value + ':00'
    )

    emit('time-changed', props.event.ID, response)

    showStatusMessage('Zeiten gespeichert', 'success')
  } catch (err) {
    console.error('Error updating time:', err)
    showStatusMessage('Fehler beim Speichern', 'error')
  } finally {
    submitting.value = false
  }
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

// Initialize time inputs from user participation
watch(() => props.event.UserParticipation, (newVal) => {
  if (newVal) {
    timeStart.value = newVal.TimeStart ? formatTime(newVal.TimeStart) : ''
    timeEnd.value = newVal.TimeEnd ? formatTime(newVal.TimeEnd) : ''
  } else {
    timeStart.value = props.event.TimeStart ? formatTime(props.event.TimeStart) : ''
    timeEnd.value = props.event.TimeEnd ? formatTime(props.event.TimeEnd) : ''
  }
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

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

.status-message {
  position: fixed;
  top: auto;
  bottom: 20px;
  right: 20px;
  padding: 15px 25px;
  border-radius: var(--BorderRadiusMedium);
  background-color: var(--ColorStatusGood);
  color: white;
  font-weight: bold;
  box-shadow: var(--BoxShadowMedium);
  z-index: 10000;
}

.status-message--error {
  background-color: var(--ColorStatusBad);
}

.dialog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.button--close {
  background: transparent;
  border: none;
  font-size: 24px;
  cursor: pointer;
  padding: 5px 10px;
  color: var(--ColorGray);
}

.button--close:hover {
  color: var(--ColorPrimary);
}

.event-info {
  margin-bottom: 20px;
}

.event-info p {
  margin: 8px 0;
}

.event-image {
  margin: 20px 0;
}

.event-image img {
  max-width: 100%;
  border-radius: var(--BorderRadiusMedium);
}

.status--Scheduled {
  color: var(--ColorStatusGood);
  font-weight: bold;
}

.status--Suggested {
  color: var(--ColorStatusWarning);
  font-weight: bold;
}

.status--Cancelled {
  color: var(--ColorStatusBad);
  font-weight: bold;
}
</style>
