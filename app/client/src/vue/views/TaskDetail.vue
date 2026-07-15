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
              <img
                v-if="task.Organization.LogoURL"
                :src="task.Organization.LogoURL"
                :alt="task.Organization.Title"
                class="task-detail_org-logo"
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
            <AppButton variant="secondary" :title="copied ? 'Kopiert!' : 'Link teilen'" @click="copyShareLink">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              {{ copied ? 'Kopiert!' : 'Teilen' }}
            </AppButton>

            <!-- Delete button -->
            <AppButton variant="danger" title="Aufgabe löschen" @click="deleteModal?.open()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Löschen
            </AppButton>
          </div>
        </div>

        <h2 class="hl2 task-detail_title">{{ task.Title }}</h2>

        <p v-if="task.DeadlineNice" class="task-detail_deadline" :class="{ 'task-card_deadline--overdue': isOverdue }">
          Fällig: {{ task.DeadlineNice }}
        </p>

        <!-- Description -->
        <div v-if="task.Description" class="task-detail_description">
          <p>{{ task.Description }}</p>
        </div>

        <!-- People -->
        <div class="task-detail_people">
          <div v-if="task.Owner" class="task-detail_person task-detail_person--editable">
            <img :src="task.Owner.Avatar" :alt="task.Owner.Name" class="task-detail_avatar" />
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
            <img :src="s.Avatar" :alt="s.Name" class="task-detail_avatar" />
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

        <!-- Subtasks -->
        <div class="task-detail_subtasks">
          <div class="task-detail_subtasks-header">
            <h3 class="hl3">Unteraufgaben</h3>
            <AppButton size="small" variant="secondary" @click="subtaskModal?.open()">+ Unteraufgabe</AppButton>
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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTasksStore } from '@stores/tasks'
import { usePageHeaderStore } from '@stores/pageHeader'
import TaskCard from '@components/TaskCard.vue'
import TaskSupportersModal from '@components/TaskSupportersModal.vue'
import TaskCreateModal from '@components/TaskCreateModal.vue'
import TaskProgressBar from '@components/TaskProgressBar.vue'
import TaskDeleteModal from '@components/TaskDeleteModal.vue'
import AppButton from '@components/AppButton.vue'

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
const subtaskModal = ref(null)
const deleteModal = ref(null)

const isOverdue = computed(() => {
  if (!task.value?.Deadline) return false
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
