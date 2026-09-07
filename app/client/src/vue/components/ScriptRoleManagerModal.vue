<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="script-role-manager-modal" @cancel.prevent="close">
      <div class="script-role-manager-modal_content" @click.stop>

        <div class="script-role-manager-modal_header">
          <h2 class="hl2 script-role-manager-modal_title">Rollen verwalten</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div class="script-role-manager-modal_body">

          <form class="script-role-manager-modal_new" @submit.prevent="addRole">
            <input
              v-model="newRoleTitle"
              type="text"
              class="input"
              placeholder="Neue Rolle, z.B. Hamlet"
            />
            <AppButton type="submit" variant="primary" size="small" :disabled="!newRoleTitle.trim() || saving">
              + Hinzufügen
            </AppButton>
          </form>

          <div v-if="!script?.Roles?.length" class="script-role-manager-modal_empty">
            Dieses Skript hat noch keine Rollen.
          </div>

          <div v-else class="script-role-manager-modal_list">
            <div v-for="role in script.Roles" :key="role.ID" class="script-role-manager-modal_role">
              <input
                class="input script-role-manager-modal_role-title"
                :value="role.Title"
                @change="renameRole(role, $event.target.value)"
              />

              <div class="script-role-manager-modal_members">
                <label v-for="m in orgMembers" :key="m.ID" class="form-checkbox">
                  <input
                    type="checkbox"
                    :value="m.ID"
                    :checked="role.MemberIDs?.includes(m.ID)"
                    @change="toggleMember(role, m.ID, $event.target.checked)"
                  />
                  {{ m.Name }}
                </label>
              </div>

              <AppButton variant="danger" size="small" @click="removeRole(role)">Rolle löschen</AppButton>
            </div>
          </div>

          <div v-if="error" class="script-role-manager-modal_error">{{ error }}</div>

        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const props = defineProps({
  script: { type: Object, default: null },
  orgMembers: { type: Array, default: () => [] },
})
const store = useSkriptStore()

const dialogEl = ref(null)
const saving = ref(false)
const error = ref(null)
const newRoleTitle = ref('')

function open() {
  error.value = null
  newRoleTitle.value = ''
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function addRole() {
  if (!newRoleTitle.value.trim() || !props.script) return
  saving.value = true
  error.value = null
  try {
    const response = await store.createRole(props.script.ID, newRoleTitle.value.trim())
    if (response.success) {
      newRoleTitle.value = ''
    } else {
      error.value = response.error || 'Fehler beim Anlegen der Rolle.'
    }
  } finally {
    saving.value = false
  }
}

async function renameRole(role, title) {
  if (!title.trim() || title === role.Title) return
  const response = await store.updateRole(role.ID, title.trim())
  if (!response.success) {
    error.value = response.error || 'Fehler beim Umbenennen der Rolle.'
  }
}

async function removeRole(role) {
  if (!confirm(`Rolle "${role.Title}" wirklich löschen?`)) return
  const response = await store.deleteRole(role.ID)
  if (!response.success) {
    error.value = response.error || 'Fehler beim Löschen der Rolle.'
  }
}

async function toggleMember(role, memberId, checked) {
  const currentIds = role.MemberIDs || []
  const newIds = checked
    ? [...currentIds, memberId]
    : currentIds.filter(id => id !== memberId)

  const response = await store.assignRoleMembers(role.ID, newIds)
  if (!response.success) {
    error.value = response.error || 'Fehler beim Zuweisen der Rolle.'
  }
}

defineExpose({ open, close })
</script>
