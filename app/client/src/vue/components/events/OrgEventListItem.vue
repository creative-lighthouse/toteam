<template>
  <!-- Kompakte Zeile eines Events (Dashboard, Profil): Vorschaubild, Titel, Zeitraum und
       Ort, rechts die eigene Markierung — führt zur Event-Seite -->
  <router-link
    :to="{ name: 'EventDetail', params: { segment: event.URLSegment } }"
    class="org-event-list-item"
    :class="{ 'org-event-list-item--past': isPast }"
  >
    <span class="org-event-list-item_media">
      <img v-if="event.ImageURL" :src="event.ImageURL" alt="" loading="lazy">
      <AppPatternImage v-else :seed="event.Title" />
    </span>
    <span class="org-event-list-item_text">
      <span class="org-event-list-item_title">{{ event.Title }}</span>
      <span class="org-event-list-item_meta">{{ meta }}</span>
    </span>
    <span
      v-if="event.UserInterest"
      class="org-event-list-item_badge"
      :class="`org-event-list-item_badge--${event.UserInterest.toLowerCase()}`"
    >{{ event.UserInterest === 'Going' ? '✓ Dabei' : '★ Interessiert' }}</span>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { formatOrgEventRange, formatPlace } from '@utils/orgEvents'
import AppPatternImage from '@components/ui/AppPatternImage.vue'

const props = defineProps({
  // Summary bzw. öffentliche Daten aus GET /calendar/myOrgEvents
  event: { type: Object, required: true },
})

const meta = computed(() =>
  [props.event.RangeStart ? formatOrgEventRange(props.event) : 'Datum folgt', formatPlace(props.event)]
    .filter(Boolean)
    .join(' · ')
)

const isPast = computed(() => {
  const end = props.event.RangeEnd ?? props.event.RangeStart
  return !!end && end < new Date().toISOString().slice(0, 10)
})
</script>
