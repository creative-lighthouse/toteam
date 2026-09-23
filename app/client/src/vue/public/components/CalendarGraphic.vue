<template>
  <div class="calendar-graphic" aria-hidden="true">
    <div class="calendar-graphic__month">
      <div class="calendar-graphic__weekdays">
        <span v-for="d in weekdays" :key="d">{{ d }}</span>
      </div>
      <div class="calendar-graphic__days">
        <span
          v-for="cell in cells"
          :key="cell.key"
          class="calendar-graphic__day"
          :class="{
            'is-empty': !cell.day,
            'is-event': cell.day && eventDays.has(cell.day),
            'is-active': cell.day === activeEvent.day,
          }"
        >
          {{ cell.day }}
        </span>
      </div>
    </div>

    <Transition name="calendar-fade" mode="out-in">
      <div class="calendar-graphic__card" :key="activeIndex">
        <div class="calendar-graphic__card-header">
          <span class="calendar-graphic__card-date">{{ activeEvent.dateLabel }}</span>
          <h3>{{ activeEvent.title }}</h3>
          <span class="calendar-graphic__card-time">{{ activeEvent.time }}</span>
        </div>
        <ul class="calendar-graphic__participants">
          <li
            v-for="(p, i) in activeEvent.participants"
            :key="p.name"
            class="calendar-graphic__participant"
            :style="{ transitionDelay: `${i * 0.1}s` }"
          >
            <span class="calendar-graphic__status" :class="`calendar-graphic__status--${p.status}`">
              <svg v-if="p.status === 'zugesagt'" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </span>
            <span class="calendar-graphic__name">{{ p.name }}</span>
            <span v-if="p.note" class="calendar-graphic__note">{{ p.note }}</span>
          </li>
        </ul>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']

// A fixed, abstract 30-day month grid – just illustrative, not a real date.
// Day 1 lands in grid column `leadingBlanks`, so every day's weekday is
// derived from that offset – this keeps the month view and the event card
// always in sync, instead of hardcoding a label that could drift out of sync.
const leadingBlanks = 4
const cells = Array.from({ length: leadingBlanks }, (_, i) => ({ key: `b${i}`, day: null })).concat(
  Array.from({ length: 30 }, (_, i) => ({ key: i + 1, day: i + 1 }))
)

function dateLabel(day) {
  const weekday = weekdays[(day - 1 + leadingBlanks) % 7]
  return `${weekday}., ${day}.`
}

const events = [
  {
    day: 2,
    dateLabel: dateLabel(2),
    title: 'Vereinsausflug',
    time: '10:00 – 16:00',
    participants: [
      { name: 'Anna', status: 'zugesagt' },
      { name: 'Ben', status: 'zugesagt', note: 'ab 12:00' },
      { name: 'Chris', status: 'abgesagt' },
    ],
  },
  {
    day: 11,
    dateLabel: dateLabel(11),
    title: 'Vereinsabend',
    time: '18:00 – 21:00',
    participants: [
      { name: 'Anna', status: 'zugesagt' },
      { name: 'Ben', status: 'zugesagt', note: 'ab 19:00' },
      { name: 'Dana', status: 'abgesagt' },
      { name: 'Elena', status: 'zugesagt' },
      { name: 'Finn', status: 'zugesagt', note: 'bis 20:00' },
    ],
  },
  {
    day: 18,
    dateLabel: dateLabel(18),
    title: 'Vorstandssitzung',
    time: '19:00 – 20:30',
    participants: [
      { name: 'Anna', status: 'zugesagt' },
      { name: 'Dana', status: 'zugesagt', note: 'bis 20:00' },
      { name: 'Elena', status: 'abgesagt' },
      { name: 'Ben', status: 'zugesagt' },
    ],
  },
  {
    day: 29,
    dateLabel: dateLabel(29),
    title: 'Trainingsabend',
    time: '18:30 – 20:00',
    participants: [
      { name: 'Chris', status: 'zugesagt' },
      { name: 'Elena', status: 'zugesagt' },
      { name: 'Finn', status: 'zugesagt', note: 'bis 19:30' },
      { name: 'Anna', status: 'abgesagt' },
      { name: 'Ben', status: 'zugesagt', note: 'ab 19:00' },
    ],
  },
]

const eventDays = new Set(events.map((e) => e.day))

const activeIndex = ref(0)
const activeEvent = computed(() => events[activeIndex.value])

let timer = null

onMounted(() => {
  const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  if (prefersReducedMotion) return

  timer = window.setInterval(() => {
    activeIndex.value = (activeIndex.value + 1) % events.length
  }, 5000)
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
})
</script>
