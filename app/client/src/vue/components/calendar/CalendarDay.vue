<template>
  <div
    :class="[
      'day_num',
      {
        'not-current-month': !isCurrentMonth,
        'date-today': isToday
      }
    ]"
  >
    <div class="day_number">{{ dayNumber }}</div>

    <!-- Events for this day -->
    <div
      v-for="event in events"
      :key="event.ID"
      :class="[
        'event',
        `event--status-${event.Status}`,
        `event--color-${getEventColor(event)}`
      ]"
      @click="$emit('event-click', event)"
    >
      <div class="event_title">{{ event.Title }}</div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  dayNumber: {
    type: Number,
    required: true
  },
  isCurrentMonth: {
    type: Boolean,
    default: true
  },
  isToday: {
    type: Boolean,
    default: false
  },
  events: {
    type: Array,
    default: () => []
  }
})

defineEmits(['event-click'])

function getEventColor(event) {
  if (!event.UserParticipation) {
    return 'gray'
  }
  return event.UserParticipation.Type
}
</script>
