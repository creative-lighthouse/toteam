<template>
  <div class="section section--TasksPage">
    <div class="section_content">

      <!-- Toolbar: View toggle + Filters + Search -->
      <div class="tasks-toolbar">
        <div class="tasks-toolbar_filters">
          <!-- Search -->
          <input
            type="search"
            class="tasks-toolbar_search input"
            placeholder="Aufgaben suchen…"
            :value="store.filterSearch"
            @input="store.setSearchFilter($event.target.value)"
          />

          <!-- Organisation filter -->
          <select
            class="tasks-toolbar_select input"
            :value="store.filterOrganization?.ID ?? ''"
            @change="onOrgChange($event.target.value)"
          >
            <option value="">Alle Organisationen</option>
            <option
              v-for="org in store.organizations"
              :key="org.ID"
              :value="org.ID"
            >{{ org.Title }}</option>
          </select>

          <!-- State filter -->
          <select
            class="tasks-toolbar_select input"
            :value="store.filterState ?? ''"
            @change="store.setStateFilter($event.target.value || null)"
          >
            <option value="">Alle Status</option>
            <option v-for="s in store.STATES" :key="s.value" :value="s.value">
              {{ s.label }}
            </option>
          </select>

          <!-- Filter by person (owner or supporter) -->
          <TaskPersonFilter />

          <!-- View mode toggle (desktop/tablet only) -->
          <div v-if="!isMobile" class="tasks-view-toggle">
            <button
              class="tasks-view-toggle_btn"
              :class="{ 'tasks-view-toggle_btn--active': viewMode === 'list' }"
              @click="viewMode = 'list'"
              title="Listenansicht"
              aria-label="Listenansicht"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
            <button
              class="tasks-view-toggle_btn"
              :class="{ 'tasks-view-toggle_btn--active': viewMode === 'kanban' }"
              @click="viewMode = 'kanban'"
              title="Kanban-Ansicht"
              aria-label="Kanban-Ansicht"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="7" rx="1"/></svg>
            </button>
          </div>
        </div>

        <!-- Add task (desktop/tablet only — mobile uses the floating action button) -->
        <AppButton v-if="!isMobile" variant="primary" class="tasks-toolbar_actions" @click="createModal?.open()">
          <span class="icon-mask" :style="addTaskIconStyle"></span>
          Neue Aufgabe
        </AppButton>
      </div>

      <!-- Loading -->
      <div v-if="store.loading" class="section_infobox">
        <p>Lade Aufgaben…</p>
      </div>

      <!-- Error -->
      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.refresh()">Erneut versuchen</AppButton>
      </div>

      <template v-else>
        <!-- Empty state -->
        <div v-if="store.filteredTasks.length === 0" class="section_infobox">
          <p>Keine Aufgaben gefunden.</p>
        </div>

        <!-- LIST VIEW — grouped by status, each group collapsible -->
        <div v-else-if="effectiveViewMode === 'list'" class="tasks-list">
          <div v-for="col in store.STATES" :key="col.value" class="tasks-list-group">
            <button
              type="button"
              class="tasks-list-group_header"
              :aria-expanded="!store.collapsedGroups.has(col.value)"
              @click="store.toggleGroupCollapsed(col.value)"
            >
              <svg class="tasks-list-group_chevron" :class="{ 'tasks-list-group_chevron--collapsed': store.collapsedGroups.has(col.value) }" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              <span class="tasks-list-group_title">{{ col.label }}</span>
              <span class="tasks-list-group_count">{{ store.tasksByState[col.value].length }}</span>
            </button>
            <div v-show="!store.collapsedGroups.has(col.value)" class="tasks-list-group_body">
              <TaskCard
                v-for="task in store.tasksByState[col.value]"
                :key="task.ID"
                :task="task"
                @click="openTask"
              />
            </div>
          </div>
        </div>

        <!-- KANBAN VIEW -->
        <div v-else class="tasks-kanban">
          <div
            v-for="col in store.STATES"
            :key="col.value"
            class="tasks-kanban_column"
            @dragover.prevent
            @drop="onDrop($event, col.value)"
          >
            <div class="tasks-kanban_column-header">
              <span class="tasks-kanban_column-title">{{ col.label }}</span>
              <span class="tasks-kanban_column-count">{{ store.tasksByState[col.value].length }}</span>
            </div>
            <div class="tasks-kanban_cards">
              <TaskCard
                v-for="task in store.tasksByState[col.value]"
                :key="task.ID"
                :task="task"
                draggable="true"
                @dragstart="onDragStart($event, task)"
                @click="openTask"
              />
            </div>
          </div>
        </div>
      </template>

    </div>

    <!-- Floating action button (mobile only) -->
    <button v-if="isMobile" class="tasks-fab" title="Neue Aufgabe" @click="createModal?.open()">
      + Neue Aufgabe
    </button>

    <TaskCreateModal ref="createModal" @created="onTaskCreated" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTasksStore } from '@stores/tasks'
import { usePageHeaderStore } from '@stores/pageHeader'
import { getCookie, setCookie } from '@utils/cookies'
import TaskCard from '@components/TaskCard.vue'
import TaskCreateModal from '@components/TaskCreateModal.vue'
import TaskPersonFilter from '@components/TaskPersonFilter.vue'
import AppButton from '@components/AppButton.vue'
import AddTaskIcon from '../../../icons/actions/action_addtask.svg'

const addTaskIconStyle = { maskImage: `url("${AddTaskIcon}")`, WebkitMaskImage: `url("${AddTaskIcon}")` }
const VIEW_MODE_COOKIE = 'toteam_tasks_view_mode'

const router = useRouter()
const store = useTasksStore()
usePageHeaderStore().setHeader('Aufgaben', 'Alle Aufgaben deiner Organisationen.')

const viewMode = ref(getCookie(VIEW_MODE_COOKIE) === 'kanban' ? 'kanban' : 'list')
watch(viewMode, (mode) => setCookie(VIEW_MODE_COOKIE, mode))
const createModal = ref(null)
let draggedTaskId = null

// Mobile only ever shows the list view — mirrors the `max-medium` (700px) CSS breakpoint
const mobileQuery = window.matchMedia('(max-width: 700px)')
const isMobile = ref(mobileQuery.matches)
const effectiveViewMode = computed(() => isMobile.value ? 'list' : viewMode.value)

function onMobileQueryChange(e) {
  isMobile.value = e.matches
}

function openTask(task) {
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
}

function onTaskCreated(task) {
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
}

function onOrgChange(value) {
  if (!value) {
    store.setOrganizationFilter(null)
    return
  }
  const org = store.organizations.find(o => o.ID === parseInt(value))
  store.setOrganizationFilter(org ?? null)
}

function onDragStart(event, task) {
  draggedTaskId = task.ID
  event.dataTransfer.effectAllowed = 'move'
}

async function onDrop(event, targetState) {
  if (!draggedTaskId) return
  const task = store.getTaskById(draggedTaskId)
  if (task && task.State !== targetState) {
    await store.updateTaskState(draggedTaskId, targetState)
  }
  draggedTaskId = null
}

onMounted(async () => {
  mobileQuery.addEventListener('change', onMobileQueryChange)
  await store.fetchTasks()
})

onUnmounted(() => {
  mobileQuery.removeEventListener('change', onMobileQueryChange)
})
</script>
