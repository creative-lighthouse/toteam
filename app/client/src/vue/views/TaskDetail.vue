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
            <span class="task-card_state-badge" :class="`task-card_state-badge--${task.State || 'open'}`">
              {{ stateLabel }}
            </span>
          </div>

          <!-- Share link button -->
          <button class="button task-detail_share-btn" @click="copyShareLink" :title="copied ? 'Kopiert!' : 'Link teilen'">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            {{ copied ? 'Kopiert!' : 'Teilen' }}
          </button>
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
          <div v-if="task.Owner" class="task-detail_person">
            <img :src="task.Owner.Avatar" :alt="task.Owner.Name" class="task-detail_avatar" />
            <div>
              <span class="task-detail_person-role">Verantwortlich</span>
              <span class="task-detail_person-name">{{ task.Owner.Name }}</span>
            </div>
          </div>

          <div v-for="s in task.Supporters" :key="s.ID" class="task-detail_person">
            <img :src="s.Avatar" :alt="s.Name" class="task-detail_avatar" />
            <div>
              <span class="task-detail_person-role">Unterstützer</span>
              <span class="task-detail_person-name">{{ s.Name }}</span>
            </div>
          </div>
        </div>

        <!-- Subtasks -->
        <div v-if="task.SubTasks?.length" class="task-detail_subtasks">
          <h3 class="hl3">Unteraufgaben</h3>
          <div class="tasks-list">
            <TaskCard
              v-for="sub in task.SubTasks"
              :key="sub.ID"
              :task="sub"
              @click="openTask"
            />
          </div>
        </div>

        <!-- Parent link -->
        <div v-if="task.ParentID" class="task-detail_parent">
          <button class="button" @click="goToParent">Zur übergeordneten Aufgabe</button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTasksStore } from '@stores/tasks'
import { usePageHeaderStore } from '@stores/pageHeader'
import TaskCard from '@components/TaskCard.vue'

const route = useRoute()
const router = useRouter()
const store = useTasksStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Aufgabe')

const task = ref(null)
const loading = ref(true)
const copied = ref(false)

const STATE_LABELS = {
  open:        'Offen',
  in_progress: 'In Bearbeitung',
  feedback:    'Feedback',
  finished:    'Abgeschlossen',
}

const stateLabel = computed(() => STATE_LABELS[task.value?.State] || 'Offen')

const isOverdue = computed(() => {
  if (!task.value?.Deadline) return false
  return new Date(task.value.Deadline) < new Date()
})

watch(task, (val) => {
  pageHeaderStore.setTitle(val?.Title ?? 'Aufgabe')
})

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
}

function openTask(t) {
  router.push({ name: 'TaskDetail', params: { hash: t.Hash } })
}

async function goToParent() {
  const parent = store.getTaskById(task.value.ParentID)
  if (parent) {
    router.push({ name: 'TaskDetail', params: { hash: parent.Hash } })
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
