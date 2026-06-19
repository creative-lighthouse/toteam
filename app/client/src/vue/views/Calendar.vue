<template>
    <div class="section section--CalendarPage">
        <div class="section_content section_content--calendar">
            <!-- Error State -->
            <div v-if="error" class="error-state">
                <p>Fehler beim Laden der Termine: {{ error }}</p>
            </div>

            <!-- Calendar View -->
            <div v-else class="events-calendar">
                <!-- Month Navigation -->
                <div class="calendar-header">
                    <button @click="previousMonth" class="btn-nav">
                        <img :src="actionBack" alt="Previous Month" />
                    </button>
                    <h2>{{ monthYearDisplay }}</h2>
                    <button @click="jumptotoday" class="btn-today" :class="{ 'btn-nav--current-month': isCurrentMonth }" title="Heute">
                        <span class="day-number">Heute</span>
                    </button>
                    <button @click="nextMonth" class="btn-nav">
                        <img :src="actionForward" alt="Next Month" />
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div class="calendar-grid" :class="{ 'calendar-grid--loading': monthLoading }">
                    <!-- Weekday Headers -->
                    <div v-for="day in weekDays" :key="day" class="calendar-weekday">
                        {{ day }}
                    </div>

                    <!-- Calendar Days (6 rows × 7 cols, includes prev/next month) -->
                    <div
                        v-for="cell in calendarDays"
                        :key="`${cell.year}-${cell.month}-${cell.day}`"
                        class="calendar-day"
                        :class="{
                        'calendar-day--outside': !cell.isCurrentMonth,
                        'calendar-day--has-events': getEventsCountForDay(cell.day, cell.month, cell.year) > 0,
                        'calendar-day--selected': isSelectedDay(cell.day, cell.month, cell.year),
                        'calendar-day--today': isToday(cell.day, cell.month, cell.year)
                        }"
                        @click="selectDayAndFetchAbsences(cell.day, cell.month, cell.year)"
                    >
                        <span class="day-number">{{ cell.day }}</span>
                        <div
                        v-if="getEventsCountForDay(cell.day, cell.month, cell.year) > 0 || getAbsenceCountForDay(cell.day, cell.month, cell.year) > 0"
                        class="event-dots"
                        >
                        <span
                            v-for="dot in getEventDotsForDay(cell.day, cell.month, cell.year)"
                            :key="dot.status"
                            class="event-dot"
                            :class="`event-dot--${dot.status}`"
                        ></span>
                        <span
                            v-if="getAbsenceCountForDay(cell.day, cell.month, cell.year) > 0"
                            class="absence-badge"
                        >{{ getAbsenceCountForDay(cell.day, cell.month, cell.year) }}</span>
                        </div>
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
                    <div v-else-if="selectedDateAbsences.length === 0" class="no-events-message">
                        <p>Keine Termine an diesem Tag.</p>
                    </div>
                </div>

                <!-- Absences -->
                <div v-if="selectedDateAbsences.length > 0" class="absences-list">
                    <h4 class="absences-list__title">Abwesend</h4>
                    <div
                    v-for="a in selectedDateAbsences"
                    :key="a.MemberID"
                    class="absence-item"
                    :class="{ 'absence-item--own': a.MemberID === authStore.user?.ID }"
                    @click="a.MemberID === authStore.user?.ID && entryModalRef.openEditAbsence(a)"
                    >
                    <img
                        v-if="a.ProfileImageURL"
                        :src="a.ProfileImageURL"
                        class="absence-item__avatar"
                        alt=""
                    />
                    <span class="absence-item__name">{{ a.MemberName }}</span>
                    <span v-if="a.Note" class="absence-item__note">{{ a.Note }}</span>
                    </div>
                </div>

                <!-- ICS Link + Termin eintragen -->
                <div class="add-container">
                    <button type="button" class="button" @click="entryModalRef.open(selectedDate)">
                        +
                    </button>
                </div>
                <div class="copy-container">
                    <button
                    type="button"
                    class="button copy-btn"
                    :class="{ 'copy-btn--copied': icsCopied }"
                    @click="copyICSLink"
                    >{{ icsCopied ? '✓ Link kopiert' : 'ICS-Link für externe Kalender kopieren →' }}</button>
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
                @edit-appointment="onEditAppointment"
            />

            <!-- Add Appointment Modal -->
            <AddAppointmentModal
                ref="entryModalRef"
                @appointment-created="refreshEvents"
                @appointment-updated="refreshEvents"
                @appointment-deleted="onAppointmentDeleted"
                @absence-created="refreshAbsences"
                @absence-updated="refreshAbsences"
                @absence-deleted="refreshAbsences"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useEventsStore } from '@stores/events'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useAuthStore } from '@stores/auth'
