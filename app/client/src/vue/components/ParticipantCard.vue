<template>
  <div :class="['participant', `participant--status-${participation.Type}`]">
    <div class="participant-main">
      <div class="participant-avatar">
        <img
          v-if="participation.ProfileImageURL"
          :src="participation.ProfileImageURL"
          :alt="participation.MemberName"
          class="participant-avatar_img"
        >
        <span v-else class="participant-avatar_initials">{{ initials }}</span>
      </div>
      <div class="participant-info">
        <span class="participant-name" :data-me="participation.IsCurrentUser ? 'true' : null">
          {{ participation.MemberName }}
        </span>
        <span class="participant-status" v-if="participation.CustomTimeframe && participation.TimeStart && participation.TimeEnd">
          {{ formatTime(participation.TimeStart) }} – {{ formatTime(participation.TimeEnd) }}
        </span>
      </div>
      <span v-if="rideIcon" class="participant-ride" :title="rideTitle">
        <span class="participant-ride_icon" :style="rideIconStyle" aria-hidden="true"></span>
        <span v-if="rideSeats !== null" class="participant-ride_seats">{{ rideSeats }}</span>
      </span>
    </div>
    <p
      v-if="participation.Notes"
      class="participant-note"
      :class="{ 'participant-note--expanded': noteExpanded }"
      @click="$emit('toggle-note')"
    >{{ participation.Notes }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import HasTransportIcon from '../../../icons/actions/action_hastransport.svg'
import NeedsTransportIcon from '../../../icons/actions/action_needstransport.svg'

const props = defineProps({
  participation: {
    type: Object,
    required: true
  },
  noteExpanded: {
    type: Boolean,
    default: false
  }
})

defineEmits(['toggle-note'])

const initials = computed(() => {
  const name = props.participation.MemberName || ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
})

function formatTime(timeStr) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

const rideIcon = computed(() => {
  if (props.participation.RideType === 'Offer') return HasTransportIcon
  if (props.participation.RideType === 'Need') return NeedsTransportIcon
  return null
})

// Icon wird per CSS-Maske statt <img> gerendert, damit es die Schriftfarbe
// annimmt (die SVGs haben eine fest codierte Füllfarbe, die per <img> nicht
// überschreibbar wäre).
const rideIconStyle = computed(() => ({
  maskImage: `url(${rideIcon.value})`,
  WebkitMaskImage: `url(${rideIcon.value})`,
}))

const rideSeats = computed(() => {
  return props.participation.RideType === 'Offer' ? (props.participation.RideSeats ?? 0) : null
})

const rideTitle = computed(() => {
  if (props.participation.RideType === 'Offer') return 'Fährt selbst'
  if (props.participation.RideType === 'Need') return 'Braucht eine Mitfahrgelegenheit'
  return ''
})
</script>
