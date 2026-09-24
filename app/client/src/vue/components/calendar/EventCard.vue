<template>
  <div
    class="event-card"
    :class="[
      `event-card--${event.EventType || 'default'}`,
      event.AllDay ? 'event-card--allday' : 'event-card--timed',
      event.Status ? `event-card--${event.Status.toLowerCase()}` : '',
      event.IsPoll ? 'event-card--poll' : '',
      compact ? 'event-card--compact' : '',
    ]"
    @click="openEventDetails"
  >
    <!-- Compact: logo + title/meta in two lines + RSVP icon, e.g. on the dashboard -->
    <template v-if="compact">
      <div class="event-card_compact-info">
        <span class="event-card_title-row">
          <span class="event-card_title">{{ event.Title }}</span>
          <span v-if="event.IsPoll" class="event-card_status event-card_status--poll" title="Terminfindung">
            <span class="event-card_status-icon" :style="scheduleIconStyle" aria-hidden="true"></span>
          </span>
          <span v-else-if="event.Status=='Suggested'" class="event-card_status">(Vorschlag)</span>
          <span v-else-if="event.Status=='Cancelled'" class="event-card_status">(Abgesagt)</span>
        </span>
        <span v-if="compactMeta" class="event-card_compact-meta">{{ compactMeta }}</span>
      </div>
      <ParticipationIcon :participationType="participationType" :title="participationLabel" />
      <AppOrgLogo
        v-if="primaryOrg && !hideOrgLogo"
        :src="primaryOrg.LogoURL"
        :alt="primaryOrg.Title"
        :title="primaryOrg.Title"
        :size="22"
        class="event-card_org-logo"
      />
    </template>

    <template v-else>
      <!-- Header: Org logo + Title + Time -->
      <div class="event-card_header">
        <div class="event-card_header-left">
          <template v-if="hideOrgLogo" />
          <template v-else-if="event.OrganizationLogos?.length">
            <AppOrgLogo
              v-for="logo in event.OrganizationLogos"
              :key="logo.ID"
              :src="logo.LogoURL"
              :alt="logo.Title"
              :title="logo.Title"
              :size="25"
              class="event-card_org-logo"
            />
          </template>
          <AppOrgLogo
            v-else-if="event.OrganizationLogoURL"
            :src="event.OrganizationLogoURL"
            alt=""
            :size="25"
            class="event-card_org-logo"
          />
          <h4 class="event-card_title">{{ event.Title }}</h4>
          <p v-if="event.IsPoll" class="event-card_status event-card_status--poll" title="Terminfindung">
            <span class="event-card_status-icon" :style="scheduleIconStyle" aria-hidden="true"></span>
          </p>
          <p v-else-if="event.Status=='Suggested'" class="event-card_status">(Vorschlag)</p>
          <p v-else-if="event.Status=='Cancelled'" class="event-card_status">(Abgesagt)</p>
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
            <AppAvatar
              v-for="p in visibleAvatars"
              :key="p.ID"
              :src="p.ProfileImageURL"
              :alt="p.MemberName"
              img-class="event-card_avatar"
            />
          </div>
          <div class="event-card_footer-info">
            <span v-if="event.Location" class="event-card_location">{{ event.Location }}</span>
            <span v-if="participationCount" class="event-card_count">{{ participationCount }}</span>
          </div>
        </div>
        <ParticipationIcon :participationType="participationType" :title="participationLabel" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ParticipationIcon from './ParticipationIcon.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ScheduleIcon from '../../../../icons/actions/action_schedule.svg'

const scheduleIconStyle = {
  maskImage: `url("${ScheduleIcon}")`,
  WebkitMaskImage: `url("${ScheduleIcon}")`,
}

const props = defineProps({
  event: {
    type: Object,
    required: true
  },
  dateDisplay: {
    type: String,
    default: null
  },
  compact: {
    type: Boolean,
    default: false
  },
  // Hide the organization logo(s), e.g. when the user only belongs to one org
  hideOrgLogo: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

// First organization of the event (logo may be null → AppOrgLogo shows the initial)
const primaryOrg = computed(() =>
  props.event.OrganizationLogos?.[0]
    || (props.event.OrganizationLogoURL ? { LogoURL: props.event.OrganizationLogoURL, Title: '' } : null)
)

// Compact meta line: "Do., 24.09.26 · 18:00 – 20:00 · Vereinsheim"
const compactMeta = computed(() => {
  const parts = []
  if (props.dateDisplay) parts.push(props.dateDisplay)
  if (props.event.AllDay) {
    parts.push('Ganztägig')
  } else if (props.event.TimeStart) {
    parts.push(formatTime(props.event.TimeStart) + (props.event.TimeEnd ? ` – ${formatTime(props.event.TimeEnd)}` : ''))
  }
  if (props.event.Location) parts.push(props.event.Location)
  return parts.join(' · ')
})

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
