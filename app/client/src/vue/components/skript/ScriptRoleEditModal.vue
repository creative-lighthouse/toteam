<template>
  <AppModal ref="modal" class="script-role-edit-modal" title="Rolle bearbeiten" @close="close">
    <form id="script-role-edit-form" class="modalform" @submit.prevent="submit">

      <label class="field">
        Titel *
        <input id="script-role-title" v-model="form.Title" type="text" required />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="script-role-description"
          v-model="form.Description"
          rows="3"
          placeholder="z.B. Alter, Charaktereigenschaften, Hinweise für die Besetzung…"
        />
      </label>

      <div class="field">
        <label>Mitglieder</label>
        <MemberPicker
          :members="orgMembers"
          v-model="form.MemberIDs"
          multiple
          show-select-all
        />
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="script-role-edit-form" variant="primary" :disabled="saving || !form.Title.trim()">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import MemberPicker from '@components/ui/MemberPicker.vue'

const props = defineProps({
  orgMembers: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const store = useSkriptStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
let currentRole = null

const form = reactive({
  Title: '',
  Description: '',
  MemberIDs: [],
})

function open(role) {
  currentRole = role
  form.Title = role.Title
  form.Description = role.Description || ''
  form.MemberIDs = [...(role.MemberIDs || [])]
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !currentRole) return

  saving.value = true
  error.value = null

  try {
    // Sequential, not Promise.all: both calls read-modify-write the same
    // ScriptRole row and each overwrites the store's cached copy of it with
    // its own response. Firing them in parallel races two independent reads
    // against each other — whichever response lands second wins and can
    // silently revert the other's change (e.g. the title reverting to its
    // old value after the member-assignment response overwrites it with a
    // row it read before the title write had committed).
    const fieldsResponse = await store.updateRole(currentRole.ID, { Title: form.Title.trim(), Description: form.Description.trim() })
    const membersResponse = await store.assignRoleMembers(currentRole.ID, form.MemberIDs)

    if (fieldsResponse.success && membersResponse.success) {
      emit('saved', membersResponse.data.role)
      close()
    } else {
      error.value = fieldsResponse.error || membersResponse.error || 'Fehler beim Speichern der Rolle.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
