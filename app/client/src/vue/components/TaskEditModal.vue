<template>
  <AppModal ref="modal" class="task-create-modal" title="Aufgabe bearbeiten" @close="close">
    <form id="task-edit-form" @submit.prevent="submit">

      <div class="form-field">
        <label class="form-label" for="task-edit-title">Titel *</label>
        <input
          id="task-edit-title"
          v-model="form.Title"
          type="text"
          class="input"
          placeholder="Aufgabentitel"
          required
          autofocus
        />
      </div>

      <div class="form-field">
        <label class="form-label" for="task-edit-description">Beschreibung</label>
        <textarea
          id="task-edit-description"
          v-model="form.Description"
          class="input"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </div>

      <div class="form-field">
        <label class="form-label" for="task-edit-deadline">Fälligkeitsdatum</label>
        <input
          id="task-edit-deadline"
          v-model="form.Deadline"
          type="date"
          class="input"
        />
      </div>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="task-edit-form" variant="primary" :disabled="saving || !form.Title.trim()">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const props = defineProps({
  task: { type: Object, required: true },
})
const emit = defineEmits(['saved'])
const store = useTasksStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

const form = reactive({
  Title: '',
  Description: '',
  Deadline: '',
})

function open() {
  form.Title = props.task.Title || ''
  form.Description = props.task.Description || ''
  form.Deadline = props.task.Deadline ? props.task.Deadline.slice(0, 10) : ''
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim()) return

  saving.value = true
  error.value = null

  try {
    const response = await store.updateTask(props.task.ID, {
      Title: form.Title.trim(),
      Description: form.Description,
      Deadline: form.Deadline,
    })

    if (response.success) {
      emit('saved', response.data.task)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Aufgabe.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
