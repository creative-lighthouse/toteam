<template>
  <div class="script-roles-view">
    <form class="script-roles-view_new" @submit.prevent="addRole">
      <input
        v-model="newRoleTitle"
        type="text"
        class="input"
        placeholder="Neue Rolle, z.B. Hamlet"
      />
      <input
        v-model="newRoleDescription"
        type="text"
        class="input"
        placeholder="Kurze Beschreibung (optional)"
      />
      <AppButton type="submit" variant="primary" size="small" :disabled="!newRoleTitle.trim() || saving">
        + Hinzufügen
      </AppButton>
    </form>

    <div v-if="error" class="script-roles-view_error">{{ error }}</div>

    <div v-if="!script?.Roles?.length" class="script-roles-view_empty">
      Dieses Skript hat noch keine Rollen.
    </div>

    <div v-else class="script-roles-view_list">
      <details v-for="role in script.Roles" :key="role.ID" class="script-roles-view_role">
        <summary class="script-roles-view_role-summary">
          <span class="script-roles-view_role-title">{{ role.Title }}</span>
          <span v-if="role.Description" class="script-roles-view_role-description">{{ role.Description }}</span>
          <span class="script-roles-view_role-count">{{ role.MemberIDs?.length || 0 }} Mitglied(er)</span>
        </summary>

        <!-- Anzeigemodus: schneller Überblick, Bearbeitung erfolgt über den Edit-Button im Modal -->
        <div class="script-roles-view_role-body">
          <div class="script-roles-view_role-actions">
            <AppIconButton
              variant="primary"
              aria-label="Rolle bearbeiten"
              title="Bearbeiten"
              @click="editModal?.open(role)"
            >
              <span class="icon-mask" :style="editIconStyle" />
            </AppIconButton>
            <AppIconButton
              variant="danger"
              aria-label="Rolle löschen"
              title="Rolle löschen"
              @click="removeRole(role)"
            >
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </div>

          <p class="script-roles-view_role-description-full">
            {{ role.Description || 'Keine Beschreibung.' }}
          </p>

          <div class="script-roles-view_role-members">
            <span class="script-roles-view_role-members-label">Gespielt von:</span>
            <span v-if="!role.Members?.length" class="script-roles-view_role-members-empty">Niemandem zugewiesen</span>
            <span v-else class="script-roles-view_role-members-list">
              {{ role.Members.map(m => m.Name).join(', ') }}
            </span>
          </div>
        </div>
      </details>
    </div>

    <ScriptRoleEditModal ref="editModal" :org-members="orgMembers" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useSkriptStore } from '@stores/skript'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import ScriptRoleEditModal from '@components/skript/ScriptRoleEditModal.vue'
import actionEdit from '../../../../icons/actions/action_edit.svg'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  script: { type: Object, default: null },
  orgMembers: { type: Array, default: () => [] },
})
const store = useSkriptStore()

const saving = ref(false)
const error = ref(null)
const newRoleTitle = ref('')
const newRoleDescription = ref('')
const editModal = ref(null)

async function addRole() {
  if (!newRoleTitle.value.trim() || !props.script) return
  saving.value = true
  error.value = null
  try {
    const response = await store.createRole(props.script.ID, {
      Title: newRoleTitle.value.trim(),
      Description: newRoleDescription.value.trim(),
    })
    if (response.success) {
      newRoleTitle.value = ''
      newRoleDescription.value = ''
    } else {
      error.value = response.error || 'Fehler beim Anlegen der Rolle.'
    }
  } finally {
    saving.value = false
  }
}

async function removeRole(role) {
  if (!confirm(`Rolle "${role.Title}" wirklich löschen?`)) return
  const response = await store.deleteRole(role.ID)
  if (!response.success) {
    error.value = response.error || 'Fehler beim Löschen der Rolle.'
  }
}
</script>
