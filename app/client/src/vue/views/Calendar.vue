<template>
  <div class="section section--CalendarPage">
    <IntroBar title="Kalender" description="Verwalte deine Termine und Events." />

    <div class="section_content section_content--calendar">
      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <p>Lade Termine...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <p>Fehler beim Laden der Termine: {{ error }}</p>
      </div>

      <!-- Calendar View -->
      <div v-else class="events-calendar">
        <!-- Month Navigation -->
        <div class="calendar-header">
          <button @click="previousMonth" class="btn-nav">&lt;</button>
          <h2>{{ monthYearDisplay }}</h2>
          <button @click="nextMonth" class="btn-nav">&gt;</button>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
          <!-- Weekday Headers -->
          <div v-for="day in weekDays" :key="day" class="calendar-weekday">
            {{ day }}
          </div>

          <!-- Empty cells for days before month starts -->
          <div
            v-for="n in firstDayOfMonth"
            :key="`empty-${n}`"
            class="calendar-day calendar-day--empty"
          ></div>

          <!-- Calendar Days -->
          <div
            v-for="day in daysInMonth"
            :key="day"
            class="calendar-day"
            :class="{
              'calendar-day--has-events': getEventsCountForDay(day) > 0,
              'calendar-day--selected': isSelectedDay(day),
              'calendar-day--today': isToday(day)
            }"
            @click="selectDay(day)"
          >
            <span class="day-number">{{ day }}</span>
            <span v-if="getEventsCountForDay(day) > 0" class="event-count">
              {{ getEventsCountForDay(day) }}
            </span>
          </div>
        </div>

        <!-- Selected Day Events -->
        <div v-if="selectedDate" class="selected-day-events">
          <h3>{{ selectedDateDisplay }}</h3>
          <div v-if="selectedDayEvents.length > 0" class="events-list">
            <EventCard
              v-for="event in selectedDayEvents"
              :key="event.ID"
              :event="event"
              @click="openEventDialog(event)"
            />
          </div>
          <div v-else class="no-events-message">
            <p>Keine Termine an diesem Tag.</p>
          </div>
        </div>
      </div>

      <!-- Event Dialog -->
      <EventDialog
        v-if="selectedEvent"
        :event="selectedEvent"
        @close="closeEventDialog"
        @participation-changed="handleParticipationChanged"
        @time-changed="handleTimeChanged"
        @food-changed="handleFoodChanged"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useEventsStore } from '@stores/events'
import IntroBar from '@components/IntroBar.vue'
import EventDialog from '@components/EventDialog.vue'
import EventCard from '@components/EventCard.vue'

const eventsStore = useEventsStore()

// Use store state
const loading = computed(() => eventsStore.loading)
const error = computed(() => eventsStore.error)

// Calendar state
const currentMonth = ref(new Date().getMonth() + 1) // 1-12
const currentYear = ref(new Date().getFullYear())

// Initialize with today's date
const today = new Date()
const todayFormatted = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
const selectedDate = ref(todayFormatted)

const weekDays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

// Calendar computeds
const monthYearDisplay = computed(() => {
  const date = new Date(currentYear.value, currentMonth.value - 1)
  return date.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 0).getDate()
})

const firstDayOfMonth = computed(() => {
  const day = new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
  // Convert Sunday (0) to 7, then subtract 1 to make Monday = 0
  return day === 0 ? 6 : day - 1
})

// Group events by date (using eventsByDate from store)
const eventsByDate = computed(() => eventsStore.eventsByDate)

// Selected day events
const selectedDayEvents = computed(() => {
  if (!selectedDate.value) return []
  const events = eventsByDate.value[selectedDate.value] || []

  // Sort by TimeStart ascending (earliest first)
  return [...events].sort((a, b) => {
    if (!a.TimeStart && !b.TimeStart) return 0
    if (!a.TimeStart) return 1
    if (!b.TimeStart) return -1
    return a.TimeStart.localeCompare(b.TimeStart)
  })
})

const selectedDateDisplay = computed(() => {
  if (!selectedDate.value) return ''
  const [year, month, day] = selectedDate.value.split('-')
  const date = new Date(year, month - 1, day)
  return date.toLocaleDateString('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

// Calendar methods
const getEventsCountForDay = (day) => {
  const dateKey = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return eventsByDate.value[dateKey]?.length || 0
}

const selectDay = (day) => {
  const dateKey = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  selectedDate.value = dateKey
}

const isSelectedDay = (day) => {
  if (!selectedDate.value) return false
  const dateKey = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return selectedDate.value === dateKey
}

const isToday = (day) => {
  const today = new Date()
  return day === today.getDate() &&
         currentMonth.value === today.getMonth() + 1 &&
         currentYear.value === today.getFullYear()
}

const previousMonth = async () => {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value--
  } else {
    currentMonth.value--
  }
  selectedDate.value = null

  // Load events for the new month
  await eventsStore.fetchEvents(currentYear.value, currentMonth.value)
}

const nextMonth = async () => {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value++
  } else {
    currentMonth.value++
  }
  selectedDate.value = null

  // Load events for the new month
  await eventsStore.fetchEvents(currentYear.value, currentMonth.value)
}

// Event dialog
const selectedEvent = ref(null)

function openEventDialog(event) {
  selectedEvent.value = event
}

function closeEventDialog() {
  selectedEvent.value = null
}

function handleParticipationChanged(eventId, updatedParticipation) {
  // Update event in store
  const event = eventsStore.getEventById(eventId)
  if (event) {
    Object.assign(event, updatedParticipation)
  }
}

function handleTimeChanged(eventId, updatedData) {
  // Update event in store
  const event = eventsStore.getEventById(eventId)
  if (event) {
    Object.assign(event, updatedData)
  }
}

function handleFoodChanged(mealId, type) {
  // Handled by store directly
  console.log('Food participation changed:', mealId, type)
}

// Load events on mount
onMounted(async () => {
  // Load current month
  await eventsStore.fetchEvents(currentYear.value, currentMonth.value)
})
</script>
