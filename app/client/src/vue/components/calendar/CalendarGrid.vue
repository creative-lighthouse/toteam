<template>
  <div
    class="calendar"
    ref="calendarEl"
    @touchstart="handleTouchStart"
    @touchend="handleTouchEnd"
    @mousedown="handleMouseDown"
    @mouseup="handleMouseUp"
  >
    <!-- Day Names -->
    <div class="days">
      <div
        v-for="day in dayNames"
        :key="day"
        class="day_name"
      >
        {{ day }}
      </div>

      <!-- Calendar Days -->
      <CalendarDay
        v-for="(day, index) in calendarDays"
        :key="`day-${index}`"
        :day-number="day.day"
        :is-current-month="day.isCurrentMonth"
        :is-today="day.isToday"
        :events="day.events"
        @event-click="$emit('event-click', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import CalendarDay from './CalendarDay.vue'

const props = defineProps({
  calendarDays: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['event-click', 'swipe-left', 'swipe-right'])

const calendarEl = ref(null)
const touchStartX = ref(0)
const touchEndX = ref(0)
const mouseStartX = ref(0)
const isMouseDown = ref(false)

const dayNames = ['MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO']

// Touch event handlers for swipe
function handleTouchStart(e) {
  touchStartX.value = e.touches[0].clientX
}

function handleTouchEnd(e) {
  touchEndX.value = e.changedTouches[0].clientX
  handleSwipe()
}

// Mouse event handlers for desktop swipe
function handleMouseDown(e) {
  isMouseDown.value = true
  mouseStartX.value = e.clientX
}

function handleMouseUp(e) {
  if (isMouseDown.value) {
    const mouseEndX = e.clientX
    const diff = mouseStartX.value - mouseEndX

    if (Math.abs(diff) > 100) { // Minimum swipe distance
      if (diff > 0) {
        emit('swipe-left')
      } else {
        emit('swipe-right')
      }
    }
  }
  isMouseDown.value = false
}

function handleSwipe() {
  const diff = touchStartX.value - touchEndX.value

  if (Math.abs(diff) > 50) { // Minimum swipe distance
    if (diff > 0) {
      // Swipe left - next month
      emit('swipe-left')
    } else {
      // Swipe right - previous month
      emit('swipe-right')
    }
  }
}
</script>
