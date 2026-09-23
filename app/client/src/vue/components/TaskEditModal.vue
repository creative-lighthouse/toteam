<template>
  <AppModal ref="modal" class="task-create-modal" title="Aufgabe bearbeiten" @close="close">
    <form id="task-edit-form" class="modalform" @submit.prevent="submit">

      <label class="field">
        Titel *
        <input
          id="task-edit-title"
          v-model="form.Title"
          type="text"
          placeholder="Aufgabentitel"
          required
          autofocus
        />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="task-edit-description"
          v-model="form.Description"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </label>

      <DateTimeRangeField
        :model-value="deadlineField"
        @update:model-value="v => (deadlineField = v)"
        time="none"
        :show-end-date="false"
        :required="false"
        start-label-date="Fälligkeitsdatum"
        start-field-class="field field--3"
      />

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
import { ref, reactive, computed } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'
import DateTimeRangeField from '@components/DateTimeRangeField.vue'

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

// Adapter zwischen dem einzelnen "YYYY-MM-DD"-String und der
// { dateStart }-Form, die DateTimeRangeField per v-model erwartet/liefert.
const deadlineField = computed({
  get: () => ({ dateStart: form.Deadline }),
  set: (val) => { form.Deadline = val.dateStart },
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
