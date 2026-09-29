<template>
  <AppModal ref="modal" class="org-event-create-modal" title="Neues Event" @close="close">
    <form id="org-event-create-form" class="modalform" @submit.prevent="submit">
      <div v-if="orgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.organizationId" :orgs="orgs" />
      </div>

      <label class="field">
        Titel *
        <input v-model="form.title" type="text" required placeholder="z.B. Halloweenhaus 2026" />
      </label>

      <p class="org-event-create-modal_hint">Termine ordnest du beim Anlegen oder Bearbeiten im Kalender einem Event zu.</p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-event-create-form" variant="primary" :disabled="submitting">
        {{ submitting ? 'Anlegen…' : 'Anlegen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const props = defineProps({
  // Organisationen, in denen man Termine verwalten darf (CALENDAR_MANAGE)
  orgs: { type: Array, default: () => [] },
})

const emit = defineEmits(['created'])

const orgEventsStore = useOrgEventsStore()

const modal = ref(null)
const form = ref({ title: '', organizationId: null })
const submitting = ref(false)
const error = ref(null)

function open() {
  form.value = { title: '', organizationId: props.orgs.length === 1 ? props.orgs[0].ID : null }
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  error.value = null
  if (!form.value.title.trim()) {
    error.value = 'Bitte gib einen Titel ein.'
    return
  }
  if (!form.value.organizationId) {
    error.value = 'Bitte wähle eine Organisation.'
    return
  }
  submitting.value = true
  try {
    const event = await orgEventsStore.createEvent(form.value.title.trim(), form.value.organizationId)
    close()
    emit('created', event)
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

defineExpose({ open, close })
</script>
