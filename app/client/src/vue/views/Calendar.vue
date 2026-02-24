<template>
  <div class="section section--CalendarPage">
    <IntroBar title="Kalender" description="Verwalte deine Termine und Events." />

    <div class="section_content">
      <!-- Calendar Header -->
      <CalendarHeader
        :year="currentYear"
        :month="currentMonth"
        @previous="previousMonth"
        @next="nextMonth"
      />

      <!-- Loading State -->
      <div v-if="loading" class="section_infobox">
        <p>Lade Kalenderdaten...</p>
      </div>

      <!-- Error State -->
      <div v-if="error" class="section_infobox error">
        <p>Fehler beim Laden: {{ error }}</p>
        <button @click="loadEvents" class="button">Erneut versuchen</button>
      </div>

      <!-- Calendar Grid -->
      <CalendarGrid
        v-if="!loading && !error"
        :calendar-days="calendarDays"
        @event-click="openEventDialog"
        @swipe-left="nextMonth"
        @swipe-right="previousMonth"
      />

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
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCalendarStore } from '@stores/calendar'
import IntroBar from '@components/IntroBar.vue'
import CalendarHeader from '@components/calendar/CalendarHeader.vue'
import CalendarGrid from '@components/calendar/CalendarGrid.vue'
import EventDialog from '@components/EventDialog.vue'

const route = useRoute()
const router = useRouter()
const calendarStore = useCalendarStore()

// Use store state
const loading = computed(() => calendarStore.loading)
const error = computed(() => calendarStore.error)
const events = computed(() => calendarStore.events)
const currentYear = computed(() => calendarStore.currentYear)
const currentMonth = computed(() => calendarStore.currentMonth)
const selectedEvent = ref(null)

// Helper function
function formatDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month - 1, 1)
  const lastDay = new Date(year, month, 0)
  const daysInMonth = lastDay.getDate()
  const startDayOfWeek = (firstDay.getDay() + 6) % 7 // Convert Sunday=0 to Monday=0

  const days = []
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  // Add days from previous month
  const prevMonthLastDay = new Date(year, month - 1, 0).getDate()
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const day = prevMonthLastDay - i
    const date = new Date(year, month - 2, day)
    days.push({
      day,
      date: formatDate(date),
      isCurrentMonth: false,
      isToday: false,
      events: []
    })
  }

  // Add days from current month
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month - 1, day)
    const dateStr = formatDate(date)
    const isToday = date.getTime() === today.getTime()

    days.push({
      day,
      date: dateStr,
      isCurrentMonth: true,
      isToday,
      events: events.value.filter(e => e.Date === dateStr)
    })
  }

  // Add days from next month to fill the grid
  const remainingDays = 42 - days.length // 6 weeks * 7 days
  for (let day = 1; day <= remainingDays; day++) {
    const date = new Date(year, month, day)
    days.push({
      day,
      date: formatDate(date),
      isCurrentMonth: false,
      isToday: false,
      events: []
    })
  }

  return days
})

async function loadEvents() {
  await calendarStore.fetchCurrentMonth()
}

function updateURL() {
  const monthParam = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}`
  const query = { month: monthParam }

  if (selectedEvent.value) {
    query.event = selectedEvent.value.ID
  }

  // Only update if query has changed
  if (route.query.month !== query.month || route.query.event !== query.event) {
    router.push({ query })
  }
}

async function previousMonth() {
  calendarStore.previousMonth()
  await calendarStore.fetchCurrentMonth()
  updateURL()
}

async function nextMonth() {
  calendarStore.nextMonth()
  await calendarStore.fetchCurrentMonth()
  updateURL()
}

function openEventDialog(event) {
  selectedEvent.value = event
  updateURL()
}

function closeEventDialog() {
  selectedEvent.value = null
  updateURL()
}

async function handleParticipationChanged(eventId, participation) {
  // The store already updated local state, just update selectedEvent if needed
  if (selectedEvent.value && selectedEvent.value.ID === eventId) {
    const updatedEvent = calendarStore.getEventById(eventId)
    if (updatedEvent) {
      selectedEvent.value = updatedEvent
    }
  }
}

async function handleTimeChanged(eventId, timeData) {
  // The store already updated local state, just update selectedEvent if needed
  if (selectedEvent.value && selectedEvent.value.ID === eventId) {
    const updatedEvent = calendarStore.getEventById(eventId)
    if (updatedEvent) {
      selectedEvent.value = updatedEvent
    }
  }
}

async function handleFoodChanged(mealId, type) {
  // The store already updated local state, just update selectedEvent if needed
  if (selectedEvent.value) {
    const updatedEvent = calendarStore.getEventById(selectedEvent.value.ID)
    if (updatedEvent) {
      selectedEvent.value = updatedEvent
    }
  }
}

// Watch for month/year changes and reload events
watch([currentYear, currentMonth], () => {
  loadEvents()
})

// Watch for URL changes (e.g., browser back/forward)
watch(() => route.query, async (newQuery, oldQuery) => {
  if (newQuery.month !== oldQuery.month) {
    const [year, month] = (newQuery.month || '').split('-')
    if (year && month) {
      const newYear = parseInt(year)
      const newMonth = parseInt(month)
      // Only update if actually different (avoids double-loading)
      if (newYear !== currentYear.value || newMonth !== currentMonth.value) {
        calendarStore.setMonth(newYear, newMonth)
        await calendarStore.fetchCurrentMonth()
      }
    }
  }
  if (newQuery.event !== oldQuery.event) {
    if (newQuery.event) {
      await openEventFromURL()
    } else {
      selectedEvent.value = null
    }
  }
})

// Initialize from URL parameters
function initializeFromURL() {
  // Parse month from URL
  if (route.query.month) {
    const [year, month] = route.query.month.split('-')
    if (year && month) {
      calendarStore.setMonth(parseInt(year), parseInt(month))
    }
  }
}

// Load event from URL if specified
async function openEventFromURL() {
  if (route.query.event) {
    const eventId = parseInt(route.query.event)

    // Don't reload if already selected
    if (selectedEvent.value && selectedEvent.value.ID === eventId) {
      return
    }

    // Wait for events to load if needed
    if (events.value.length === 0) {
      await loadEvents()
    }

    // Find and open the event
    const event = calendarStore.getEventById(eventId)
    if (event) {
      selectedEvent.value = event
    } else {
      console.warn('Event not found in current month:', eventId)
      // Event might be in a different month - could load it via API
      // For now just clear the event parameter
      const query = { ...route.query }
      delete query.event
      router.replace({ query })
    }
  }
}

onMounted(async () => {
  initializeFromURL()
  await loadEvents()
  await openEventFromURL()
})
</script>

