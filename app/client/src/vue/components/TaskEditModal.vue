<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="task-create-modal" @cancel.prevent="close">
      <div class="task-create-modal_content" @click.stop>

        <div class="task-create-modal_header">
          <h2 class="hl2 task-create-modal_title">Aufgabe bearbeiten</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="task-create-modal_body" @submit.prevent="submit">

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

          <div v-if="error" class="task-create-modal_error">
            {{ error }}
          </div>

          <div class="task-create-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">
              Abbrechen
            </AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !form.Title.trim()">
              {{ saving ? 'Speichern…' : 'Speichern' }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const props = defineProps({
  task: { type: Object, required: true },
})
const emit = defineEmits(['saved'])
const store = useTasksStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)

const form = reactive({
  Title: '',
  Description: '',
})

function open() {
  form.Title = props.task.Title || ''
  form.Description = props.task.Description || ''
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function submit() {
  if (!form.Title.trim()) return

  saving.value = true
  error.value = null

  try {
    const response = await store.updateTask(props.task.ID, {
      Title: form.Title.trim(),
      Description: form.Description,
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
