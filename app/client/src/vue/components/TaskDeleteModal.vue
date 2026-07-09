<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="task-delete-modal" @cancel.prevent="close">
      <div class="task-delete-modal_content" @click.stop>

        <div class="task-delete-modal_header">
          <h2 class="hl2 task-delete-modal_title">Aufgabe löschen</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div class="task-delete-modal_body">
          <p>Möchtest du <strong>{{ task.Title }}</strong> wirklich löschen?</p>

          <p v-if="subtaskCount > 0" class="task-delete-modal_subtask-note">
            Diese Aufgabe hat {{ subtaskCount }} Unteraufgabe{{ subtaskCount !== 1 ? 'n' : '' }}. Was soll damit passieren?
          </p>

          <div v-if="error" class="task-delete-modal_error">{{ error }}</div>

          <div class="task-delete-modal_actions">
            <AppButton variant="secondary" :disabled="deleting" @click="close">Abbrechen</AppButton>

            <template v-if="subtaskCount > 0">
              <AppButton variant="secondary" :disabled="deleting" @click="confirmDelete('promote')">
                {{ deleting ? 'Löschen…' : 'Unteraufgaben eigenständig machen' }}
              </AppButton>
              <AppButton variant="danger" :disabled="deleting" @click="confirmDelete('delete')">
                {{ deleting ? 'Löschen…' : 'Alles löschen' }}
              </AppButton>
            </template>
            <AppButton v-else variant="danger" :disabled="deleting" @click="confirmDelete('promote')">
              {{ deleting ? 'Löschen…' : 'Löschen' }}
            </AppButton>
          </div>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

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
