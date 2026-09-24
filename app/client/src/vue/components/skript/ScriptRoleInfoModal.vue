<template>
  <AppModal
    ref="modal"
    class="script-role-info-modal"
    :title="roles.length === 1 ? roles[0].Title : 'Rollen'"
    @close="close"
  >
    <div v-for="role in roles" :key="role.ID" class="script-role-info-modal_role">
      <h3 v-if="roles.length > 1" class="script-role-info-modal_role-title">{{ role.Title }}</h3>

      <p class="script-role-info-modal_description">
        <AppLinkifiedText :text="role.Description || 'Keine Beschreibung.'" />
      </p>

      <p class="script-role-info-modal_members">
        <span class="script-role-info-modal_members-label">Gespielt von:</span>
        <span v-if="!role.Members?.length" class="script-role-info-modal_members-empty">Niemandem zugewiesen</span>
        <span v-else>{{ role.Members.map(m => m.Name).join(', ') }}</span>
      </p>
    </div>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import AppModal from '@components/ui/AppModal.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'

const modal = ref(null)
const roles = ref([])

function open(rolesToShow) {
  roles.value = rolesToShow
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

defineExpose({ open, close })
</script>
