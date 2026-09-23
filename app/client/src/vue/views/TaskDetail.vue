<template>
  <div class="section section--TaskDetailPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Aufgabe…</p>
      </div>

      <div v-else-if="!task" class="section_infobox">
        <p>Aufgabe nicht gefunden.</p>
      </div>

      <div v-else class="task-detail">

        <!-- Parent link -->
        <AppButton v-if="task.Parent" size="small" variant="secondary" @click="goToParent">
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" fill="currentColor"><path d="M7.354 3.146a.5.5 0 010 .708L4.207 7h8.043a.5.5 0 010 1H4.207l3.147 3.146a.5.5 0 01-.708.708l-4-4a.5.5 0 010-.708l4-4a.5.5 0 01.708 0z"/></svg>
          Übergeordnete Aufgabe: {{ task.Parent.Title }}
        </AppButton>

        <!-- Header row -->
        <div class="task-detail_header">
          <div class="task-detail_meta">
            <span v-if="task.Organization" class="task-detail_org">
              <AppOrgLogo
                :src="task.Organization.LogoURL"
                :alt="task.Organization.Title"
                :size="22"
              />
              {{ task.Organization.Title }}
            </span>
            <select
              class="task-card_state-badge task-detail_status-select"
              :class="`task-card_state-badge--${task.State || 'open'}`"
              :value="task.State || 'open'"
              :disabled="changingStatus"
              @change="changeStatus($event.target.value)"
            >
              <option v-for="s in store.STATES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>

          <div class="task-detail_header-actions">
            <!-- Share link button -->
            <AppIconButton variant="neutral" :aria-label="copied ? 'Kopiert!' : 'Link teilen'" :title="copied ? 'Kopiert!' : 'Link teilen'" @click="copyShareLink">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </AppIconButton>

            <!-- Edit button -->
            <AppIconButton v-if="task.CanEdit" variant="primary" aria-label="Aufgabe bearbeiten" title="Aufgabe bearbeiten" @click="editModal?.open()">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>

            <!-- Delete button -->
            <AppIconButton v-if="task.CanDelete" variant="danger" aria-label="Aufgabe löschen" title="Aufgabe löschen" @click="deleteModal?.open()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </AppIconButton>
          </div>
        </div>

        <h2 class="hl2 task-detail_title">{{ task.Title }}</h2>

        <p v-if="task.DeadlineNice && task.State !== 'finished'" class="task-detail_deadline" :class="{ 'task-card_deadline--overdue': isOverdue }">
          Fällig: {{ task.DeadlineNice }}
        </p>

        <!-- Description -->
        <div v-if="task.Description" class="task-detail_description">
          <p>{{ task.Description }}</p>
        </div>

        <!-- People -->
        <div class="task-detail_people">
          <div v-if="task.Owner" class="task-detail_person task-detail_person--editable">
            <AppAvatar :src="task.Owner.Avatar" :alt="task.Owner.Name" img-class="task-detail_avatar" />
            <div>
              <span class="task-detail_person-role">Verantwortlich</span>
              <span class="task-detail_person-name">{{ task.Owner.Name }}</span>
            </div>
            <select
              class="task-detail_person-select task-detail_person-select--overlay"
              :value="task.Owner.ID"
              :disabled="loadingOrgMembers || changingOwner"
              aria-label="Verantwortlichen ändern"
              @change="changeOwner($event.target.value)"
            >
              <option v-for="m in orgMembers" :key="m.ID" :value="m.ID">{{ m.Name }}</option>
            </select>
          </div>

          <div v-for="s in task.Supporters" :key="s.ID" class="task-detail_person task-detail_person--editable">
            <AppAvatar :src="s.Avatar" :alt="s.Name" img-class="task-detail_avatar" />
            <div>
              <span class="task-detail_person-role">Unterstützer</span>
              <span class="task-detail_person-name">{{ s.Name }}</span>
            </div>
            <select
              class="task-detail_person-select task-detail_person-select--overlay"
              :value="s.ID"
              :disabled="loadingOrgMembers || changingSupporterId === s.ID"
              :aria-label="`${s.Name} austauschen oder entfernen`"
              @change="changeSupporter(s.ID, $event.target.value)"
            >
              <option value="">— Entfernen —</option>
              <option v-for="m in supporterOptionsFor(s.ID)" :key="m.ID" :value="m.ID">{{ m.Name }}</option>
            </select>
          </div>

          <AppButton size="small" variant="secondary" @click="supportersModal?.open()">
            + Unterstützer hinzufügen
          </AppButton>
        </div>

        <!-- Rooms -->
        <div class="task-detail_rooms">
          <div class="task-detail_rooms-header">
            <h3 class="hl3">Räume</h3>
            <AppIconButton variant="neutral" aria-label="Raum hinzufügen" title="Raum hinzufügen" @click="roomsModal?.open()">
              <span class="icon-mask" :style="addRoomIconStyle" />
            </AppIconButton>
          </div>

          <div v-if="task.Rooms?.length" class="task-detail_room-chips">
            <span v-for="r in task.Rooms" :key="r.ID" class="task-detail_room-chip">{{ r.Title }}</span>
          </div>
          <div v-else class="section_infobox"><p>Diese Aufgabe ist noch keinem Raum zugeordnet.</p></div>
        </div>

        <!-- Subtasks -->
        <div class="task-detail_subtasks">
          <div class="task-detail_subtasks-header">
            <h3 class="hl3">Unteraufgaben</h3>
            <AppIconButton variant="neutral" aria-label="Unteraufgabe hinzufügen" title="Unteraufgabe hinzufügen" @click="subtaskModal?.open()">
              <span class="icon-mask" :style="addTaskIconStyle" />
            </AppIconButton>
          </div>

          <TaskProgressBar :subtasks="task.SubTasks || []" />

          <div v-if="task.SubTasks?.length" class="tasks-list">
            <TaskCard
              v-for="sub in task.SubTasks"
              :key="sub.ID"
              :task="sub"
              @click="openTask"
            />
          </div>
          <div v-else class="section_infobox"><p>Noch keine Unteraufgaben.</p></div>
        </div>

      </div>
    </div>

    <TaskSupportersModal
      v-if="task"
      ref="supportersModal"
      :task-id="task.ID"
      :organization-id="task.Organization?.ID"
      :owner-id="task.Owner?.ID"
      :current-supporter-ids="(task.Supporters || []).map(s => s.ID)"
      @saved="onSupportersSaved"
    />
    <TaskRoomsModal
      v-if="task"
      ref="roomsModal"
      :task-id="task.ID"
      :organization-id="task.Organization?.ID"
      :current-room-ids="(task.Rooms || []).map(r => r.ID)"
      @saved="onRoomsSaved"
    />
    <TaskCreateModal
      v-if="task"
      ref="subtaskModal"
      :parent-task="task"
      @created="onSubtaskCreated"
    />
    <TaskDeleteModal
      v-if="task"
      ref="deleteModal"
      :task="task"
      @deleted="onTaskDeleted"
    />
    <TaskEditModal
      v-if="task"
      ref="editModal"
      :task="task"
      @saved="onTaskSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTasksStore } from '@stores/tasks'
