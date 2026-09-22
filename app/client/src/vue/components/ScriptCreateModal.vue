<template>
  <AppModal ref="modal" class="script-create-modal" title="Neues Skript" @close="close">
    <form id="script-create-form" @submit.prevent="submit">

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
        <label class="form-label" for="script-title">Titel *</label>
        <input
          id="script-title"
          v-model="form.Title"
          type="text"
          class="input"
          placeholder="z.B. Sommertheater 2027"
          required
          autofocus
        />
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="script-create-form" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
        {{ saving ? 'Speichern…' : 'Erstellen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const emit = defineEmits(['created'])
const store = useSkriptStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

const defaultForm = () => ({
  Title: '',
  OrganizationID: store.organizations.length === 1 ? store.organizations[0].ID : 0,
})

const form = reactive(defaultForm())

function open() {
  Object.assign(form, defaultForm())
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationID) return

  saving.value = true
  error.value = null

  try {
    const response = await store.createScript({
      Title: form.Title.trim(),
      OrganizationID: parseInt(form.OrganizationID),
    })

    if (response.success) {
      emit('created', response.data.script)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erstellen des Skripts.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
