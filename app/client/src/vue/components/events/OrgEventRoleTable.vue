<template>
  <!-- Rollenplan eines Skripts: Tage (der Termine mit Rollenplan) als Spalten, Rollen als
       Zeilen, in den Zellen wer die Rolle übernimmt. Mit `editable` öffnet ein Klick auf
       eine Zelle die Zuteilung (Event "cell"). Bei vielen Tagen seitwärts scrollbar. -->
  <div class="org-event-role-table">
    <table class="org-event-role-table_grid">
      <thead>
        <tr>
          <th class="org-event-role-table_corner" scope="col">Rolle</th>
          <th v-for="day in days" :key="day.Date" scope="col" class="org-event-role-table_day" :title="day.label || null">
            <span class="org-event-role-table_day-date">{{ formatCastingDay(day.Date) }}</span>
            <span v-if="day.label" class="org-event-role-table_day-label">{{ day.label }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="role in script.Roles" :key="role.ID">
          <th scope="row" class="org-event-role-table_role" :title="role.Description || null">{{ role.Title }}</th>
          <td v-for="day in days" :key="day.Date" class="org-event-role-table_cell">
            <component
              :is="editable ? 'button' : 'div'"
              :type="editable ? 'button' : null"
              class="org-event-role-table_cell-inner"
              :class="{ 'org-event-role-table_cell-inner--editable': editable }"
              :aria-label="editable ? `${role.Title} am ${formatCastingDay(day.Date)} zuteilen` : null"
              @click="editable && emit('cell', role, day)"
            >
              <span
                v-for="a in cellAssignments(role, day)"
                :key="a.ID"
                class="org-event-role-table_person"
                :class="{
                  'org-event-role-table_person--me': a.Member?.ID === authStore.user?.ID,
                  'org-event-role-table_person--warn': editable && warnings[a.ID],
                }"
                :title="editable && warnings[a.ID] ? warnings[a.ID] : null"
              >
                <AppAvatar :src="a.Member?.Avatar" :alt="a.Member?.Name ?? ''" :name="a.Member?.Name ?? ''" img-class="org-event-role-table_avatar" />
                <span class="org-event-role-table_name">{{ a.Member?.Name ?? 'Unbekannt' }}</span>
                <span v-if="a.TimeStart" class="org-event-role-table_time">{{ a.TimeStart }}–{{ a.TimeEnd }}</span>
              </span>
              <span v-if="!cellAssignments(role, day).length" class="org-event-role-table_empty" aria-hidden="true">
                {{ editable ? '+' : '–' }}
              </span>
            </component>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppAvatar from '@components/ui/AppAvatar.vue'
import { formatCastingDay, castingAvailability, castingWarnings } from '@utils/roleCasting'

const props = defineProps({
  // { ID, Title, Roles: [{ ID, Title, Description }] }
  script: { type: Object, required: true },
  // Alle Zuteilungen des Events (auch anderer Skripte — für die Hinweise)
  assignments: { type: Array, default: () => [] },
  // castingDayList(): [{ Date, Appointments, Available, label }]
  days: { type: Array, default: () => [] },
  // Rollen-ID → Titel über alle Skripte des Events
  roleTitles: { type: Object, default: () => ({}) },
  editable: { type: Boolean, default: false },
})

// (Rolle, Tag) — Zelle angeklickt
const emit = defineEmits(['cell'])
const authStore = useAuthStore()

function cellAssignments(role, day) {
  return props.assignments
    .filter(a => a.RoleID === role.ID && a.Date === day.Date)
    .sort((a, b) => (a.TimeStart ?? '').localeCompare(b.TimeStart ?? ''))
}

// Hinweise (nicht zugesagt, Überschneidung) für alle Zuteilungen, Tag für Tag
const warnings = computed(() => Object.assign({}, ...props.days.map(day =>
  castingWarnings(props.assignments.filter(a => a.Date === day.Date), castingAvailability(day), props.roleTitles)
)))
</script>