import { usePageHeaderStore } from '@stores/pageHeader'
import TaskCard from '@components/TaskCard.vue'
import AppAvatar from '@components/AppAvatar.vue'
import AppOrgLogo from '@components/AppOrgLogo.vue'
import TaskSupportersModal from '@components/TaskSupportersModal.vue'
import TaskRoomsModal from '@components/TaskRoomsModal.vue'
import TaskCreateModal from '@components/TaskCreateModal.vue'
import TaskProgressBar from '@components/TaskProgressBar.vue'
import TaskDeleteModal from '@components/TaskDeleteModal.vue'
import TaskEditModal from '@components/TaskEditModal.vue'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import actionAddRoom from '../../../icons/actions/action_addroom.svg'
import actionAddTask from '../../../icons/actions/action_addtask.svg'

const addRoomIconStyle = { maskImage: `url("${actionAddRoom}")`, WebkitMaskImage: `url("${actionAddRoom}")` }
const addTaskIconStyle = { maskImage: `url("${actionAddTask}")`, WebkitMaskImage: `url("${actionAddTask}")` }

const route = useRoute()
const router = useRouter()
const store = useTasksStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Aufgabe')

const task = ref(null)
const loading = ref(true)
const copied = ref(false)
const changingStatus = ref(false)
const changingOwner = ref(false)
const changingSupporterId = ref(null)
const orgMembers = ref([])
const loadingOrgMembers = ref(false)
const supportersModal = ref(null)
const roomsModal = ref(null)
const subtaskModal = ref(null)
const deleteModal = ref(null)
const editModal = ref(null)

