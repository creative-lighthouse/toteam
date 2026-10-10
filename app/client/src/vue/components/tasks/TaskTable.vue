<template>
  <!-- Tabellenansicht der Aufgaben: Status, Titel, Verantwortliche, Unteraufgaben, Organisation.
       Ganze Zeile klickbar (bzw. per Enter/Leertaste), öffnet die Aufgabe. -->
  <div class="task-table">
    <table>
      <thead>
        <tr>
          <th scope="col">Status</th>
          <th scope="col">Titel</th>
          <th scope="col">Verantwortlich</th>
          <th scope="col" class="task-table_col-count">Unteraufgaben</th>
          <th scope="col" title="Organisation">Org</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="task in tasks"
          :key="task.ID"
          class="task-table_row"
          tabindex="0"
          @click="emit('open', task)"
          @keydown.enter.prevent="emit('open', task)"
          @keydown.space.prevent="emit('open', task)"
        >
          <td>
            <span class="task-card_state-badge" :class="`task-card_state-badge--${task.State || 'open'}`">
              {{ stateLabel(task) }}
            </span>
          </td>
          <td class="task-table_title">
            {{ task.Title }}
            <span v-if="task.DeadlineNice && task.State !== 'finished'" class="task-table_deadline" :class="{ 'task-table_deadline--overdue': isOverdue(task) }">
              {{ task.DeadlineNice }}<template v-if="isOverdue(task)"> · überfällig</template>
            </span>
          </td>
          <td>
            <div v-if="task.Owner || task.Supporters?.length" class="task-table_people">
              <span v-if="task.Owner" class="task-table_owner">
                <AppAvatar :src="task.Owner.Avatar" :alt="task.Owner.Name" :title="task.Owner.Name" img-class="task-table_avatar" />
                <span>{{ task.Owner.Name }}</span>
              </span>
              <span v-if="task.Supporters?.length" class="task-table_supporters" :title="task.Supporters.map(s => s.Name).join(', ')">
                <AppAvatar
                  v-for="s in task.Supporters.slice(0, 3)"
                  :key="s.ID"
                  :src="s.Avatar"
                  :alt="s.Name"
                  img-class="task-table_avatar"
                />
                <span v-if="task.Supporters.length > 3" class="task-table_avatar task-table_avatar--overflow">+{{ task.Supporters.length - 3 }}</span>
              </span>
            </div>
            <span v-else class="task-table_empty">–</span>
          </td>
          <td class="task-table_col-count">
            <template v-if="subtaskCount(task)">
              <template v-if="task.SubTasks?.length">{{ finishedSubtasks(task) }}/</template>{{ subtaskCount(task) }}
            </template>
            <span v-else class="task-table_empty">–</span>
          </td>
          <td>
            <AppOrgLogo
              v-if="task.Organization"
              :src="task.Organization.LogoURL"
              :alt="task.Organization.Title"
              :title="task.Organization.Title"
              :size="26"
            />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { TASK_STATES } from '@utils/taskFilters'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'

defineProps({
  tasks: { type: Array, required: true },
})

const emit = defineEmits(['open'])

function stateLabel(task) {
  return TASK_STATES.find(s => s.value === (task.State || 'open'))?.label ?? 'Offen'
}

function isOverdue(task) {
  if (!task.Deadline || task.State === 'finished') return false
  return new Date(task.Deadline) < new Date()
}

// Unteraufgaben von Unteraufgaben kommen nur als Anzahl (SubTaskCount) mit
function subtaskCount(task) {
  return task.SubTasks?.length || task.SubTaskCount || 0
}

function finishedSubtasks(task) {
  return task.SubTasks.filter(s => s.State === 'finished').length
}
</script>
