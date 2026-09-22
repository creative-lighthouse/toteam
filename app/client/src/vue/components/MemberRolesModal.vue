<template>
  <AppModal ref="modal" class="member-roles-modal" :title="`Rollen für ${memberName}`" @close="close">
    <form id="member-roles-form" @submit.prevent="submit">

      <div v-if="availableRoles.length === 0" class="member-roles-modal_empty">
        Diese Organisation hat noch keine Rollen.
      </div>

      <div v-else class="member-roles-modal_list">
        <label v-for="role in availableRoles" :key="role.ID" class="form-checkbox">
          <input type="checkbox" :value="role.ID" v-model="selected" />
          {{ role.Title }}
        </label>
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>

    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="member-roles-form" variant="primary" :disabled="saving">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { useOrgRolesStore } from '@stores/orgRoles'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const props = defineProps({
  membershipId: { type: Number, required: true },
  memberName: { type: String, default: '' },
  currentRoleIds: { type: Array, default: () => [] },
  availableRoles: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const orgRolesStore = useOrgRolesStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const selected = ref([])

function open() {
  selected.value = [...props.currentRoleIds]
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    const response = await orgRolesStore.assignRolesToMember(props.membershipId, selected.value.map(id => parseInt(id)))

    if (response.success) {
      emit('saved', response.data.membership)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Rollen.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
