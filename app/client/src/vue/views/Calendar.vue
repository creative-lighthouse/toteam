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
                    <AppIconButton variant="neutral" aria-label="Vorheriger Monat" @click="previousMonth">
                        <span class="icon-mask" :style="backIconStyle" />
                    </AppIconButton>
                    <!-- aria-live: Monatswechsel (auch per Pfeiltasten) wird angesagt -->
                    <h2 aria-live="polite">{{ monthYearDisplay }}</h2>
                    <AppButton
                        size="small"
                        variant="secondary"
                        :disabled="isCurrentMonth"
                        title="Heute"
                        @click="jumptotoday"
                    >Heute</AppButton>
                    <AppIconButton variant="neutral" aria-label="Nächster Monat" @click="nextMonth">
                        <span class="icon-mask" :style="forwardIconStyle" />
                    </AppIconButton>
                </div>

                <!-- Calendar Grid -->
                <!--
                    Tastatur: ein Tab-Stopp auf dem fokussierten Tag. Pfeiltasten wechseln
                    Tag/Woche, Pos1/Ende Wochenanfang/-ende, Bild auf/ab den Monat.
                    Leertaste/Enter wählt den Tag und springt zur Liste darunter.
                -->
                <div
                    ref="calendarGridEl"
                    class="calendar-grid"
                    :class="{ 'calendar-grid--loading': monthLoading }"
                    role="group"
                    :aria-label="`Kalender ${monthYearDisplay}`"
                    :aria-busy="monthLoading"
                    @keydown="onGridKeydown"
                >
                    <!-- Weekday Headers (stecken für Screenreader schon im Label jedes Tages) -->
                    <div v-for="day in weekDays" :key="day" class="calendar-weekday" aria-hidden="true">
                        {{ day }}
                    </div>

                    <!-- Calendar Days (6 rows × 7 cols, includes prev/next month) -->
                    <div
                        v-for="cell in calendarDays"
                        :key="`${cell.year}-${cell.month}-${cell.day}`"
                        class="calendar-day"
                        role="button"
                        :data-date="makeDateKey(cell.day, cell.month, cell.year)"
                        :tabindex="makeDateKey(cell.day, cell.month, cell.year) === focusedDate ? 0 : -1"
                        :aria-label="dayLabel(cell)"
                        :aria-pressed="isSelectedDay(cell.day, cell.month, cell.year)"
                        :aria-current="isToday(cell.day, cell.month, cell.year) ? 'date' : undefined"
                        :class="{
                        'calendar-day--outside': !cell.isCurrentMonth,
                        'calendar-day--has-events': getEventsCountForDay(cell.day, cell.month, cell.year) > 0,
                        'calendar-day--selected': isSelectedDay(cell.day, cell.month, cell.year),
                        'calendar-day--today': isToday(cell.day, cell.month, cell.year)
                        }"
                        @click="onDayClick(cell)"
                        @focus="focusedDate = makeDateKey(cell.day, cell.month, cell.year)"
                    >
                        <span v-if="cell.weekNumber" class="week-number" :title="`KW ${cell.weekNumber}`" aria-hidden="true">{{ cell.weekNumber }}</span>
                        <span class="day-number" aria-hidden="true">{{ cell.day }}</span>
                        <!-- Skeleton, solange die Termine des Monats noch laden -->
                        <div v-if="monthLoading && cell.isCurrentMonth" class="event-dots event-dots--skeleton" aria-hidden="true">
                        <span class="event-dot-skeleton"></span>
                        </div>
                        <div
                        v-else-if="getEventsCountForDay(cell.day, cell.month, cell.year) > 0 || getAbsenceCountForDay(cell.day, cell.month, cell.year) > 0"
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
                    <!-- Ziel des Fokus nach Auswahl eines Tages per Tastatur -->
                    <h3 ref="selectedDayHeading" tabindex="-1" class="selected-day-events_title">{{ selectedDateDisplay }}</h3>

                    <div v-if="selectedDayEvents.length > 0" class="events-list">
                        <EventCard
                        v-for="event in selectedDayEvents"
                        :key="event.ID"
                        :event="event"
                        @click="(ev, cardEl) => openEvent(ev, cardEl)"
                        />
                    </div>
                    <div v-else-if="selectedDateAbsences.length === 0" class="no-events-message">
                        <p>Keine Termine an diesem Tag.</p>
                    </div>
                </div>

                <!-- Absences -->
                <div v-if="selectedDateAbsences.length > 0" class="absences-list">
                    <h4 class="absences-list__title">Abwesend</h4>
                    <!-- Eigene Abwesenheiten sind Buttons (per Tab/Enter bearbeitbar), fremde nur Text -->
                    <component
                    :is="isOwnAbsence(a) ? 'button' : 'div'"
                    v-for="a in selectedDateAbsences"
                    :key="a.MemberID"
                    :type="isOwnAbsence(a) ? 'button' : undefined"
                    :aria-label="isOwnAbsence(a) ? absenceLabel(a) : undefined"
                    class="absence-item"
                    :class="{ 'absence-item--own': isOwnAbsence(a) }"
                    @click="isOwnAbsence(a) && entryModalRef.openEditAbsence(a)"
                    >
                    <AppAvatar
                        :src="a.ProfileImageURL"
                        :alt="a.MemberName"
                        img-class="absence-item__avatar"
                    />
                    <span class="absence-item__name">{{ a.MemberName }}</span>
                    <span v-if="a.Note" class="absence-item__note">{{ a.Note }}</span>
                    </component>
                </div>

                <!-- ICS Link + Termin eintragen -->
                <div class="add-container">
                    <AppIconButton variant="primary" aria-label="Termin hinzufügen" @click="entryModalRef.open(selectedDate)">
                        +
                    </AppIconButton>
                </div>
                <div class="copy-container">
                    <button
                    v-if="authStore.user?.Hash"
                    type="button"
                    class="button copy-btn"
                    @click="icsLinkModalRef.open()"
                    >ICS-Link für externe Kalender kopieren →</button>
                </div>
                <CalendarIcsLinkModal
                    v-if="authStore.user?.Hash"
                    ref="icsLinkModalRef"
                    :hash="authStore.user.Hash"
                    :organizations="memberOrgs"
                />
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

            <!-- Platzhalter, solange ein per Link (eventID) geöffneter Termin noch lädt -->
            <EventDialogSkeleton
                v-if="linkedEventLoadingId"
                @close="cancelLinkedEvent"
            />

            <!-- Poll (Terminfindung) Dialog -->
            <PollDialog
                v-if="selectedPollEvent"
                :event="selectedPollEvent"
                @close="closePollDialog"
                @edit-poll="onEditPoll"
                @finalized="onPollFinalized"
            />

            <!-- Add Appointment Modal -->
            <CalendarEntryCreateModal
                ref="entryModalRef"
                @appointment-created="refreshEvents"
                @appointment-updated="refreshEvents"
                @appointment-deleted="onAppointmentDeleted"
                @poll-created="refreshEvents"
                @poll-updated="refreshEvents"
                @poll-deleted="onPollDeleted"
                @absence-created="refreshAbsences"
                @absence-updated="refreshAbsences"
                @absence-deleted="refreshAbsences"
                @closed="onAddAppointmentModalClosed"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch, provide } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useEventsStore } from '@stores/events'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useAuthStore } from '@stores/auth'
