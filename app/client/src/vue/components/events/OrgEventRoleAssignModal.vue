<template>
  <AppModal ref="modal" class="org-event-role-assign-modal" :title="title" @close="close">
    <template v-if="role && day">
      <p v-if="day.label" class="org-event-role-assign-modal_day">{{ day.label }}</p>

      <!-- Bisherige Zuteilungen dieser Zelle -->
      <ul v-if="cellAssignments.length" class="org-event-role-assign-modal_list">
        <li v-for="a in cellAssignments" :key="a.ID" class="org-event-role-assign-modal_item">
          <AppAvatar :src="a.Member?.Avatar" :alt="a.Member?.Name ?? ''" :name="a.Member?.Name ?? ''" img-class="org-event-role-assign-modal_avatar" />
          <span class="org-event-role-assign-modal_item-text">
            <span class="org-event-role-assign-modal_item-name">{{ a.Member?.Name ?? 'Unbekannt' }}</span>
            <span class="org-event-role-assign-modal_item-time">{{ formatCastingSpan(a) }}</span>
            <span v-if="warnings[a.ID]" class="org-event-role-assign-modal_item-warn">⚠ {{ warnings[a.ID] }}</span>
          </span>
          <AppIconButton variant="danger" aria-label="Zuteilung entfernen" title="Entfernen" :disabled="busy" @click="remove(a)">
            <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
          </AppIconButton>
        </li>
      </ul>
      <p v-else class="org-event-role-assign-modal_empty">Noch niemand eingeteilt.</p>

      <form id="org-event-role-assign-form" class="modalform" @submit.prevent="add">
        <label class="field">
          Person
          <ScriptMemberPickerDropdown
            v-model="draft.MemberID"
            :members="memberOptions"
            :aria-label="`Person für ${role.Title}`"
            @update:model-value="onMemberChange"
          />
        </label>

        <AppToggle v-model="draft.AllDay" label="Ganztägig" @update:model-value="onAllDayChange" />

        <template v-if="!draft.AllDay">
          <label class="field field--3">
            Von
            <input v-model="draft.TimeStart" type="time" required />
          </label>
          <label class="field field--3">
            Bis
            <input v-model="draft.TimeEnd" type="time" required />
          </label>
        </template>

        <p v-if="draftHint" class="org-event-role-assign-modal_hint">{{ draftHint }}</p>
        <div v-if="error" class="app-modal_error">{{ error }}</div>
      </form>
    </template>

    <template #actions>
      <AppButton variant="secondary" :disabled="busy" @click="close">Schließen</AppButton>
      <AppButton type="submit" form="org-event-role-assign-form" variant="primary" :disabled="busy || !canSubmit">
        {{ busy ? 'Speichern…' : 'Zuteilen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppModal from '@components/ui/AppModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import ScriptMemberPickerDropdown from '@components/skript/ScriptMemberPickerDropdown.vue'
import {
  formatCastingDay, formatCastingSpan, castingOverlaps, castingAvailability, castingWarnings,
} from '@utils/roleCasting'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  eventId: { type: Number, required: true },
  // Alle Zuteilungen des Events — die Liste aktualisiert sich nach jeder Änderung von selbst
  assignments: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  // Rollen-ID → Titel über alle Skripte des Events
  roleTitles: { type: Object, default: () => ({}) },
})

// Neuer Gesamtstand nach jeder Änderung (siehe SkriptApiController::formatEventScripts())
const emit = defineEmits(['update'])
const store = useSkriptStore()

const modal = ref(null)
const role = ref(null)
// castingDayList()-Eintrag: { Date, Appointments, Available, label }
const day = ref(null)
const busy = ref(false)
const error = ref(null)

// Standardmäßig für den ganzen Tag
const emptyDraft = () => ({ MemberID: null, AllDay: true, TimeStart: '', TimeEnd: '' })
const draft = reactive(emptyDraft())

const title = computed(() => (role.value && day.value ? `${role.value.Title} · ${formatCastingDay(day.value.Date)}` : 'Rolle zuteilen'))

const dayAssignments = computed(() => props.assignments.filter(a => a.Date === day.value?.Date))
const cellAssignments = computed(() =>
  dayAssignments.value
    .filter(a => a.RoleID === role.value?.ID)
    .sort((a, b) => (a.TimeStart ?? '').localeCompare(b.TimeStart ?? ''))
)
const availability = computed(() => castingAvailability(day.value))
const warnings = computed(() => castingWarnings(dayAssignments.value, availability.value, props.roleTitles))

const span = computed(() => (draft.AllDay || !draft.TimeStart || !draft.TimeEnd
  ? { TimeStart: null, TimeEnd: null }
  : { TimeStart: draft.TimeStart, TimeEnd: draft.TimeEnd }))

