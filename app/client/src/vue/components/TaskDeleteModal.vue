<template>
  <AppModal ref="modal" class="task-delete-modal" title="Aufgabe löschen" @close="close">
    <p>Möchtest du <strong>{{ task.Title }}</strong> wirklich löschen?</p>

    <p v-if="subtaskCount > 0" class="task-delete-modal_subtask-note">
      Diese Aufgabe hat {{ subtaskCount }} Unteraufgabe{{ subtaskCount !== 1 ? 'n' : '' }}. Was soll damit passieren?
    </p>

    <div v-if="error" class="app-modal_error">{{ error }}</div>

    <template #actions>
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
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const props = defineProps({
  task: { type: Object, required: true },
})
const emit = defineEmits(['deleted'])
const store = useTasksStore()

const modal = ref(null)
const deleting = ref(false)
const error = ref(null)

const subtaskCount = computed(() => props.task.SubTasks?.length || 0)

function open() {
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
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