import { useOrganizationsStore } from '@stores/organizations'
import EventDialog from '@components/calendar/event-dialog/EventDialog.vue'
import PollDialog from '@components/calendar/PollDialog.vue'
import EventDialogSkeleton from '@components/calendar/event-dialog/EventDialogSkeleton.vue'
import { morphIntoModal } from '@utils/viewTransition'
import { MODAL_FOCUS_FALLBACK } from '@utils/modalFocus'
import EventCard from '@components/calendar/EventCard.vue'
import AppMenu from '@components/layout/AppMenu.vue'
import CalendarEntryCreateModal from '@components/calendar/CalendarEntryCreateModal.vue'
import CalendarIcsLinkModal from '@components/calendar/CalendarIcsLinkModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import actionForward from '../../../icons/actions/action_forward.svg'
import actionBack from '../../../icons/actions/action_back.svg'

// Masked so the arrows take the button's text color (white on hover)
const backIconStyle = { maskImage: `url("${actionBack}")`, WebkitMaskImage: `url("${actionBack}")` }
const forwardIconStyle = { maskImage: `url("${actionForward}")`, WebkitMaskImage: `url("${actionForward}")` }

const eventsStore = useEventsStore()
const authStore = useAuthStore()
const orgsStore = useOrganizationsStore()
const canManageContent = computed(() =>
  orgsStore.organizations.some(o => o.Permissions?.includes('CALENDAR_MANAGE'))
)
const route = useRoute()
const router = useRouter()
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

  // Calendar week on the first cell (Monday) of every row
  for (let i = 0; i < cells.length; i += 7) {
    cells[i].weekNumber = getISOWeek(cells[i].year, cells[i].month, cells[i].day)
  }

  return cells
})