import { useOrganizationsStore } from '@stores/organizations'
import EventDialog from '@components/EventDialog/EventDialog.vue'
import EventCard from '@components/EventCard.vue'
import AppMenu from '@components/AppMenu.vue'
import AddAppointmentModal from '@components/AddAppointmentModal.vue'
import actionForward from '../../../icons/actions/action_forward.svg'
import actionBack from '../../../icons/actions/action_back.svg'

const eventsStore = useEventsStore()
const authStore = useAuthStore()
const orgsStore = useOrganizationsStore()
const canManageContent = computed(() =>
  orgsStore.organizations.some(o => ['moderator', 'admin'].includes(o.MembershipStatus))
)
const route = useRoute()
usePageHeaderStore().setHeader('Kalender', 'Verwalte deine Termine und Events. Du kannst hier Termine erstellen, einsehen und bei den Terminen Zu- oder absagen')

// Use store state
const loading = computed(() => eventsStore.loading)
const error = computed(() => eventsStore.error)
const monthLoading = ref(false)

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

// Always 42 cells (6 rows × 7 cols), filling in prev/next month days
const calendarDays = computed(() => {
  const cells = []
  const totalCells = 42
  const offset = firstDayOfMonth.value
  const dim = daysInMonth.value

  // Days from previous month
  const prevMonthDim = new Date(currentYear.value, currentMonth.value - 1, 0).getDate()
  const prevMonth = currentMonth.value === 1 ? 12 : currentMonth.value - 1
  const prevYear = currentMonth.value === 1 ? currentYear.value - 1 : currentYear.value
  for (let i = offset - 1; i >= 0; i--) {
    cells.push({ day: prevMonthDim - i, month: prevMonth, year: prevYear, isCurrentMonth: false })
  }

  // Days of current month
  for (let d = 1; d <= dim; d++) {
    cells.push({ day: d, month: currentMonth.value, year: currentYear.value, isCurrentMonth: true })
  }

  // Days from next month
  const nextMonth = currentMonth.value === 12 ? 1 : currentMonth.value + 1
  const nextYear = currentMonth.value === 12 ? currentYear.value + 1 : currentYear.value
  let nextDay = 1
  while (cells.length < totalCells) {
    cells.push({ day: nextDay++, month: nextMonth, year: nextYear, isCurrentMonth: false })
  }

  return cells
})

// Group events by date (using eventsByDate from store)
const eventsByDate = computed(() => eventsStore.eventsByDate)