/**
 * Personen fürs Dropdown: nur wer am Tag zugesagt hat (bei einer Zeitspanne: wessen
 * Zusage sie abdeckt) und nicht schon ganztägig eingeteilt ist. Wer zu einer anderen
 * Zeit schon eine Rolle spielt (auch dieselbe, z.B. mit Pause dazwischen), bekommt
 * nur einen Hinweis — Überschneidungen meldet erst draftHint nach der Auswahl.
 */
const memberOptions = computed(() => props.members.flatMap(m => {
  if (!availability.value.has(m.ID)) return []
  const windows = availability.value.get(m.ID)
  const own = dayAssignments.value.filter(a => a.Member?.ID === m.ID)
  if (own.some(a => !a.TimeStart)) return []
  if (span.value.TimeStart && windows && !windows.some(w => w.TimeStart <= span.value.TimeStart && w.TimeEnd >= span.value.TimeEnd)) return []
  return [{
    ...m,
    Hint: own.map(a => `spielt ${a.TimeStart} – ${a.TimeEnd} ${props.roleTitles[a.RoleID] ?? ''}`.trim()).join('; ') || null,
  }]
}))

// Zuteilungen der gewählten Person an diesem Tag, die sich mit der geplanten Zeit überschneiden
const overlapping = computed(() => (draft.MemberID
  ? dayAssignments.value.filter(a => a.Member?.ID === draft.MemberID && castingOverlaps(a, span.value))
  : []))

const canSubmit = computed(() =>
  memberOptions.value.some(m => m.ID === draft.MemberID)
  && !overlapping.value.length
  && (draft.AllDay || (draft.TimeStart && draft.TimeEnd && draft.TimeStart < draft.TimeEnd))
)

const draftHint = computed(() => {
  if (!memberOptions.value.length) {
    return availability.value.size
      ? 'Alle, die für diesen Tag zugesagt haben, sind zu dieser Zeit schon eingeteilt.'
      : 'Für diesen Tag hat noch niemand zugesagt.'
  }
  if (overlapping.value.length) {
    const name = props.members.find(m => m.ID === draft.MemberID)?.Name ?? 'Diese Person'
    const taken = overlapping.value.map(a => `${formatCastingSpan(a)} „${props.roleTitles[a.RoleID] ?? ''}“`).join(', ')
    return `${name} spielt an diesem Tag schon ${taken} — bitte eine Zeit wählen, die sich nicht überschneidet.`
  }
  if (!draft.AllDay && draft.TimeStart && draft.TimeEnd && draft.TimeEnd <= draft.TimeStart) {
    return 'Das Ende muss nach dem Beginn liegen.'
  }
  const windows = draft.MemberID && availability.value.get(draft.MemberID)
  if (windows && draft.AllDay) {
    const name = props.members.find(m => m.ID === draft.MemberID)?.Name ?? 'Diese Person'
    return `${name} hat nur für ${windows.map(w => `${w.TimeStart} – ${w.TimeEnd}`).join(', ')} zugesagt.`
  }
  return null
})

// Spielt die gewählte Person an dem Tag schon zu einer bestimmten Zeit, passt "ganztägig"
// nicht mehr — dann direkt die Zeitfelder zeigen (leer, die Terminzeit wäre meist belegt)
function onMemberChange(memberId) {
  if (!memberId || !draft.AllDay) return
  if (dayAssignments.value.some(a => a.Member?.ID === memberId && a.TimeStart)) {
    draft.AllDay = false
    draft.TimeStart = ''
    draft.TimeEnd = ''
  }
}

// Wird "Ganztägig" abgewählt, schlagen wir die Zeit des Termins an diesem Tag vor
function onAllDayChange(allDay) {
  if (allDay || draft.TimeStart) return
  const appt = day.value?.Appointments.find(a => a.TimeStart)
  draft.TimeStart = appt?.TimeStart ?? ''
  draft.TimeEnd = appt?.TimeEnd ?? ''
}

function open(newRole, newDay) {
  role.value = newRole
  day.value = newDay
  Object.assign(draft, emptyDraft())
  error.value = null
  modal.value?.open()
}

// Auch fürs X und Escape — AppModal meldet beides nur als "close".
// Rolle/Tag bleiben stehen, damit der Inhalt während der Ausblende-Animation sichtbar bleibt.
function close() {
  modal.value?.close()
}

async function run(action) {
  busy.value = true
  error.value = null
  try {
    emit('update', await action())
    return true
  } catch (e) {
    error.value = e.message
    return false
  } finally {
    busy.value = false
  }
}

async function add() {
  if (!canSubmit.value) return
  const ok = await run(() => store.createEventAssignment(props.eventId, {
    RoleID: role.value.ID,
    MemberID: draft.MemberID,
    Date: day.value.Date,
    TimeStart: span.value.TimeStart,
    TimeEnd: span.value.TimeEnd,
  }))
  if (ok) close()
}

function remove(assignment) {
  return run(() => store.deleteEventAssignment(assignment.ID))
}

defineExpose({ open, close })
</script>