// ISO 8601 week number (weeks start on Monday, week 1 contains the first Thursday)
function getISOWeek(year, month, day) {
  const date = new Date(Date.UTC(year, month - 1, day))
  const weekday = date.getUTCDay() || 7
  date.setUTCDate(date.getUTCDate() + 4 - weekday)
  const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1))
  return Math.ceil(((date - yearStart) / 86400000 + 1) / 7)
}

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

  const counts = { poll: 0, accept: 0, maybe: 0, decline: 0, none: 0, cancelled: 0 }
  events.forEach(e => {
    // Abgesagt zählt vor der eigenen Rückmeldung: graues X statt Zusagefarbe
    if (e.Status === 'Cancelled') {
      counts.cancelled++
      return
    }
    if (e.IsPoll) {
      counts.poll++
      return
    }
    const status = e.UserParticipation?.Type?.toLowerCase() || 'none'
    counts[status] = (counts[status] || 0) + 1
  })

  return Object.entries(counts)
    .filter(([, count]) => count > 0)
    .map(([status, count]) => ({ status, count }))
}

// ── Abwesenheiten ───────────────────────────────────────────────────────────

const isOwnAbsence = a => a.MemberID === authStore.user?.ID

function formatAbsenceDate(dateStr) {
  const [y, m, d] = dateStr.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('de-DE', { day: 'numeric', month: 'long' })
}

// "Deine Abwesenheit bearbeiten, 3. Oktober bis 6. Oktober, Urlaub"
function absenceLabel(a) {
  const parts = ['Deine Abwesenheit bearbeiten']
  if (a.DateStart) {
    parts.push(a.DateEnd && a.DateEnd !== a.DateStart
      ? `${formatAbsenceDate(a.DateStart)} bis ${formatAbsenceDate(a.DateEnd)}`
      : formatAbsenceDate(a.DateStart))
  }
  if (a.Note) parts.push(a.Note)
  return parts.join(', ')
}

// ── Tastatur-Navigation im Kalender-Raster ──────────────────────────────────

const calendarGridEl = ref(null)
const selectedDayHeading = ref(null)

// Wurde ein Termin nicht aus der Liste geöffnet (z.B. per Link mit eventID),
// landet der Fokus nach dem Schließen auf der Tagesüberschrift der Liste
provide(MODAL_FOCUS_FALLBACK, () => selectedDayHeading.value)
const focusedDate = ref(selectedDate.value)

// Der Tab-Stopp muss auf einem sichtbaren Tag liegen: ausgewählter Tag, heute oder der Monatserste
function defaultFocusDate() {
  const inView = key => calendarDays.value.some(c => makeDateKey(c.day, c.month, c.year) === key)
  if (selectedDate.value && inView(selectedDate.value)) return selectedDate.value
  const now = new Date()
  const today = makeDateKey(now.getDate(), now.getMonth() + 1, now.getFullYear())
  if (inView(today)) return today
  return makeDateKey(1, currentMonth.value, currentYear.value)
}

watch(calendarDays, () => {
  if (!calendarDays.value.some(c => makeDateKey(c.day, c.month, c.year) === focusedDate.value)) {
    focusedDate.value = defaultFocusDate()
  }
}, { immediate: true })

function dayLabel(cell) {
  const date = new Date(cell.year, cell.month - 1, cell.day)
  const parts = [date.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })]
  if (isToday(cell.day, cell.month, cell.year)) parts.push('heute')
  if (!monthLoading.value || !cell.isCurrentMonth) {
    const events = getEventsCountForDay(cell.day, cell.month, cell.year)
    parts.push(events === 0 ? 'keine Termine' : events === 1 ? '1 Termin' : `${events} Termine`)
    const absences = getAbsenceCountForDay(cell.day, cell.month, cell.year)
    if (absences) parts.push(absences === 1 ? '1 Abwesenheit' : `${absences} Abwesenheiten`)
  }
  return parts.join(', ')
}

