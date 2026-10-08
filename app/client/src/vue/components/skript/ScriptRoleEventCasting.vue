<template>
  <!-- Besetzung einer Skript-Rolle: kommt ausschließlich aus der Rollenverteilung der
       Events (pro Tag), im Skript selbst wird nichts zugeordnet -->
  <div class="script-role-event-casting">
    <p v-if="!upcoming.length && !past.length" class="script-role-event-casting_empty">
      Noch nicht besetzt. Besetzt wird in der Rollenverteilung eines Events, für die Termine mit Rollenplan.
    </p>

    <div v-for="event in upcoming" :key="event.EventID" class="script-role-event-casting_event">
      <router-link :to="{ name: 'EventDetail', params: { segment: event.URLSegment } }" class="script-role-event-casting_title">
        {{ event.Title }}
      </router-link>
      <ul class="script-role-event-casting_days">
        <li v-for="day in event.Days" :key="day.ID">
          <span class="script-role-event-casting_date">{{ formatDay(day.Date) }}</span>
          <span>{{ day.Member?.Name }}</span>
          <span v-if="day.TimeStart" class="script-role-event-casting_time">{{ day.TimeStart }}<template v-if="day.TimeEnd">–{{ day.TimeEnd }}</template></span>
        </li>
      </ul>
    </div>

    <details v-if="past.length" class="script-role-event-casting_past">
      <summary>Frühere Besetzungen ({{ past.length }})</summary>
      <div v-for="event in past" :key="event.EventID" class="script-role-event-casting_event">
        <router-link :to="{ name: 'EventDetail', params: { segment: event.URLSegment } }" class="script-role-event-casting_title">
          {{ event.Title }}
        </router-link>
        <ul class="script-role-event-casting_days">
          <li v-for="day in event.Days" :key="day.ID">
            <span class="script-role-event-casting_date">{{ formatDay(day.Date) }}</span>
            <span>{{ day.Member?.Name }}</span>
            <span v-if="day.TimeStart" class="script-role-event-casting_time">{{ day.TimeStart }}<template v-if="day.TimeEnd">–{{ day.TimeEnd }}</template></span>
          </li>
        </ul>
      </div>
    </details>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // Rolle aus der Skript-API mit EventCasting: [{ EventID, Title, URLSegment, IsPast, Days: [{ Date, TimeStart, TimeEnd, Member }] }]
  role: { type: Object, required: true },
})

const upcoming = computed(() => (props.role.EventCasting || []).filter(e => !e.IsPast))
const past = computed(() => (props.role.EventCasting || []).filter(e => e.IsPast))

// "Fr., 30.10."
function formatDay(date) {
  const [y, m, d] = date.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' })
}
</script>
