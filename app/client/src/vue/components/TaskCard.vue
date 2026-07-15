<template>
  <div
    class="task-card"
    :class="[`task-card--${task.State || 'open'}`, { 'task-card--has-subtasks': task.SubTasks?.length }]"
    @click="$emit('click', task)"
  >
    <div class="task-card_header">
      <span class="task-card_state-badge" :class="`task-card_state-badge--${task.State || 'open'}`">
        {{ stateLabel }}
      </span>
      <template v-if="task.Organization">
        <img
          v-if="task.Organization.LogoURL"
          :src="task.Organization.LogoURL"
          :alt="task.Organization.Title"
          :title="task.Organization.Title"
          class="task-card_org-logo"
        />
        <span v-else class="task-card_org-name" :title="task.Organization.Title">
          {{ task.Organization.Title }}
        </span>
      </template>
    </div>

    <h3 class="hl3 task-card_title">{{ task.Title }}</h3>

    <p v-if="task.Description" class="task-card_description">{{ truncatedDescription }}</p>

    <TaskProgressBar v-if="task.SubTasks?.length" class="task-card_progress" :subtasks="task.SubTasks" :show-legend="false" />

    <div class="task-card_footer">
      <div class="task-card_meta">
        <span v-if="task.DeadlineNice" class="task-card_deadline" :class="{ 'task-card_deadline--overdue': isOverdue }">
          {{ task.DeadlineNice }}
        </span>
        <span v-if="task.SubTasks?.length" class="task-card_subtasks-count">
          {{ task.SubTasks.length }} Unteraufgabe{{ task.SubTasks.length !== 1 ? 'n' : '' }}
        </span>
      </div>

      <div class="task-card_avatars">
        <img
          v-if="task.Owner"
          :src="task.Owner.Avatar"
          :alt="task.Owner.Name"
          :title="task.Owner.Name"
          class="task-card_avatar task-card_avatar--owner"
        />
        <img
          v-for="s in task.Supporters?.slice(0, 3)"
          :key="s.ID"
          :src="s.Avatar"
          :alt="s.Name"
          :title="s.Name"
          class="task-card_avatar"
        />
        <span v-if="(task.Supporters?.length ?? 0) > 3" class="task-card_avatar-overflow">
          +{{ task.Supporters.length - 3 }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TaskProgressBar from '@components/TaskProgressBar.vue'

const props = defineProps({
  task: {
    type: Object,
    required: true
  }
})

defineEmits(['click'])

const STATE_LABELS = {
  open:        'Offen',
  in_progress: 'In Bearbeitung',
  feedback:    'Feedback',
  finished:    'Abgeschlossen',
}

const stateLabel = computed(() => STATE_LABELS[props.task.State] || 'Offen')

const truncatedDescription = computed(() => {
  const desc = props.task.Description || ''
  return desc.length > 120 ? desc.slice(0, 120) + '…' : desc
})

const isOverdue = computed(() => {
  if (!props.task.Deadline) return false
  return new Date(props.task.Deadline) < new Date()
})
</script>