function onDayClick(cell) {
  focusedDate.value = makeDateKey(cell.day, cell.month, cell.year)
  selectDayAndFetchAbsences(cell.day, cell.month, cell.year)
}

function parseKey(key) {
  const [y, m, d] = key.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function keyOf(date) {
  return makeDateKey(date.getDate(), date.getMonth() + 1, date.getFullYear())
}

async function focusDate(date) {
  const year = date.getFullYear()
  const month = date.getMonth() + 1
  focusedDate.value = keyOf(date)
  // Verlässt der Fokus den angezeigten Monat, wechselt der Kalender mit
  if (year !== currentYear.value || month !== currentMonth.value) {
    currentYear.value = year
    currentMonth.value = month
    loadMonth(year, month)
  }
  await nextTick()
  calendarGridEl.value?.querySelector(`[data-date="${focusedDate.value}"]`)?.focus()
}

function onGridKeydown(event) {
  const cellEl = event.target.closest?.('[data-date]')
  if (!cellEl) return
  const current = parseKey(cellEl.dataset.date)
  const weekday = (current.getDay() + 6) % 7 // Montag = 0
  const target = new Date(current)

  switch (event.key) {
    case 'ArrowLeft': target.setDate(current.getDate() - 1); break
    case 'ArrowRight': target.setDate(current.getDate() + 1); break
    case 'ArrowUp': target.setDate(current.getDate() - 7); break
    case 'ArrowDown': target.setDate(current.getDate() + 7); break
    case 'Home': target.setDate(current.getDate() - weekday); break
    case 'End': target.setDate(current.getDate() + (6 - weekday)); break
    case 'PageUp':
    case 'PageDown': {
      // Gleicher Tag im Vor-/Folgemonat, am Monatsende gekürzt (31. → 30./28.)
      const offset = event.key === 'PageUp' ? -1 : 1
      const lastDay = new Date(current.getFullYear(), current.getMonth() + offset + 1, 0).getDate()
      target.setFullYear(current.getFullYear(), current.getMonth() + offset, Math.min(current.getDate(), lastDay))
      break
    }
    case 'Enter':
    case ' ':
      event.preventDefault()
      selectFocusedDay(current)
      return
    default:
      return
  }
  event.preventDefault()
  focusDate(target)
}

async function selectFocusedDay(date) {
  selectDayAndFetchAbsences(date.getDate(), date.getMonth() + 1, date.getFullYear())
  // Fokus zur Liste unter dem Kalender: die Überschrift nennt den Tag, danach Tab zu den Terminen
  await nextTick()
  selectedDayHeading.value?.focus()
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

function getAdjacentMonth(year, month, offset) {
  const date = new Date(year, month - 1 + offset, 1)
  return { year: date.getFullYear(), month: date.getMonth() + 1 }
}

// Lädt den vorherigen und nächsten Monat im Hintergrund mit,
// damit Termine an Monatsgrenzen (z.B. 1. November in der Oktober-Ansicht) sofort sichtbar sind
function prefetchAdjacentMonths(year, month) {
  const prev = getAdjacentMonth(year, month, -1)
  const next = getAdjacentMonth(year, month, 1)
  for (const m of [prev, next]) {
    eventsStore.fetchEvents(m.year, m.month)
      .then(() => loadedMonths.add(monthKey(m.year, m.month)))
      .catch(() => {})
  }
}

// Monate, deren Termine schon einmal geladen wurden — dort kein Skeleton, die
// Daten werden nur still im Hintergrund aktualisiert
const loadedMonths = new Set()
const monthKey = (year, month) => `${year}-${month}`
let monthLoadToken = 0

// Wechselt die Ansicht sofort; das Raster ist direkt da, nur die Termin-Punkte
// zeigen bis zum Laden ein Skeleton. Der Token verhindert, dass eine ältere,
// langsamere Anfrage beim schnellen Durchblättern den Ladezustand zu früh beendet.
async function loadMonth(year, month, forceRefresh = false) {
  const token = ++monthLoadToken
  const key = monthKey(year, month)
  // Schon Termine im Store (z.B. vom Dashboard)? Dann ebenfalls kein Skeleton
  const prefix = `${year}-${String(month).padStart(2, '0')}`
  const hasData = loadedMonths.has(key) || eventsStore.events.some(e => e.DateStart?.startsWith(prefix))
  monthLoading.value = !hasData
  try {
    await Promise.all([
      eventsStore.fetchEvents(year, month, forceRefresh),
      loadAbsenceCountsForCurrentMonth(),
    ])
    loadedMonths.add(key)
  } catch {
    // Fehler zeigt der Store (error) an
  } finally {
    if (token === monthLoadToken) {
      monthLoading.value = false
      prefetchAdjacentMonths(year, month)
    }
  }
}

const jumptotoday = async () => {
  const now = new Date()
  const newYear = now.getFullYear()
  const newMonth = now.getMonth() + 1
  const todayKey = `${newYear}-${String(newMonth).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

  if (!isCurrentMonth.value) {
    currentYear.value = newYear
    currentMonth.value = newMonth
    selectedDate.value = todayKey
    await loadMonth(newYear, newMonth)
    return
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
  await loadMonth(currentYear.value, currentMonth.value)
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
  await loadMonth(currentYear.value, currentMonth.value)
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
  // Auch die Nachbarmonate frisch laden: mehrtägige Termine und die Optionen einer
  // Terminfindung liegen oft (auch) in einem anderen Monat als dem angezeigten
  const prev = getAdjacentMonth(currentYear.value, currentMonth.value, -1)
  const next = getAdjacentMonth(currentYear.value, currentMonth.value, 1)
  await Promise.all([
    eventsStore.fetchEvents(currentYear.value, currentMonth.value, true),
    eventsStore.fetchEvents(prev.year, prev.month, true).catch(() => {}),
    eventsStore.fetchEvents(next.year, next.month, true).catch(() => {}),
  ])
  await loadAbsenceCountsForCurrentMonth()
  if (selectedDate.value) {
    try {
      selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(selectedDate.value)
    } catch {
      selectedDateAbsences.value = []
    }
  }
  // Reopen event dialog with fresh data after editing
  const id = pendingReopenId.value
  pendingReopenId.value = null
  if (id) {
    const fresh = eventsStore.getEventById(id)
    if (fresh) openEventDialog(fresh)
  }
  // Terminfindung nach dem Bearbeiten mit frischen Daten wieder öffnen — bevorzugt
  // an derselben Option, sonst an der ersten noch vorhandenen
  const poll = pendingReopenPoll.value
  pendingReopenPoll.value = null
  if (poll) {
    const pollEvents = eventsStore.events
      .filter(e => e.IsPoll && e.PollID === poll.pollId)
      .sort((a, b) => a.DateStart.localeCompare(b.DateStart))
    const fresh = pollEvents.find(e => e.ID === poll.eventId) ?? pollEvents[0]
    if (fresh) openPollDialog(fresh)
  }
}

// Event dialog
const selectedEvent = ref(null)
const selectedPollEvent = ref(null)
const pendingReopenId = ref(null)
const pendingReopenPoll = ref(null)

// Die angeklickte Karte morpht (wo unterstützt) in den Termin- bzw. Terminfindungs-Dialog
function openEvent(event, cardEl = null) {
  morphIntoModal(cardEl, () => {
    if (event.IsPoll) {
      openPollDialog(event)
    } else {
      openEventDialog(event)
    }
  })
}

function openEventDialog(event) {
  selectedEvent.value = event
  router.replace({ query: { ...route.query, eventID: event.ID } })
}

function closeEventDialog() {
  selectedEvent.value = null
  const { eventID: _removed, ...rest } = route.query
  router.replace({ query: rest })
}

function openPollDialog(event) {
  selectedPollEvent.value = event
  router.replace({ query: { ...route.query, eventID: event.ID } })
}

function closePollDialog() {
  selectedPollEvent.value = null
  const { eventID: _removed, ...rest } = route.query
  router.replace({ query: rest })
}

function onEditPoll(event) {
  selectedPollEvent.value = null
  pendingReopenPoll.value = { pollId: event.PollID, eventId: event.ID }
  const { eventID: _removed, ...rest } = route.query
  router.replace({ query: rest })
  entryModalRef.value.openEditPoll(event)
}

async function onPollDeleted() {
  pendingReopenPoll.value = null
  await refreshEvents()
}

async function onPollFinalized() {
  closePollDialog()
  await refreshEvents()
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
  selectedEvent.value = null
  pendingReopenId.value = event.ID
  // Remove eventID from URL while editing (onAddAppointmentModalClosed handles both cancel + save)
  const { eventID: _removed, ...rest } = route.query
  router.replace({ query: rest })
  entryModalRef.value.openEditAppointment(event)
}

function onAddAppointmentModalClosed(wasSaved) {
  if (!wasSaved) {
    // User cancelled — clear pending reopen and clean up URL
    pendingReopenId.value = null
    pendingReopenPoll.value = null
    const { eventID: _removed, ...rest } = route.query
    router.replace({ query: rest })
  }
}

async function onAppointmentDeleted() {
  closeEventDialog()
  await refreshEvents()
}

// ICS link (Konfiguration im Modal)
const icsLinkModalRef = ref(null)
const memberOrgs = computed(() => orgsStore.organizations.filter(o => o.MembershipStatus === 'member'))

// Load events on mount
// ── Per Link (eventID) geöffneter Termin ──────────────────────────────────────
// Liegt der Termin schon im Store (z.B. vom Dashboard), öffnet er sofort und wird
// nach dem Laden nur aktualisiert. Sonst steht bis dahin ein Skeleton-Modal da,
// das anschließend in den echten Dialog morpht.
const linkedEventLoadingId = ref(null)

function showLinkedEvent(event) {
  if (event.IsPoll) {
    selectedPollEvent.value = event
  } else {
    selectedEvent.value = event
  }
}

function cancelLinkedEvent() {
  linkedEventLoadingId.value = null
  const { eventID: _removed, ...rest } = route.query
  router.replace({ query: rest })
}

function resolveLinkedEvent(id) {
  const fresh = eventsStore.getEventById(id)

  // Skeleton offen → in den echten Dialog morphen (oder schließen, falls es den Termin nicht gibt)
  if (linkedEventLoadingId.value === id) {
    if (!fresh) {
      cancelLinkedEvent()
      return
    }
    const skeletonEl = document.querySelector('dialog.event-dialog-skeleton[open]')
    morphIntoModal(skeletonEl, () => {
      linkedEventLoadingId.value = null
      showLinkedEvent(fresh)
    })
    return
  }

  // Sofort aus dem Store geöffnet → mit frischen Daten ersetzen, solange noch offen
  if (fresh) {
    if (selectedEvent.value?.ID === id) selectedEvent.value = fresh
    if (selectedPollEvent.value?.ID === id) selectedPollEvent.value = fresh
  }
}

onMounted(async () => {
  // Check for deep-link query params from notification or shared URL
  const linkDate = route.query.date
  const linkEventID = route.query.eventID ? Number(route.query.eventID) : null

  if (linkEventID) {
    const cached = eventsStore.getEventById(linkEventID)
    if (cached) {
      showLinkedEvent(cached)
    } else {
      linkedEventLoadingId.value = linkEventID
    }
  }

  // If a specific date was linked, navigate to that month
  if (linkDate) {
    const [year, month] = linkDate.split('-').map(Number)
    currentYear.value = year
    currentMonth.value = month
    selectedDate.value = linkDate
  }

  // Always fetch fresh data for the current month on page load
  await Promise.all([
    loadMonth(currentYear.value, currentMonth.value, true),
    orgsStore.fetchOrganizations(),
  ])

  // Verlinkten Termin öffnen bzw. aktualisieren (URL stimmt bereits) — vor den
  // Abwesenheiten, damit der Dialog nicht unnötig auf sie wartet
  if (linkEventID) resolveLinkedEvent(linkEventID)

  // Load absences for the initially selected day
  if (selectedDate.value) {
    try {
      selectedDateAbsences.value = await eventsStore.fetchAbsencesForDate(selectedDate.value)
    } catch {
      selectedDateAbsences.value = []
    }
  }

})
</script>
