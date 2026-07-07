<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="task-delete-modal" @cancel.prevent="close">
      <div class="task-delete-modal_content" @click.stop>

        <div class="task-delete-modal_header">
          <h2 class="hl2 task-delete-modal_title">Aufgabe löschen</h2>
          <button class="button--close" aria-label="Schließen" @click="close">✕</button>
        </div>

        <div class="task-delete-modal_body">
          <p>Möchtest du <strong>{{ task.Title }}</strong> wirklich löschen?</p>

          <p v-if="subtaskCount > 0" class="task-delete-modal_subtask-note">
            Diese Aufgabe hat {{ subtaskCount }} Unteraufgabe{{ subtaskCount !== 1 ? 'n' : '' }}. Was soll damit passieren?
          </p>

          <div v-if="error" class="task-delete-modal_error">{{ error }}</div>

          <div class="task-delete-modal_actions">
            <button type="button" class="button" @click="close" :disabled="deleting">Abbrechen</button>

            <template v-if="subtaskCount > 0">
              <button type="button" class="button button--secondary" :disabled="deleting" @click="confirmDelete('promote')">
                {{ deleting ? 'Löschen…' : 'Unteraufgaben eigenständig machen' }}
              </button>
              <button type="button" class="button button--danger" :disabled="deleting" @click="confirmDelete('delete')">
                {{ deleting ? 'Löschen…' : 'Alles löschen' }}
              </button>
            </template>
            <button v-else type="button" class="button button--danger" :disabled="deleting" @click="confirmDelete('promote')">
              {{ deleting ? 'Löschen…' : 'Löschen' }}
            </button>
          </div>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTasksStore } from '@stores/tasks'

const props = defineProps({
  task: { type: Object, required: true },
})
const emit = defineEmits(['deleted'])
const store = useTasksStore()

const dialogEl = ref(null)
const deleting = ref(false)
const error = ref(null)

const subtaskCount = computed(() => props.task.SubTasks?.length || 0)

function open() {
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function confirmDelete(subtasksMode) {
  deleting.value = true
  error.value = null

  try {
    const response = await store.deleteTask(props.task.ID, subtasksMode)
    if (response.success) {
      emit('deleted')
      close()
    } else {
      error.value = response.error || 'Fehler beim Löschen der Aufgabe.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    deleting.value = false
  }
}

defineExpose({ open, close })
</script>
