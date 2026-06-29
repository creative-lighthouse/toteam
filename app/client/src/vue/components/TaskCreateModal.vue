<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="task-create-modal" @cancel.prevent="close">
      <div class="task-create-modal_content" @click.stop>

        <div class="task-create-modal_header">
          <h2 class="hl2 task-create-modal_title">Neue Aufgabe</h2>
          <button class="button button--close" aria-label="Schließen" @click="close">✕</button>
        </div>

        <form class="task-create-modal_body" @submit.prevent="submit">

          <div class="form-field">
            <label class="form-label">Organisation</label>
            <div class="multiselect-group">
              <label v-for="org in store.organizations" :key="org.ID" class="checkbox-label">
                <input type="radio" :value="org.ID" v-model="form.OrganizationID" :aria-label="org.Title" />
                {{ org.Title }}
              </label>
            </div>
          </div>

          <div class="form-field">
            <label class="form-label" for="task-title">Titel *</label>
            <input
              id="task-title"
              v-model="form.Title"
              type="text"
              class="input"
              placeholder="Aufgabentitel"
              required
              autofocus
            />
          </div>

          <div class="form-field">
            <label class="form-label" for="task-description">Beschreibung</label>
            <textarea
              id="task-description"
              v-model="form.Description"
              class="input"
              rows="3"
              placeholder="Optionale Beschreibung…"
            />
          </div>

          <div class="form-field-row">
            <div class="form-field">
              <label class="form-label" for="task-state">Status</label>
              <select id="task-state" v-model="form.State" class="input">
                <option v-for="s in store.STATES" :key="s.value" :value="s.value">
                  {{ s.label }}
                </option>
              </select>
            </div>

            <div class="form-field">
              <label class="form-label" for="task-deadline">Fälligkeitsdatum</label>
              <input
                id="task-deadline"
                v-model="form.Deadline"
                type="date"
                class="input"
              />
            </div>
          </div>

          <div v-if="error" class="task-create-modal_error">
            {{ error }}
          </div>

          <div class="task-create-modal_actions">
            <button type="button" class="button" @click="close" :disabled="saving">
              Abbrechen
            </button>
            <button type="submit" class="button button--primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
              {{ saving ? 'Speichern…' : 'Erstellen' }}
            </button>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useTasksStore } from '@stores/tasks'

const emit = defineEmits(['created'])
const store = useTasksStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)

const defaultForm = () => ({
  Title: '',
  Description: '',
  OrganizationID: store.filterOrganization?.ID ?? (store.organizations.length === 1 ? store.organizations[0].ID : 0),
  State: 'open',
  Deadline: '',
})

const form = reactive(defaultForm())

function open() {
  Object.assign(form, defaultForm())
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationID) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      OrganizationID: form.OrganizationID ? parseInt(form.OrganizationID) : 0,
      State: form.State,
      Deadline: form.Deadline || null,
    }

    const response = await store.createTask(payload)

    if (response.success) {
      emit('created', response.data.task)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erstellen der Aufgabe.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
