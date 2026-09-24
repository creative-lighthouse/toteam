<template>
  <AppModal ref="modal" class="organization-create-modal" title="Neue Organisation" @close="close">
    <form id="organization-create-form" class="modalform" @submit.prevent="submit">

      <label class="field">
        Titel *
        <input
          id="org-title"
          v-model="form.Title"
          type="text"
          placeholder="Name der Organisation"
          required
          autofocus
        />
      </label>

      <label class="field">
        Benutzername
        <input
          id="org-username"
          :value="form.Username"
          type="text"
          placeholder="z. B. mein-verein"
          @input="onUsernameInput"
        />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="org-description"
          v-model="form.Description"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </label>

      <label class="field">
        Beitrittsmodus
        <select id="org-join-mode" v-model="form.JoinMode">
          <option value="open">Offen</option>
          <option value="application">Bewerbung erforderlich</option>
          <option value="invite_only">Nur auf Einladung</option>
          <option value="hidden">Versteckt</option>
        </select>
      </label>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="organization-create-form" variant="primary" :disabled="saving || !form.Title.trim()">
        {{ saving ? 'Speichern…' : 'Erstellen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const emit = defineEmits(['created'])
const store = useOrganizationsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

const defaultForm = () => ({
  Title: '',
  Username: '',
  Description: '',
  JoinMode: 'invite_only',
})

const form = reactive(defaultForm())

// Bereinigt die Eingabe live zu einem gültigen Benutzernamen (muss dem Backend-Regex
// ^[a-z0-9][a-z0-9._-]*$ entsprechen): Kleinschreibung, Leerzeichen -> Unterstrich,
// alle anderen unerlaubten Zeichen entfernt.
function sanitizeUsername(value) {
  return value
    .toLowerCase()
    .replace(/\s+/g, '_')
    .replace(/[^a-z0-9._-]/g, '')
    .replace(/^[^a-z0-9]+/, '')
}

function onUsernameInput(event) {
  form.Username = sanitizeUsername(event.target.value)
  event.target.value = form.Username
}

function open() {
  Object.assign(form, defaultForm())
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
    const payload = {
      Title: form.Title.trim(),
      Username: form.Username.trim(),
      Description: form.Description,
      JoinMode: form.JoinMode,
    }

    const response = await store.createOrganization(payload)

    if (response.success) {
      emit('created', response.data.organization)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erstellen der Organisation.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
