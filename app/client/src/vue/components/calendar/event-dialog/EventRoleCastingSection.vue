<template>
  <!-- Rollenplan des Termins: wer an seinen Tagen welche Skript-Rolle des Events übernimmt -->
  <div v-if="visible" class="role-casting-section">
    <div class="section-feature-header">
      <h3 class="event-role-casting_title">Rollenplan</h3>
      <AppIconButton
        v-if="canManageContent && event.EventURLSegment"
        variant="primary"
        aria-label="Rollenplan bearbeiten"
        title="Auf der Event-Seite bearbeiten"
        :to="{ name: 'EventDetail', params: { segment: event.EventURLSegment } }"
      >
        <span class="icon-mask" :style="editIconStyle" aria-hidden="true" />
      </AppIconButton>
    </div>

    <ul v-if="event.RoleAssignments.length" class="role-casting-list">
      <li
        v-for="a in event.RoleAssignments"
        :key="a.ID"
        class="role-casting-item"
        :class="{ 'role-casting-item--me': a.Member?.ID === authStore.user?.ID }"
      >
        <span class="role-casting-item_role">{{ a.RoleTitle }}</span>
        <span class="role-casting-item_member">{{ a.Member?.Name ?? 'Unbekannt' }}</span>
        <span class="role-casting-item_time">{{ formatWhen(a) }}</span>
      </li>
    </ul>

    <p v-else class="event-section-empty">Noch keine Rollen zugeteilt.</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionEdit from '../../../../../icons/actions/action_edit.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false },
})

const authStore = useAuthStore()

// Wie Tagesordnung/Mahlzeiten: mit Rechten immer, sonst nur mit Inhalt
const visible = computed(() => {
  if (!props.event.EnableRoleCasting || !props.event.EventID) return false
  return props.canManageContent || props.event.RoleAssignments.length > 0
})

const multiDay = computed(() => (props.event.DateEnd || props.event.DateStart) !== props.event.DateStart)
const dayFormat = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' })

function formatWhen(a) {
  const time = a.TimeStart ? `${a.TimeStart} – ${a.TimeEnd}` : 'ganztägig'
  return multiDay.value ? `${dayFormat.format(new Date(`${a.Date}T00:00:00Z`))}, ${time}` : time
}
</script>
