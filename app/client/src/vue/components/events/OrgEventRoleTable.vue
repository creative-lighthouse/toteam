<template>
  <!-- Rollenplan (Mitgliederansicht): Rollen als Spalten, Personen alphabetisch als Zeilen,
       in den Zellen wann jemand die Rolle übernimmt. Bei vielen Rollen seitwärts scrollbar. -->
  <div class="org-event-role-table">
    <h3 class="org-event-role-table_title">Rollenplan</h3>

    <div v-if="days.length > 1" class="org-event-role-table_days" role="group" aria-label="Tag">
      <button
        type="button"
        class="org-event-role-table_day"
        :class="{ 'org-event-role-table_day--active': selectedDay === null }"
        :aria-pressed="selectedDay === null"
        @click="selectedDay = null"
      >Alle Tage</button>
      <button
        v-for="day in days"
        :key="day.Date"
        type="button"
        class="org-event-role-table_day"
        :class="{ 'org-event-role-table_day--active': day.Date === selectedDay }"
        :aria-pressed="day.Date === selectedDay"
        :title="day.label"
        @click="selectedDay = day.Date"
      >{{ formatCastingDay(day.Date) }}</button>
    </div>

    <p v-if="!rows.length" class="org-event-role-table_hint">
      {{ selectedDay ? 'An diesem Tag ist noch niemand eingeteilt.' : 'Noch keine Rollen zugeteilt.' }}
    </p>

    <div v-else class="org-event-role-table_scroll">
      <table class="org-event-role-table_grid">
        <thead>
          <tr v-if="scriptsWithRoles.length > 1">
            <th class="org-event-role-table_corner" rowspan="2" scope="col">Person</th>
            <th
              v-for="script in scriptsWithRoles"
              :key="script.ID"
              :colspan="script.Roles.length"
              scope="colgroup"
              class="org-event-role-table_script"
            >{{ script.Title }}</th>
          </tr>
          <tr>
            <th v-if="scriptsWithRoles.length <= 1" class="org-event-role-table_corner" scope="col">Person</th>
            <th
              v-for="role in roles"
              :key="role.ID"
              scope="col"
              class="org-event-role-table_role"
              :class="{ 'org-event-role-table_role--empty': !castRoleIds.has(role.ID) }"
              :title="role.Description || null"
            >
              {{ role.Title }}
              <span v-if="!castRoleIds.has(role.ID)" class="org-event-role-table_role-empty">unbesetzt</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.member.ID"
            :class="{ 'org-event-role-table_row--me': row.member.ID === authStore.user?.ID }"
          >
            <th scope="row" class="org-event-role-table_person">
              <AppAvatar :src="row.member.Avatar" :alt="row.member.Name" img-class="org-event-role-table_avatar" />
              <span>{{ row.member.Name }}</span>
            </th>
            <td v-for="role in roles" :key="role.ID" class="org-event-role-table_cell">
              <span
                v-for="a in row.byRole[role.ID] ?? []"
                :key="a.ID"
                class="org-event-role-table_slot"
              >
                <span v-if="selectedDay === null" class="org-event-role-table_slot-day">{{ formatCastingDay(a.Date) }}</span>
                <span class="org-event-role-table_slot-time">{{ formatCastingSpan(a) }}</span>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppAvatar from '@components/ui/AppAvatar.vue'
import { castingDayList, formatCastingDay, formatCastingSpan } from '@utils/roleCasting'

const props = defineProps({
  scripts: { type: Array, default: () => [] },
  assignments: { type: Array, default: () => [] },
  castingDays: { type: Array, default: () => [] },
})

const authStore = useAuthStore()

// null = alle Tage
const selectedDay = ref(null)

const days = computed(() => castingDayList(props.castingDays, props.assignments))
const scriptsWithRoles = computed(() => props.scripts.filter(s => s.Roles.length))
const roles = computed(() => scriptsWithRoles.value.flatMap(s => s.Roles))

const shownAssignments = computed(() =>
  props.assignments.filter(a => a.Member && (selectedDay.value === null || a.Date === selectedDay.value))
)

const castRoleIds = computed(() => new Set(shownAssignments.value.map(a => a.RoleID)))

// Eine Zeile pro Person (alphabetisch), Zuteilungen nach Rolle gruppiert und chronologisch
const rows = computed(() => {
  const byMember = new Map()
  shownAssignments.value.forEach(a => {
    if (!byMember.has(a.Member.ID)) byMember.set(a.Member.ID, { member: a.Member, byRole: {} })
    const row = byMember.get(a.Member.ID)
    ;(row.byRole[a.RoleID] ??= []).push(a)
  })
  const sortKey = a => `${a.Date} ${a.TimeStart ?? ''}`
  return [...byMember.values()]
    .map(row => {
      Object.values(row.byRole).forEach(list => list.sort((x, y) => sortKey(x).localeCompare(sortKey(y))))
      return row
    })
    .sort((x, y) => x.member.Name.localeCompare(y.member.Name, 'de'))
})
</script>
