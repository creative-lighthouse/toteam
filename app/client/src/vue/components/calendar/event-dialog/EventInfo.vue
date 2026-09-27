<template>
  <div class="event-info">
    <p v-if="event.EventTitle"><strong>Event:</strong> {{ event.EventTitle }}</p>
    <p v-if="event.Type"><strong>Typ:</strong> {{ event.Type }}</p>
    <template v-if="isMultiDay(event)">
      <p><strong>Zeitraum:</strong> {{ formatEventRange(event, { long: true }) }}</p>
      <p v-if="event.AllDay"><strong>Ganztägig</strong></p>
    </template>
    <template v-else>
      <p><strong>Datum:</strong> {{ formatDate(event.DateStart) }}</p>
      <p v-if="event.TimeStart && event.TimeEnd">
        <strong>Zeit:</strong> {{ formatTime(event.TimeStart) }} - {{ formatTime(event.TimeEnd) }}
      </p>
      <p v-else>
        <strong>Ganztägig</strong>
      </p>
    </template>
    <p v-if="event.Location">
      <strong>Ort: </strong>
      <a
        :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(event.Location)}`"
        target="_blank"
        rel="noopener"
        class="location-link"
      >{{ event.Location }}</a>
    </p>
    <div v-if="event.Description" class="event-description">
      <strong>Beschreibung:</strong>
      <p class="event-description_text"><AppLinkifiedText :text="event.Description" /></p>
    </div>
  </div>
</template>

<script setup>
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import { isMultiDay, formatEventRange } from '@utils/eventDates'
defineProps({
  event: { type: Object, required: true }
})

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('de-DE', {
    weekday: 'short',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  }).format(date)
}

function formatTime(timeStr) {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}
</script>
