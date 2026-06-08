<template>
    <div ref="scrollerEl" class="calendar-scroller">
        <div v-for="week in weeks" :key="week.weekKey" class="calendar-week_wrap">
            <CalendarWeek :weekNumber="week.weekNumber">
                <CalendarDay
                    v-for="day in week.days"
                    :key="day.dateStr"
                    :dayNumber="day.dayOfMonth"
                    :date="day.date"
                    :isToday="day.isToday"
                    :events="day.events"
                />
            </CalendarWeek>
        </div>
    </div>
</template>

<script setup>
import { useInfiniteScroll } from '@vueuse/core'
import { ref, onMounted, useTemplateRef } from 'vue'
import CalendarWeek from './CalendarWeek.vue'
import CalendarDay from './CalendarDay.vue'

const el = useTemplateRef('scrollerEl')
var currentMaxWeek = 0;
var currentMinWeek = 0;

// Infinite Scroll nach unten (Zukunft)
useInfiniteScroll(
    el,
    () => {
        // Load next week
        const newWeeks = generateNextWeek()
        weeks.value.push(...newWeeks)
    },
    {
        distance: 200,
        direction: 'bottom',
        canLoadMore: () => true
    }
)

// Infinite Scroll nach oben (Vergangenheit)
useInfiniteScroll(
    el,
    () => {
        // Speichere aktuelle Scroll-Position
        const scrollEl = el.value
        if (!scrollEl) return

        const oldScrollHeight = scrollEl.scrollHeight
        const oldScrollTop = scrollEl.scrollTop

        // Load previous week
        const newWeeks = generatePreviousWeek()
        weeks.value.unshift(...newWeeks)

        // Warte auf DOM-Update, dann korrigiere Scroll-Position
        requestAnimationFrame(() => {
            const newScrollHeight = scrollEl.scrollHeight
            const heightDiff = newScrollHeight - oldScrollHeight
            scrollEl.scrollTop = oldScrollTop + heightDiff
        })
    },
    {
        distance: 200,
        direction: 'top',
        canLoadMore: () => true
    }
)

const weeks = ref([])

/**
 * Gibt die ISO Wochennummer zurück
 */
function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
    const dayNum = d.getUTCDay() || 7
    d.setUTCDate(d.getUTCDate() + 4 - dayNum)
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
}

/**
 * Gibt den Montag der Woche für ein gegebenes Datum zurück
 */
function getMonday(date) {
    const d = new Date(date)
    const day = d.getDay()
    const diff = d.getDate() - day + (day === 0 ? -6 : 1) // Montag ist der erste Tag
    return new Date(d.setDate(diff))
}

/**
 * Formatiert Datum zu YYYY-MM-DD
 */
function formatDate(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}


function generateNextWeek() {
    currentMaxWeek++;
    const startDate = new Date(2024, 0, 1 + (currentMaxWeek * 7));
    const endDate = new Date(2024, 0, 7 + (currentMaxWeek * 7));
    return generateWeeks(startDate, endDate);
}

function generatePreviousWeek() {
    currentMinWeek--;
    const startDate = new Date(2024, 0, 1 + (currentMinWeek * 7));
    const endDate = new Date(2024, 0, 7 + (currentMinWeek * 7));
    return generateWeeks(startDate, endDate);
}

function generateWeeks(startDate, endDate, today = new Date()) {
    const weeks = []
    let currentDate = getMonday(startDate)

    while (currentDate <= endDate) {
        const weekNumber = getWeekNumber(currentDate)
        const days = []

        for (let i = 0; i < 7; i++) {
            const dateStr = formatDate(currentDate)
            days.push({
                date: new Date(currentDate),
                dateStr,
                dayOfMonth: currentDate.getDate(),
                isToday: dateStr === formatDate(today),
                events: [] // Hier können später echte Events geladen werden
            })
            currentDate.setDate(currentDate.getDate() + 1)
        }

        weeks.push({
            weekKey: `${startDate.getFullYear()}-W${weekNumber}`,
            weekNumber,
            days
        })
    }

    return weeks
}

/**
 * Initialisiert den Kalender mit dem aktuellen Monat
 */
function initializeCalendar() {
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    // Nur der aktuelle Monat
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
    const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)

    weeks.value = generateWeeks(firstOfMonth, lastOfMonth, today)

    console.log(`[Calendar] Initialized with ${weeks.value.length} weeks for current month`)
}

onMounted(() => {
    initializeCalendar()
})
</script>

<style scoped>
.calendar-scroller {
    overflow-y: auto;
    height: calc(100dvh - 160px); /* Feste Höhe damit es scrollbar ist */
    background-color: var(--color-background, #fff);
    position: fixed;
    top: 80px; /* Unter der AppHeader */
    left: 0;
    right: 0;
    transform: translateX(0);
}
</style>
