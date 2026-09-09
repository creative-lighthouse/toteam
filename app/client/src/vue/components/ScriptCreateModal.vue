<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="script-create-modal" @cancel.prevent="close">
      <div class="script-create-modal_content" @click.stop>

        <div class="script-create-modal_header">
          <h2 class="hl2 script-create-modal_title">Neues Skript</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="script-create-modal_body" @submit.prevent="submit">

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

          <div v-if="error" class="script-create-modal_error">{{ error }}</div>

          <div class="script-create-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
              {{ saving ? 'Speichern…' : 'Erstellen' }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const emit = defineEmits(['created'])
const store = useSkriptStore()

const dialogEl = ref(null)
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
