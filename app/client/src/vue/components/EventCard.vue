<template>
  <div
    class="event-card"
    :class="[
      `event-card--${event.EventType || 'default'}`,
      event.AllDay ? 'event-card--allday' : 'event-card--timed'
    ]"
    @click="openEventDetails"
  >
    <!-- Header: Org logo + Title + Time -->
    <div class="event-card_header">
      <div class="event-card_header-left">
        <img
          v-if="event.OrganizationLogoURL"
          :src="event.OrganizationLogoURL"
          class="event-card_org-logo"
          alt=""
        >
        <h4 class="event-card_title">{{ event.Title }}</h4>
      </div>
      <span v-if="dateDisplay" class="event-card_time">{{ dateDisplay }}</span>
      <span v-else-if="event.AllDay" class="event-card_time event-card_time--allday">Ganztägig</span>
      <span v-else-if="event.TimeStart" class="event-card_time">
        {{ formatTime(event.TimeStart) }}<template v-if="event.TimeEnd"> – {{ formatTime(event.TimeEnd) }}</template>
      </span>
    </div>

    <!-- Footer: Avatars + Info + RSVP -->
    <div class="event-card_footer">
      <div class="event-card_footer-left">
        <div v-if="visibleAvatars.length" class="event-card_avatars">
          <img
            v-for="p in visibleAvatars"
            :key="p.ID"
            :src="p.ProfileImageURL"
            :alt="p.MemberName"
            class="event-card_avatar"
          >
        </div>
        <div class="event-card_footer-info">
          <span v-if="event.Location" class="event-card_location">{{ event.Location }}</span>
          <span v-if="participationCount" class="event-card_count">{{ participationCount }}</span>
        </div>
      </div>
      <span
        class="event-card_participation"
        :class="`participation--${participationType}`"
      >{{ participationLabel }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true
  },
  dateDisplay: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['click'])

const visibleAvatars = computed(() =>
  (props.event.Participations || [])
    .filter(p => (p.Type === 'Accept' || p.Type === 'Maybe') && p.ProfileImageURL)
    .slice(0, 5)
)

const participationCount = computed(() => {
  const parts = props.event.Participations || []
  const accept = parts.filter(p => p.Type === 'Accept').length
  const maybe = parts.filter(p => p.Type === 'Maybe').length
  const out = []
  if (accept) out.push(`${accept} Zusagen`)
  if (maybe) out.push(`${maybe} Vielleicht`)
  return out.join(' | ')
})

const participationType = computed(() =>
  props.event.UserParticipation?.Type?.toLowerCase() || 'none'
)

const participationLabel = computed(() => ({
  accept: 'Zugesagt',
  maybe: 'Vielleicht',
  decline: 'Abgesagt',
  none: 'Noch keine Antwort',
}[participationType.value] ?? 'Noch keine Antwort'))

function formatTime(time) {
  if (!time) return ''
  return time.substring(0, 5)
}

function openEventDetails() {
  emit('click', props.event)
}
</script>
