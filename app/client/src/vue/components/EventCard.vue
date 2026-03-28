<template>
  <div class="event-card" :class="`event-card--${event.EventType || 'default'}`" @click="openEventDetails">
    <div class="event-card_header">
      <h4 class="event-card_title">{{ event.Title }}</h4>
      <span v-if="event.TimeStart" class="event-card_time">
        {{ formatTime(event.TimeStart) }}
        <template v-if="event.TimeEnd">
          - {{ formatTime(event.TimeEnd) }}
        </template>
      </span>
    </div>

    <div v-if="event.Description" class="event-card_description">
      {{ event.Description }}
    </div>

    <div class="event-card_footer">
        <div class="event-card_location">
            <span v-if="event.Location" class="event-card_location">
                📍 {{ event.Location }}
            </span>
        </div>
        <span v-if="event.UserParticipation" class="event-card_participation" :class="`participation--${event.UserParticipation.Type.toLowerCase()}`">
            {{ getParticipationLabel(event.UserParticipation.Type) }}
        </span>
        <span v-else class="event-card_participation participation--none">
            Noch keine Antwort
        </span>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['click'])

function formatTime(time) {
  if (!time) return ''
  // Time format: HH:mm:ss -> HH:mm
  return time.substring(0, 5)
}

function getParticipationLabel(type) {
  const labels = {
    'Accept': '✓ Zugesagt',
    'Maybe': '? Vielleicht',
    'Decline': '✗ Abgesagt'
  }
  return labels[type] || type
}

function openEventDetails() {
  emit('click', props.event)
}
</script>
