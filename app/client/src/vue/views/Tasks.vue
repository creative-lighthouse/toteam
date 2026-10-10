<template>
  <div class="section section--TasksPage">
    <div class="section_content">

      <!-- Toolbar: Suche + "Neue Aufgabe", darunter die Filter -->
      <AppSearchBar
        class="tasks-toolbar"
        :model-value="store.filterSearch"
        placeholder="Aufgaben suchen…"
        @update:model-value="store.setSearchFilter"
      >
        <!-- Add task (desktop/tablet only — mobile uses the floating action button) -->
        <template v-if="!isMobile" #actions>
          <AppButton variant="primary" @click="createModal?.open()">
            <span class="icon-mask" :style="addTaskIconStyle"></span>
            Neue Aufgabe
          </AppButton>
        </template>

        <template #filters>
          <TaskFilterControls
            :organizations="store.organizations"
            :organization-id="store.filterOrganization?.ID ?? null"
            :state="store.filterState"
            :person-id="store.filterPersonId"
            v-model:view-mode="viewMode"
            @update:organization-id="onOrgChange"
            @update:state="store.setStateFilter"
            @update:person-id="store.setPersonFilter"
          />
        </template>
      </AppSearchBar>

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

        <TaskBoard
          v-else
          fill
          :tasks="store.filteredTasks"
          :view-mode="effectiveViewMode"
          :collapsed-groups="store.collapsedGroups"
          @toggle-group="store.toggleGroupCollapsed"
          @open="openTask"
          @move="onMove"
        />
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
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTasksStore } from '@stores/tasks'
import { usePageHeaderStore } from '@stores/pageHeader'
import { getCookie, setCookie } from '@utils/cookies'
import { useMediaQuery } from '@utils/useMediaQuery'
import TaskBoard from '@components/tasks/TaskBoard.vue'
import TaskCreateModal from '@components/tasks/TaskCreateModal.vue'
import TaskFilterControls from '@components/tasks/TaskFilterControls.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import AddTaskIcon from '../../../icons/actions/action_addtask.svg'

const addTaskIconStyle = { maskImage: `url("${AddTaskIcon}")`, WebkitMaskImage: `url("${AddTaskIcon}")` }
const VIEW_MODE_COOKIE = 'toteam_tasks_view_mode'

const router = useRouter()
const store = useTasksStore()
usePageHeaderStore().setHeader('Aufgaben', 'Alle Aufgaben deiner Organisationen.')

const viewMode = ref(['kanban', 'table'].includes(getCookie(VIEW_MODE_COOKIE)) ? getCookie(VIEW_MODE_COOKIE) : 'list')
watch(viewMode, (mode) => setCookie(VIEW_MODE_COOKIE, mode))
const createModal = ref(null)

// Mobile only ever shows the list view — mirrors the `max-medium` (700px) CSS breakpoint
const isMobile = useMediaQuery('(max-width: 700px)')
const effectiveViewMode = computed(() => isMobile.value ? 'list' : viewMode.value)

function openTask(task) {
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
}

function onTaskCreated(task) {
  router.push({ name: 'TaskDetail', params: { hash: task.Hash } })
}

function onOrgChange(id) {
  store.setOrganizationFilter(id ? store.organizations.find(o => o.ID === id) ?? null : null)
}

async function onMove(task, targetState) {
  await store.updateTaskState(task.ID, targetState)
}

onMounted(async () => {
  await store.fetchTasks()
})
</script>