// Selected day events
const selectedDayEvents = computed(() => {
  if (!selectedDate.value) return []
  const events = eventsByDate.value[selectedDate.value] || []

  // All-day events first, then timed events sorted by TimeStart
  return [...events].sort((a, b) => {
    if (a.AllDay && !b.AllDay) return -1
    if (!a.AllDay && b.AllDay) return 1
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
const makeDateKey = (day, month, year) =>
  `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`

const getEventsCountForDay = (day, month = currentMonth.value, year = currentYear.value) => {
  return eventsByDate.value[makeDateKey(day, month, year)]?.length || 0
}

const getEventDotsForDay = (day, month = currentMonth.value, year = currentYear.value) => {
  const events = eventsByDate.value[makeDateKey(day, month, year)] || []

  const counts = { accept: 0, maybe: 0, decline: 0, none: 0 }
  events.forEach(e => {
    const status = e.UserParticipation?.Type?.toLowerCase() || 'none'
    counts[status] = (counts[status] || 0) + 1
  })

  return Object.entries(counts)
    .filter(([, count]) => count > 0)
    .map(([status, count]) => ({ status, count }))
}

const selectDay = (day, month = currentMonth.value, year = currentYear.value) => {
  selectedDate.value = makeDateKey(day, month, year)
}

const isSelectedDay = (day, month = currentMonth.value, year = currentYear.value) => {
  if (!selectedDate.value) return false
  return selectedDate.value === makeDateKey(day, month, year)
}

const isToday = (day, month = currentMonth.value, year = currentYear.value) => {
  const now = new Date()
  return day === now.getDate() && month === now.getMonth() + 1 && year === now.getFullYear()
}

const isCurrentMonth = computed(() => {
  const now = new Date()
  return currentMonth.value === now.getMonth() + 1 && currentYear.value === now.getFullYear()
})

const jumptotoday = async () => {
  const now = new Date()
  const newYear = now.getFullYear()
  const newMonth = now.getMonth() + 1
  const todayKey = `${newYear}-${String(newMonth).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

  if (!isCurrentMonth.value) {
    currentYear.value = newYear
    currentMonth.value = newMonth
    monthLoading.value = true
    await eventsStore.fetchEvents(newYear, newMonth)
    monthLoading.value = false
  }

  selectedDate.value = todayKey
}

const previousMonth = async () => {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value--
  } else {
    currentMonth.value--
  }
  selectedDate.value = null
  selectedDateAbsences.value = []
  monthLoading.value = true
  await Promise.all([
    eventsStore.fetchEvents(currentYear.value, currentMonth.value),
    loadAbsenceCountsForCurrentMonth(),
  ])
  monthLoading.value = false
}

const nextMonth = async () => {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value++
  } else {
    currentMonth.value++
  }
  selectedDate.value = null
  selectedDateAbsences.value = []
  monthLoading.value = true
  await Promise.all([
    eventsStore.fetchEvents(currentYear.value, currentMonth.value),
    loadAbsenceCountsForCurrentMonth(),
  ])
  monthLoading.value = false
}

// AddCalendarEntryModal
const entryModalRef = ref(null)
const selectedDateAbsences = ref([])
const absenceCountsByDate = ref({})

function getAbsenceCountForDay(day, month, year) {
  return absenceCountsByDate.value[makeDateKey(day, month, year)] ?? 0
}

async function loadAbsenceCountsForCurrentMonth() {
  try {
    absenceCountsByDate.value = await eventsStore.fetchAbsenceCountsForMonth(
      currentYear.value,
      currentMonth.value
    )
  } catch {
    absenceCountsByDate.value = {}
  }
}

async function selectDayAndFetchAbsences(day, month, year) {
  selectDay(day, month, year)
  const date = makeDateKey(day, month, year)
  try {
    selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(date)
  } catch {
    selectedDateAbsences.value = []
  }
}

async function refreshAbsences() {
  await loadAbsenceCountsForCurrentMonth()
  if (selectedDate.value) {
    try {
      selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(selectedDate.value)
    } catch {
      selectedDateAbsences.value = []
    }
  }
}

async function refreshEvents() {
  await eventsStore.fetchEvents(currentYear.value, currentMonth.value, true)
  await loadAbsenceCountsForCurrentMonth()
  if (selectedDate.value) {
    try {
      selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(selectedDate.value)
    } catch {
      selectedDateAbsences.value = []
    }
  }
}

// Event dialog
const selectedEvent = ref(null)

function openEventDialog(event) {
  selectedEvent.value = event
}

function closeEventDialog() {
  selectedEvent.value = null
}

function handleParticipationChanged(_eventId, _updatedParticipation) {
  // Store already updates state in changeParticipation()
}

function handleTimeChanged(_eventId, _updatedData) {
  // Store already updates state in changeParticipationTime()
}

function handleFoodChanged(mealId, type) {
  // Handled by store directly
  console.log('Food participation changed:', mealId, type)
}

function onEditAppointment(event) {
  closeEventDialog()
  entryModalRef.value.openEditAppointment(event)
}

async function onAppointmentDeleted() {
  closeEventDialog()
  await refreshEvents()
}

// ICS link copy
const icsCopied = ref(false)
const icsLink = computed(() => {
  const hash = authStore.user?.Hash
  if (!hash) return null
  return `${window.location.origin}/ics?user=${hash}`
})

async function copyICSLink() {
  if (!icsLink.value) return
  await navigator.clipboard.writeText(icsLink.value)
  icsCopied.value = true
  setTimeout(() => { icsCopied.value = false }, 3000)
}

// Load events on mount
onMounted(async () => {
  // Check for deep-link query params from notification
  const linkDate = route.query.date
  const linkEventID = linkDate ? Number(route.query.eventID) : null

  // If a specific date was linked, navigate to that month
  if (linkDate) {
    const [year, month] = linkDate.split('-').map(Number)
    currentYear.value = year
    currentMonth.value = month
    selectedDate.value = linkDate
  }

  // Always fetch fresh data for the current month on page load
  await Promise.all([
    eventsStore.fetchEvents(currentYear.value, currentMonth.value, true),
    loadAbsenceCountsForCurrentMonth(),
    orgsStore.fetchOrganizations(),
  ])

  // Load absences for the initially selected day
  if (selectedDate.value) {
    try {
      selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(selectedDate.value)
    } catch {
      selectedDateAbsences.value = []
    }
  }

  // If a specific event was linked, open its dialog
  if (linkEventID) {
    const event = eventsStore.getEventById(linkEventID)
    if (event) {
      selectedEvent.value = event
    }
  }
})
</script>
