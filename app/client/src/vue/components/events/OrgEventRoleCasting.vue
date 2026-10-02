<template>
  <!-- Rollenzuteilung (Bearbeiten): pro Tag eines Termins mit Rollenplan, wer welche Rolle übernimmt -->
  <div class="org-event-role-casting">
    <h3 class="org-event-role-casting_title">Rollenzuteilung</h3>

    <p v-if="!days.length" class="org-event-role-casting_hint">
      Noch kein Termin mit Rollenplan. Schalte bei den Terminen des Events „Rollenplan“ ein,
      dann können hier für deren Tage Rollen zugeteilt werden.
    </p>

    <template v-else>
      <div class="org-event-role-casting_days" role="group" aria-label="Tag">
        <button
          v-for="day in days"
          :key="day.Date"
          type="button"
          class="org-event-role-casting_day"
          :class="{ 'org-event-role-casting_day--active': day.Date === selectedDay }"
          :aria-pressed="day.Date === selectedDay"
          :title="day.label"
          @click="selectedDay = day.Date"
        >
          <span>{{ formatDay(day.Date) }}</span>
          <span v-if="day.label" class="org-event-role-casting_day-label">{{ day.label }}</span>
          <span v-if="countForDay(day.Date)" class="org-event-role-casting_day-count">{{ countForDay(day.Date) }}</span>
        </button>
      </div>

      <div v-for="script in scriptsWithRoles" :key="script.ID" class="org-event-role-casting_script">
        <h4 v-if="scriptsWithRoles.length > 1" class="org-event-role-casting_script-title">{{ script.Title }}</h4>

        <div v-for="role in script.Roles" :key="role.ID" class="org-event-role-casting_role">
          <div class="org-event-role-casting_role-head">
            <span class="org-event-role-casting_role-title">{{ role.Title }}</span>
            <span v-if="!assignmentsFor(role).length" class="org-event-role-casting_role-empty">unbesetzt</span>
          </div>

          <ul v-if="assignmentsFor(role).length" class="org-event-role-casting_assignments">
            <li
              v-for="a in assignmentsFor(role)"
              :key="a.ID"
              class="org-event-role-casting_assignment"
              :class="{ 'org-event-role-casting_assignment--conflict': warnings[a.ID] }"
              :title="warnings[a.ID] || null"
            >
              <span class="org-event-role-casting_assignment-name">{{ a.Member?.Name ?? 'Unbekannt' }}</span>
              <span class="org-event-role-casting_assignment-time">{{ formatSpan(a) }}</span>
              <span v-if="warnings[a.ID]" class="org-event-role-casting_assignment-warn" :aria-label="warnings[a.ID]">⚠</span>
              <button type="button" class="org-event-role-casting_assignment-remove" aria-label="Zuteilung entfernen" :disabled="busy" @click="remove(a)">×</button>
            </li>
          </ul>

          <form class="org-event-role-casting_form" @submit.prevent="add(role)">
            <ScriptMemberPickerDropdown v-model="drafts[role.ID].MemberID" :members="memberOptions(role)" :aria-label="`Person für ${role.Title}`" />
            <label class="org-event-role-casting_allday">
              <input v-model="drafts[role.ID].AllDay" type="checkbox" @change="onAllDayChange(role)" />
              Ganztägig
            </label>
            <template v-if="!drafts[role.ID].AllDay">
              <input v-model="drafts[role.ID].TimeStart" type="time" class="input" aria-label="Von" required />
              <input v-model="drafts[role.ID].TimeEnd" type="time" class="input" aria-label="Bis" required />
            </template>
            <AppButton type="submit" variant="secondary" size="small" :disabled="busy || !canSubmit(role)">Zuteilen</AppButton>
          </form>
          <p v-if="draftHint(role)" class="org-event-role-casting_draft-hint">{{ draftHint(role) }}</p>
        </div>
      </div>
    </template>

    <p v-if="error" class="org-event-role-casting_error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/ui/AppButton.vue'
import ScriptMemberPickerDropdown from '@components/skript/ScriptMemberPickerDropdown.vue'
import { castingDayList, formatCastingDay, formatCastingSpan } from '@utils/roleCasting'

const props = defineProps({
  event: { type: Object, required: true },
  scripts: { type: Array, default: () => [] },
  assignments: { type: Array, default: () => [] },
  // Tage der Termine mit Rollenplan (SkriptApiController::castingDays())
  castingDays: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
})

// Neuer Gesamtstand nach jeder Änderung (siehe SkriptApiController::formatEventScripts())
const emit = defineEmits(['update'])

const store = useSkriptStore()
const busy = ref(false)
const error = ref(null)

const days = computed(() => castingDayList(props.castingDays, props.assignments))

const selectedDay = ref(null)
watch(days, list => {
  if (list.some(d => d.Date === selectedDay.value)) return
  const today = new Date().toISOString().slice(0, 10)
  selectedDay.value = (list.find(d => d.Date >= today) ?? list[0])?.Date ?? null
}, { immediate: true })

const scriptsWithRoles = computed(() => props.scripts.filter(s => s.Roles.length))
const roleTitles = computed(() => Object.fromEntries(props.scripts.flatMap(s => s.Roles.map(r => [r.ID, r.Title]))))

const dayAssignments = computed(() => props.assignments.filter(a => a.Date === selectedDay.value))

function assignmentsFor(role) {
  return dayAssignments.value.filter(a => a.RoleID === role.ID)
}

function countForDay(day) {
  return props.assignments.filter(a => a.Date === day).length
}

