<template>
  <!-- Die Termine eines Events, chronologisch, als kompakte Karten wie auf dem Dashboard; ein Klick öffnet den Termin im Kalender -->
  <ul v-if="appointments.length" class="org-event-appointment-list">
    <li
      v-for="appt in cards"
      :key="appt.ID"
      :class="{ 'org-event-appointment-list_item--past': isPast(appt) }"
    >
      <EventCard :event="appt" :date-display="formatDate(appt.DateStart)" compact hide-org-logo @click="openAppointment" />
    </li>
  </ul>
  <p v-else class="org-event-appointment-list_empty">Diesem Event sind noch keine Termine zugeordnet.</p>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import EventCard from '@components/calendar/EventCard.vue'

const props = defineProps({
  // aus GET /calendar/orgEvent/{id} (OrgEvent::appointmentsToApi())
  appointments: { type: Array, default: () => [] },
})

const router = useRouter()

// EventCard erwartet die Rückmeldung als UserParticipation.Type (wie in der Kalender-API)
const cards = computed(() => props.appointments.map(appt => ({
  ...appt,
  UserParticipation: appt.UserResponse ? { Type: appt.UserResponse } : null,
})))

const today = () => new Date().toISOString().slice(0, 10)

function isPast(appt) {
  return (appt.DateEnd || appt.DateStart) < today()
}

// Wie auf dem Dashboard: "Do., 24.09.26"
function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString
  return new Intl.DateTimeFormat('de-DE', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(date)
}

function openAppointment(appt) {
  router.push({ name: 'Calendar', query: { date: appt.DateStart, eventID: appt.ID } })
}
</script>
