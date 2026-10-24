<template>
  <!-- Feedback-Karte auf der Event-Seite: Titel mit +1, Klick öffnet das Detail-Modal -->
  <div class="org-event-feedback">
    <OrgFeedbackList v-if="items.length" :items="items" @open="detailModal?.open($event)" @error="error = $event" />
    <p v-else class="org-event-feedback_empty">Noch kein Feedback zu diesem Event.</p>

    <AppButton variant="secondary" size="small" class="org-event-feedback_add" @click="openCreate">
      + Feedback geben
    </AppButton>
    <p v-if="error" class="org-event-feedback_error">{{ error }}</p>

    <OrgFeedbackFormModal ref="formModal" @saved="onSaved" @open="detailModal?.open($event)" />
    <OrgFeedbackDetailModal
      ref="detailModal"
      @edit="openEdit"
      @changed="onChanged"
      @deleted="onDeleted"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import AppButton from '@components/ui/AppButton.vue'
import OrgFeedbackList from '@components/feedback/OrgFeedbackList.vue'
import OrgFeedbackFormModal from '@components/feedback/OrgFeedbackFormModal.vue'
import OrgFeedbackDetailModal from '@components/feedback/OrgFeedbackDetailModal.vue'

const props = defineProps({
  // Das Event ({ ID, Title, OrganizationID })
  event: { type: Object, required: true },
  // Kurzform aus GET /orgfeedback/eventFeedback/{id}
  items: { type: Array, required: true },
})

// Nach Änderungen neu laden
const emit = defineEmits(['reload'])

const store = useOrgFeedbackStore()
const formModal = ref(null)
const detailModal = ref(null)
const error = ref(null)

function openCreate() {
  const { ID, Title, OrganizationID } = props.event
  formModal.value?.open({ event: { ID, Title, OrganizationID } })
}

// Beim Bearbeiten lassen sich Organisation und Event ändern — dafür die Auswahl laden
async function openEdit(feedback) {
  if (!store.organizations.length) await store.fetchList()
  formModal.value?.openForEdit(feedback)
}

function onSaved(feedback) {
  emit('reload')
  detailModal.value?.open(feedback)
}

function onChanged(fields) {
  const item = props.items.find(i => i.ID === fields.ID)
  if (item) Object.assign(item, fields)
}

function onDeleted() {
  emit('reload')
}
</script>
