<template>
  <!-- Die Termine eines Events, chronologisch; ein Klick öffnet den Termin im Kalender -->
  <ul v-if="appointments.length" class="org-event-appointment-list">
    <li v-for="appt in appointments" :key="appt.ID">
      <button
        type="button"
        class="org-event-appointment"
        :class="{ 'org-event-appointment--cancelled': appt.Status === 'Cancelled', 'org-event-appointment--past': isPast(appt) }"
        @click="openAppointment(appt)"
      >
        <span class="org-event-appointment_date">{{ formatAppointmentDate(appt) }}</span>
        <span class="org-event-appointment_title">
          {{ appt.Title }}
          <span v-if="appt.Status === 'Cancelled'" class="org-event-appointment_badge">Abgesagt</span>
          <span v-else-if="appt.Status === 'Suggested'" class="org-event-appointment_badge">Vorschlag</span>
        </span>
        <span v-if="appt.Location || appt.EventType" class="org-event-appointment_info">
          {{ [appt.EventType, appt.Location].filter(Boolean).join(' · ') }}
        </span>
        <ParticipationIcon :participationType="appt.UserResponse?.toLowerCase() ?? 'none'" />
      </button>
    </li>
  </ul>
  <p v-else class="org-event-appointment-list_empty">Diesem Event sind noch keine Termine zugeordnet.</p>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { formatDate } from '@utils/inventory'
import { formatEventRange, isMultiDay } from '@utils/eventDates'
import ParticipationIcon from '@components/calendar/ParticipationIcon.vue'

defineProps({
  // aus GET /calendar/orgEvent/{id} (OrgEvent::appointmentsToApi())
  appointments: { type: Array, default: () => [] },
})

const router = useRouter()

const today = () => new Date().toISOString().slice(0, 10)

function isPast(appt) {
  return (appt.DateEnd || appt.DateStart) < today()
}

function formatAppointmentDate(appt) {
  if (isMultiDay(appt)) return formatEventRange(appt, { weekday: true })
  const time = !appt.AllDay && appt.TimeStart ? `, ${appt.TimeStart.slice(0, 5)}` : ''
  return formatDate(appt.DateStart) + time
}

function openAppointment(appt) {
  router.push({ name: 'Calendar', query: { date: appt.DateStart, eventID: appt.ID } })
}
</script>
