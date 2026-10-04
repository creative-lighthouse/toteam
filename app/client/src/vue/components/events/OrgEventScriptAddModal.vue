<template>
  <AppModal ref="modal" class="org-event-script-add-modal" title="Skript hinzufügen" @close="close">
    <form id="org-event-script-add-form" class="modalform" @submit.prevent="submit">
      <AppSegmentedToggle v-if="modes.length > 1" v-model="mode" :options="modes" />

      <label v-if="mode === 'existing'" class="field">
        Vorhandenes Skript
        <select v-model="scriptId" required>
          <option :value="null" disabled>— Skript wählen —</option>
          <option v-for="s in availableScripts" :key="s.ID" :value="s.ID">
            {{ s.Title }} ({{ s.RoleCount }} {{ s.RoleCount === 1 ? 'Rolle' : 'Rollen' }})
          </option>
        </select>
      </label>

      <label v-else class="field">
        Titel *
        <input v-model="title" type="text" placeholder="z.B. Geisterbahn-Text" required />
      </label>

      <p class="org-event-script-add-modal_hint">
        {{ mode === 'existing'
          ? 'Das Skript wird mit seinen Rollen auch für dieses Event verwendet — Änderungen am Skript gelten überall.'
          : 'Das neue Skript gehört zur Organisation des Events. Rollen kannst du danach direkt hier anlegen.' }}
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-event-script-add-form" variant="primary" :disabled="saving || !valid">
        {{ saving ? 'Speichern…' : 'Hinzufügen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
  // Stand aus SkriptApiController::formatEventScripts()
  availableScripts: { type: Array, default: () => [] },
  canCreate: { type: Boolean, default: false },
})

// Neuer Gesamtstand nach dem Hinzufügen
const emit = defineEmits(['saved'])
const store = useSkriptStore()

const modal = ref(null)
const mode = ref('existing')
const scriptId = ref(null)
const title = ref('')
const saving = ref(false)
const error = ref(null)

const modes = computed(() => [
  props.availableScripts.length && { value: 'existing', label: 'Vorhandenes Skript' },
  props.canCreate && { value: 'new', label: 'Neues Skript' },
].filter(Boolean))

const valid = computed(() => (mode.value === 'existing' ? !!scriptId.value : !!title.value.trim()))

function open() {
  mode.value = modes.value[0]?.value ?? 'new'
  scriptId.value = null
  title.value = ''
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  try {
    const data = mode.value === 'existing' ? { ScriptID: scriptId.value } : { Title: title.value.trim() }
    emit('saved', await store.attachEventScript(props.eventId, data))
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
