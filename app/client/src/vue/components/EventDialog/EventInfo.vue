<template>
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
</template>

<script setup>
defineProps({
  event: { type: Object, required: true }
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
  return timeStr.substring(0, 5)
}
</script>
