<template>
  <!-- Filter für Aufgabenlisten (Übersicht und Unteraufgaben) — gedacht für den
       filters-Slot der AppSearchBar, daher ohne eigenes Wrapper-Element -->
  <select
    v-if="organizations.length > 1"
    aria-label="Aufgaben nach Organisation filtern"
    :value="organizationId ?? ''"
    @change="emit('update:organizationId', $event.target.value ? parseInt($event.target.value) : null)"
  >
    <option value="">Alle Organisationen</option>
    <option v-for="org in organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
  </select>

  <select
    aria-label="Aufgaben nach Status filtern"
    :value="state ?? ''"
    @change="emit('update:state', $event.target.value || null)"
  >
    <option value="">Alle Status</option>
    <option v-for="s in TASK_STATES" :key="s.value" :value="s.value">{{ s.label }}</option>
  </select>

  <!-- Filter by person (owner or supporter) -->
  <TaskPersonFilter :model-value="personId" @update:model-value="emit('update:personId', $event)" />

  <!-- View mode toggle (desktop/tablet only — mobile always shows the list) -->
  <AppViewToggle v-if="!isMobile" :model-value="viewMode" :options="TASK_VIEW_MODES" @update:model-value="emit('update:viewMode', $event)" />
</template>

<script setup>
import { TASK_STATES } from '@utils/taskFilters'
import { useMediaQuery } from '@utils/useMediaQuery'
import TaskPersonFilter from '@components/tasks/TaskPersonFilter.vue'
import AppViewToggle from '@components/ui/AppViewToggle.vue'

defineProps({
  // Organisationen zur Auswahl — der Filter erscheint erst ab zwei
  organizations: { type: Array, default: () => [] },
  organizationId: { type: Number, default: null },
  state: { type: String, default: null },
  personId: { type: Number, default: null },
  viewMode: { type: String, default: 'list' },
})

const emit = defineEmits(['update:organizationId', 'update:state', 'update:personId', 'update:viewMode'])

const TASK_VIEW_MODES = [
  { value: 'list', label: 'Listenansicht', icon: 'list' },
  { value: 'kanban', label: 'Kanban-Ansicht', icon: 'kanban' },
  { value: 'table', label: 'Tabellenansicht', icon: 'table' },
]

const isMobile = useMediaQuery('(max-width: 700px)')
</script>
