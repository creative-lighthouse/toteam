<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="task-supporters-modal" @cancel.prevent="close">
      <div class="task-supporters-modal_content" @click.stop>

        <div class="task-supporters-modal_header">
          <h2 class="hl2 task-supporters-modal_title">Unterstützer verwalten</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form class="task-supporters-modal_body" @submit.prevent="submit">

          <div v-if="loadingMembers" class="task-supporters-modal_loading">Lade Mitglieder…</div>

          <div v-else-if="memberOptions.length === 0" class="task-supporters-modal_loading">
            Keine weiteren Mitglieder in dieser Organisation.
          </div>

          <div v-else class="task-supporters-modal_list">
            <label v-for="m in memberOptions" :key="m.ID" class="task-supporters-modal_option">
              <input type="checkbox" :value="m.ID" v-model="selected" />
              <AppAvatar :src="m.Avatar" :alt="m.Name" img-class="task-supporters-modal_avatar" />
              <span>{{ m.Name }}</span>
            </label>
          </div>

          <div v-if="error" class="task-supporters-modal_error">{{ error }}</div>

          <div class="task-supporters-modal_actions">
            <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
            <AppButton type="submit" variant="primary" :disabled="saving || loadingMembers">
              {{ saving ? 'Speichern…' : 'Speichern' }}
            </AppButton>
          </div>

        </form>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'
import { useTasksStore } from '@stores/tasks'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppAvatar from '@components/AppAvatar.vue'

const props = defineProps({
  taskId: { type: Number, required: true },
  organizationId: { type: Number, required: true },
  ownerId: { type: Number, default: 0 },
  currentSupporterIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['saved'])
const store = useTasksStore()

const dialogEl = ref(null)
const saving = ref(false)
const loadingMembers = ref(false)
const error = ref(null)
const memberOptions = ref([])
const selected = ref([])

async function open() {
  error.value = null
  selected.value = [...props.currentSupporterIds]
  loadingMembers.value = true
  try {
    const members = await store.fetchOrgMembers(props.organizationId)
    memberOptions.value = members.filter(m => m.ID !== props.ownerId)
  } finally {
    loadingMembers.value = false
  }
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    const response = await store.updateTask(props.taskId, { SupporterIDs: selected.value.map(id => parseInt(id)) })

    if (response.success) {
      emit('saved', response.data.task)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Unterstützer.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
