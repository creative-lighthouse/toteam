<template>
  <!-- Aufgaben als Liste (nach Status gruppiert, einklappbar), Kanban oder Tabelle —
       für die Übersicht (fill: füllt die Seitenhöhe, Spalten scrollen einzeln)
       und die Unteraufgaben auf der Detailseite (normaler Seitenfluss) -->
  <div class="task-board" :class="{ 'task-board--fill': fill }">

    <!-- LIST VIEW — grouped by status, each group collapsible -->
    <div v-if="viewMode === 'list'" class="task-board_list">
      <div v-for="col in TASK_STATES" :key="col.value" class="task-board_group">
        <button
          type="button"
          class="task-board_group-header"
          :aria-expanded="!collapsedGroups.has(col.value)"
          @click="emit('toggle-group', col.value)"
        >
          <svg class="task-board_group-chevron" :class="{ 'task-board_group-chevron--collapsed': collapsedGroups.has(col.value) }" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          <span class="task-board_group-title">{{ col.label }}</span>
          <span class="task-board_count">{{ byState[col.value].length }}</span>
        </button>
        <div v-show="!collapsedGroups.has(col.value)" class="task-board_group-body">
          <TaskCard
            v-for="task in byState[col.value]"
            :key="task.ID"
            :task="task"
            :hide-org-logo="hideOrgLogo"
            @click="emit('open', task)"
          />
        </div>
      </div>
    </div>

    <!-- KANBAN VIEW — Karten per Drag & Drop in eine andere Spalte = Status ändern -->
    <div v-else-if="viewMode === 'kanban'" class="task-board_kanban">
      <div
        v-for="col in TASK_STATES"
        :key="col.value"
        class="task-board_column"
        @dragover.prevent
        @drop="onDrop(col.value)"
      >
        <div class="task-board_column-header">
          <span class="task-board_column-title">{{ col.label }}</span>
          <span class="task-board_count">{{ byState[col.value].length }}</span>
        </div>
        <div class="task-board_column-cards">
          <TaskCard
            v-for="task in byState[col.value]"
            :key="task.ID"
            :task="task"
            :hide-org-logo="hideOrgLogo"
            draggable="true"
            @dragstart="onDragStart($event, task)"
            @click="emit('open', task)"
          />
        </div>
      </div>
    </div>

    <!-- TABLE VIEW -->
    <TaskTable v-else :tasks="tasksSortedByState" @open="task => emit('open', task)" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TASK_STATES, groupTasksByState } from '@utils/taskFilters'
import TaskCard from '@components/tasks/TaskCard.vue'
import TaskTable from '@components/tasks/TaskTable.vue'

const props = defineProps({
  // bereits gefilterte Aufgaben
  tasks: { type: Array, required: true },
  viewMode: { type: String, default: 'list' },
  // eingeklappte Status-Gruppen der Listenansicht (Status-Werte)
  collapsedGroups: { type: Set, default: () => new Set() },
  // Seitenhöhe füllen, Liste/Spalten scrollen einzeln (Übersicht)
  fill: { type: Boolean, default: false },
  hideOrgLogo: { type: Boolean, default: false },
})

// move: (Aufgabe, neuer Status) nach Drag & Drop im Kanban
const emit = defineEmits(['open', 'toggle-group', 'move'])

const byState = computed(() => groupTasksByState(props.tasks))
const tasksSortedByState = computed(() => TASK_STATES.flatMap(s => byState.value[s.value]))

let draggedTask = null

function onDragStart(event, task) {
  draggedTask = task
  event.dataTransfer.effectAllowed = 'move'
}

function onDrop(targetState) {
  if (draggedTask && (draggedTask.State || 'open') !== targetState) {
    emit('move', draggedTask, targetState)
  }
  draggedTask = null
}
</script>
