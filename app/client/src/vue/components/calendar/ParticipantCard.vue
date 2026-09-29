<template>
  <div :class="['participant', `participant--status-${participation.Type}`, { 'participant--has-note': !!participation.Notes }]">
    <div class="participant-avatar">
        <AppAvatar
            :src="participation.ProfileImageURL"
            :alt="participation.MemberName"
            img-class="participant-avatar_img"
            placeholder-class="participant-avatar_initials"
        />
    </div>
    <span class="participant-name" :data-me="participation.IsCurrentUser ? 'true' : null">
        {{ participation.MemberName }}
    </span>
    <div class="participant-timeandride">
        <!-- Icon + Zahl allein wären für Screenreader nur "2" — daher als Text beschrieben -->
        <span
            v-if="rideIcon"
            class="participant-ride"
            :class="{ 'participant-ride--needed': participation.RideType === 'Need' }"
            :title="rideTitle"
            role="img"
            :aria-label="rideSeats !== null ? `${rideTitle}, ${rideSeats} freie Plätze` : rideTitle"
        >
            <span class="participant-ride_icon" :style="rideIconStyle" aria-hidden="true"></span>
            <span v-if="rideSeats !== null" class="participant-ride_seats" aria-hidden="true">{{ rideSeats }}</span>
        </span>
        <span class="participant-status" v-if="participation.Type !== 'Decline' && participation.CustomTimeframe && participation.TimeStart && participation.TimeEnd">
            {{ formatTime(participation.TimeStart) }} – {{ formatTime(participation.TimeEnd) }}
        </span>
        <img
          v-if="foodPreferenceIcon"
          :src="foodPreferenceIcon.src"
          :alt="foodPreferenceIcon.label"
          :title="foodPreferenceIcon.label"
          class="participant-food-preference"
        >
        <span
          v-for="allergy in participation.Allergies || []"
          :key="allergy"
          class="allergy-pill"
        >{{ allergy }}</span>
        <img
          v-if="statusIcon"
          :src="statusIcon.src"
          :alt="statusIcon.label"
          :title="statusIcon.label"
          class="participant-status-icon"
        >
    </div>
    <p
      v-if="participation.Notes"
      class="participant-note"
      :class="{ 'participant-note--expanded': noteExpanded }"
      @click="$emit('toggle-note')"
    ><AppLinkifiedText :text="participation.Notes" /></p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import HasTransportIcon from '../../../../icons/actions/action_hastransport.svg'
import NeedsTransportIcon from '../../../../icons/actions/action_needstransport.svg'
import AcceptIcon from '../../../../icons/states/participation_accept.svg'
import MaybeIcon from '../../../../icons/states/participation_maybe.svg'
import DeclineIcon from '../../../../icons/states/participation_decline.svg'
import VeganIcon from '../../../../icons/states/food_vegan.svg'
import VegetarianIcon from '../../../../icons/states/food_vegetarian.svg'

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

// Status zusätzlich als Icon — nicht nur über die Randfarbe (Farbenblindheit)
const STATUS_ICONS = {
  Accept: { src: AcceptIcon, label: 'Zugesagt' },
  Maybe: { src: MaybeIcon, label: 'Vielleicht' },
  Decline: { src: DeclineIcon, label: 'Abgesagt' },
}
const statusIcon = computed(() => STATUS_ICONS[props.participation.Type] ?? null)

function formatTime(timeStr) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

const FOOD_PREFERENCE_ICONS = {
  Vegetarian: { src: VegetarianIcon, label: 'Vegetarisch' },
  Vegan: { src: VeganIcon, label: 'Vegan' },
}
const foodPreferenceIcon = computed(() => FOOD_PREFERENCE_ICONS[props.participation.FoodPreference] ?? null)

const rideIcon = computed(() => {
  if (props.participation.Type === 'Decline') return null
  if (props.participation.RideType === 'Offer') return HasTransportIcon
  if (props.participation.RideType === 'Need') return NeedsTransportIcon
  return null
})

// Icon wird per CSS-Maske statt <img> gerendert, damit es die Schriftfarbe
// annimmt (die SVGs haben eine fest codierte Füllfarbe, die per <img> nicht
// überschreibbar wäre).
const rideIconStyle = computed(() => ({
  maskImage: `url("${rideIcon.value}")`,
  WebkitMaskImage: `url("${rideIcon.value}")`,
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
