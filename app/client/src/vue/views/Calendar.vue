<template>
  <div class="section section--CalendarPage">
    <IntroBar title="Kalender" description="Verwalte deine Termine und Events." />

    <div class="section_content section_content--calendar">
      <CalendarScroller />

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
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import IntroBar from '@components/IntroBar.vue'
import EventDialog from '@components/EventDialog.vue'
import CalendarScroller from '../components/calendar/CalendarScroller.vue'

const eventsStore = useEventsStore()

// Use store state
const events = computed(() => eventsStore.events)
const loading = computed(() => eventsStore.loading)
const error = computed(() => eventsStore.error)

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
</script>

<style scoped>
.section_content--calendar {
  padding: 1rem;
}
</style>
