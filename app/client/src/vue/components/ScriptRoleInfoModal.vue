<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="script-role-info-modal" @cancel.prevent="close">
      <div class="script-role-info-modal_content" @click.stop>

        <div class="script-role-info-modal_header">
          <h2 class="hl2 script-role-info-modal_title">
            {{ roles.length === 1 ? roles[0].Title : 'Rollen' }}
          </h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div class="script-role-info-modal_body">
          <div v-for="role in roles" :key="role.ID" class="script-role-info-modal_role">
            <h3 v-if="roles.length > 1" class="script-role-info-modal_role-title">{{ role.Title }}</h3>

            <p class="script-role-info-modal_description">
              {{ role.Description || 'Keine Beschreibung.' }}
            </p>

            <p class="script-role-info-modal_members">
              <span class="script-role-info-modal_members-label">Gespielt von:</span>
              <span v-if="!role.Members?.length" class="script-role-info-modal_members-empty">Niemandem zugewiesen</span>
              <span v-else>{{ role.Members.map(m => m.Name).join(', ') }}</span>
            </p>
          </div>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import AppIconButton from '@components/AppIconButton.vue'

const dialogEl = ref(null)
const roles = ref([])

function open(rolesToShow) {
  roles.value = rolesToShow
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

defineExpose({ open, close })
</script>