// Ganztägig (ohne Uhrzeit) überschneidet sich mit allem
const toMinutes = t => parseInt(t.slice(0, 2)) * 60 + parseInt(t.slice(3, 5))
function overlaps(a, b) {
  if (!a.TimeStart || !b.TimeStart) return true
  return toMinutes(a.TimeStart) < toMinutes(b.TimeEnd) && toMinutes(b.TimeStart) < toMinutes(a.TimeEnd)
}
const formatWindows = windows => windows.map(w => `${w.TimeStart} – ${w.TimeEnd}`).join(', ')

// Wer am gewählten Tag zugesagt hat: MemberID → null (ganzer Tag) oder Zeitfenster
const availability = computed(() => {
  const day = days.value.find(d => d.Date === selectedDay.value)
  return new Map((day?.Available ?? []).map(a => [a.MemberID, a.Windows]))
})

// Hinweise an bestehenden Zuteilungen: Überschneidungen (nur aus Altdaten möglich)
// und Personen, die für den Tag nicht (mehr) zugesagt haben
const warnings = computed(() => {
  const result = {}
  const list = dayAssignments.value
  list.forEach(a => {
    const notes = []
    if (a.Member && !availability.value.has(a.Member.ID)) notes.push('Hat für diesen Tag nicht (mehr) zugesagt')
    const others = list.filter(b => b.ID !== a.ID && b.Member?.ID === a.Member?.ID && overlaps(a, b))
    if (others.length) notes.push(`Überschneidung mit: ${[...new Set(others.map(b => roleTitles.value[b.RoleID]))].join(', ')}`)
    if (notes.length) result[a.ID] = notes.join(' · ')
  })
  return result
})

function draftSpan(role) {
  const d = drafts[role.ID]
  return d.AllDay || !d.TimeStart || !d.TimeEnd ? { TimeStart: null, TimeEnd: null } : { TimeStart: d.TimeStart, TimeEnd: d.TimeEnd }
}

/**
 * Personen fürs Dropdown einer Rolle: nur wer am Tag zugesagt hat (bei einer
 * Zeitspanne: wessen Zusage sie abdeckt) und nicht schon ganztägig eingeteilt ist.
 * Wer zu einer anderen Zeit schon eine Rolle spielt, bekommt einen Hinweis und ist
 * gesperrt, wenn sich das mit der geplanten Zeit überschneidet.
 */
function memberOptions(role) {
  const span = draftSpan(role)
  return props.members.flatMap(m => {
    if (!availability.value.has(m.ID)) return []
    const windows = availability.value.get(m.ID)
    const own = dayAssignments.value.filter(a => a.Member?.ID === m.ID)
    if (own.some(a => !a.TimeStart)) return []
    if (span.TimeStart && windows && !windows.some(w => w.TimeStart <= span.TimeStart && w.TimeEnd >= span.TimeEnd)) return []
    return [{
      ...m,
      Hint: own.map(a => `spielt ${a.TimeStart} – ${a.TimeEnd} ${roleTitles.value[a.RoleID] ?? ''}`.trim()).join('; ') || null,
      Disabled: own.some(a => overlaps(a, span)),
    }]
  })
}

function canSubmit(role) {
  const id = drafts[role.ID].MemberID
  return !!id && memberOptions(role).some(m => m.ID === id && !m.Disabled)
}

// Hinweis unter der Eingabezeile
function draftHint(role) {
  const options = memberOptions(role)
  if (!options.length) {
    return availability.value.size
      ? 'Alle, die für diesen Tag zugesagt haben, sind zu dieser Zeit schon eingeteilt.'
      : 'Für diesen Tag hat noch niemand zugesagt.'
  }
  const id = drafts[role.ID].MemberID
  const windows = id && availability.value.get(id)
  if (windows && drafts[role.ID].AllDay) {
    const name = props.members.find(m => m.ID === id)?.Name ?? 'Diese Person'
    return `${name} hat nur für ${formatWindows(windows)} zugesagt.`
  }
  return null
}

// Eingabezeile pro Rolle — standardmäßig für den ganzen Tag
const emptyDraft = () => ({ MemberID: null, AllDay: true, TimeStart: '', TimeEnd: '' })
const drafts = reactive({})
watch(() => props.scripts, scripts => {
  scripts.forEach(s => s.Roles.forEach(r => {
    if (!drafts[r.ID]) drafts[r.ID] = emptyDraft()
  }))
}, { immediate: true })

// Wird "Ganztägig" abgewählt, schlagen wir die Zeit des Termins an diesem Tag vor
function onAllDayChange(role) {
  const draft = drafts[role.ID]
  if (draft.AllDay || draft.TimeStart) return
  const appt = days.value.find(d => d.Date === selectedDay.value)?.Appointments.find(a => a.TimeStart)
  draft.TimeStart = appt?.TimeStart ?? ''
  draft.TimeEnd = appt?.TimeEnd ?? ''
}

async function run(action) {
  busy.value = true
  error.value = null
  try {
    emit('update', await action())
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function add(role) {
  const draft = drafts[role.ID]
  return run(async () => {
    const state = await store.createEventAssignment(props.event.ID, {
      RoleID: role.ID,
      MemberID: draft.MemberID,
      Date: selectedDay.value,
      TimeStart: draft.AllDay ? null : draft.TimeStart || null,
      TimeEnd: draft.AllDay ? null : draft.TimeEnd || null,
    })
    drafts[role.ID] = emptyDraft()
    return state
  })
}

function remove(assignment) {
  return run(() => store.deleteEventAssignment(assignment.ID))
}

const formatDay = formatCastingDay
const formatSpan = formatCastingSpan
</script>
