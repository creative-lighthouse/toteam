<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="member-roles-modal" @cancel.prevent="close">
      <div class="member-roles-modal_content" @click.stop>

        <div class="member-roles-modal_header">
          <h2 class="hl2 member-roles-modal_title">Rollen für {{ memberName }}</h2>
          <button class="button--close" aria-label="Schließen" @click="close">✕</button>
        </div>

        <form class="member-roles-modal_body" @submit.prevent="submit">

          <div v-if="availableRoles.length === 0" class="member-roles-modal_empty">
            Diese Organisation hat noch keine Rollen.
          </div>

          <div v-else class="member-roles-modal_list">
            <label v-for="role in availableRoles" :key="role.ID" class="form-checkbox">
              <input type="checkbox" :value="role.ID" v-model="selected" />
              {{ role.Title }}
            </label>
          </div>

          <div v-if="error" class="member-roles-modal_error">{{ error }}</div>

          <div class="member-roles-modal_actions">
            <button type="button" class="button" @click="close" :disabled="saving">Abbrechen</button>
            <button type="submit" class="button button--primary" :disabled="saving">
              {{ saving ? 'Speichern…' : 'Speichern' }}
            </button>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { useOrgRolesStore } from '@stores/orgRoles'

const props = defineProps({
  membershipId: { type: Number, required: true },
  memberName: { type: String, default: '' },
  currentRoleIds: { type: Array, default: () => [] },
  availableRoles: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const orgRolesStore = useOrgRolesStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)
const selected = ref([])

function open() {
  selected.value = [...props.currentRoleIds]
  error.value = null
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
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