const isOverdue = computed(() => {
  if (!task.value?.Deadline || task.value?.State === 'finished') return false
  return new Date(task.value.Deadline) < new Date()
})

watch(task, (val) => {
  pageHeaderStore.setTitle(val?.Title ?? 'Aufgabe')
})

async function loadOrgMembers(orgId) {
  if (!orgId) {
    orgMembers.value = []
    return
  }
  loadingOrgMembers.value = true
  try {
    orgMembers.value = await store.fetchOrgMembers(orgId)
  } finally {
    loadingOrgMembers.value = false
  }
}

async function changeStatus(newState) {
  if (!task.value || newState === task.value.State) return
  changingStatus.value = true
  try {
    const response = await store.updateTaskState(task.value.ID, newState)
    if (response.success) {
      task.value = response.data.task
    } else {
      alert('Fehler: ' + (response.error || 'Status konnte nicht geändert werden.'))
    }
  } finally {
    changingStatus.value = false
  }
}

async function changeOwner(newOwnerId) {
  if (!task.value) return
  const id = parseInt(newOwnerId)
  if (id === task.value.Owner?.ID) return
  changingOwner.value = true
  try {
    const response = await store.updateTask(task.value.ID, { OwnerID: id })
    if (response.success) {
      task.value = response.data.task
    } else {
      alert('Fehler: ' + (response.error || 'Verantwortlicher konnte nicht geändert werden.'))
    }
  } finally {
    changingOwner.value = false
  }
}

// Org members eligible to replace a given supporter: not the owner, not already
// another supporter (the supporter being edited is excluded from that check)
function supporterOptionsFor(supporterId) {
  const takenIds = new Set([
    task.value?.Owner?.ID,
    ...(task.value?.Supporters || []).filter(s => s.ID !== supporterId).map(s => s.ID),
  ])
  return orgMembers.value.filter(m => !takenIds.has(m.ID))
}

async function changeSupporter(oldSupporterId, newValue) {
  if (!task.value) return

  const currentIds = (task.value.Supporters || []).map(s => s.ID)
  const newIds = newValue === ''
    ? currentIds.filter(id => id !== oldSupporterId)
    : currentIds.map(id => id === oldSupporterId ? parseInt(newValue) : id)

  changingSupporterId.value = oldSupporterId
  try {
    const response = await store.updateTask(task.value.ID, { SupporterIDs: newIds })
    if (response.success) {
      task.value = response.data.task
    } else {
      alert('Fehler: ' + (response.error || 'Unterstützer konnte nicht geändert werden.'))
    }
  } finally {
    changingSupporterId.value = null
  }
}

function onSupportersSaved(updatedTask) {
  task.value = updatedTask
}

function onRoomsSaved(updatedTask) {
  task.value = updatedTask
}

function onTaskSaved(updatedTask) {
  task.value = updatedTask
}

function onSubtaskCreated(newSubtask) {
  if (!task.value) return
  task.value = { ...task.value, SubTasks: [...(task.value.SubTasks || []), newSubtask] }
}

function onTaskDeleted() {
  if (task.value?.Parent) {
    router.push({ name: 'TaskDetail', params: { hash: task.value.Parent.Hash } })
  } else {
    router.push({ name: 'Tasks' })
  }
}

async function loadTask(hash) {
  loading.value = true
  let found = null

  // Try from store cache first
  const cached = store.tasks.find(t => t.Hash === hash)
  if (cached) {
    found = cached
  } else {
    if (store.tasks.length === 0) {
      await store.fetchTasks()
      found = store.tasks.find(t => t.Hash === hash) ?? null
    }
    if (!found) {
      found = await store.fetchTaskByHash(hash)
    }
  }

  task.value = found
  loading.value = false

  if (found?.Organization?.ID) {
    loadOrgMembers(found.Organization.ID)
  }
}

function openTask(t) {
  router.push({ name: 'TaskDetail', params: { hash: t.Hash } })
}

function goToParent() {
  if (task.value?.Parent) {
    router.push({ name: 'TaskDetail', params: { hash: task.value.Parent.Hash } })
  }
}

async function copyShareLink() {
  const url = window.location.href
  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    prompt('Link kopieren:', url)
  }
}

onMounted(() => loadTask(route.params.hash))

watch(() => route.params.hash, (hash) => {
  if (hash) loadTask(hash)
})
</script>
