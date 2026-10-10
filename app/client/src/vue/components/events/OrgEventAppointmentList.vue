<template>
  <!-- Die Termine eines Events, chronologisch, als kompakte Karten wie auf dem Dashboard — darunter
       ihre Mahlzeiten; ein Klick auf den Termin meldet "open" (die Event-Seite zeigt dann den Termin-Dialog).
       Vergangene Termine liegen eingeklappt darüber, damit die Liste kurz bleibt. -->
  <div v-if="appointments.length" class="org-event-appointment-list">
    <AppCollapse v-if="pastCards.length" :title="`Vergangene (${pastCards.length})`" class="org-event-appointment-list_past">
      <ul class="org-event-appointment-list_items">
        <li v-for="appt in pastCards" :key="appt.ID" class="org-event-appointment-list_item--past">
          <EventCard :event="appt" :date-display="formatDate(appt.DateStart)" compact hide-org-logo @click="(appt, el) => emit('open', appt, el)" />
          <EventMealsList :meals="appt.Meals ?? []" />
        </li>
      </ul>
    </AppCollapse>

    <ul v-if="upcomingCards.length" class="org-event-appointment-list_items">
      <li v-for="appt in upcomingCards" :key="appt.ID">
        <EventCard :event="appt" :date-display="formatDate(appt.DateStart)" compact hide-org-logo @click="(appt, el) => emit('open', appt, el)" />
        <EventMealsList :meals="appt.Meals ?? []" />
      </li>
    </ul>
    <p v-else class="org-event-appointment-list_empty">Keine anstehenden Termine.</p>
  </div>
  <p v-else class="org-event-appointment-list_empty">Diesem Event sind noch keine Termine zugeordnet.</p>
</template>

<script setup>
import { computed } from 'vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import EventCard from '@components/calendar/EventCard.vue'
import EventMealsList from '@components/calendar/EventMealsList.vue'

const props = defineProps({
  // aus GET /calendar/orgEvent/{id} (OrgEvent::appointmentsToApi())
  appointments: { type: Array, default: () => [] },
})

// (Termin, Karten-Element) — das Element, damit die Karte ins Modal morphen kann
const emit = defineEmits(['open'])

// EventCard erwartet die Rückmeldung als UserParticipation.Type (wie in der Kalender-API)
const cards = computed(() => props.appointments.map(appt => ({
  ...appt,
  UserParticipation: appt.UserResponse ? { Type: appt.UserResponse } : null,
})))

// Lokales Datum (toISOString wäre UTC und kippt nachts den Tag)
function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function isPast(appt) {
  return (appt.DateEnd || appt.DateStart) < today()
}

const pastCards = computed(() => cards.value.filter(isPast))
const upcomingCards = computed(() => cards.value.filter(appt => !isPast(appt)))

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

</script>
