<template>
  <!-- Platzhalter für einen per Link (eventID) geöffneten Termin, solange er noch lädt -->
  <AppModal ref="modal" class="event-dialog-skeleton" @close="$emit('close')">
    <template #header>
      <span class="event-dialog-skeleton_logo" aria-hidden="true"></span>
      <div class="event_title">
        <span class="event-dialog-skeleton_bar event-dialog-skeleton_bar--title" aria-hidden="true"></span>
      </div>
    </template>

    <div class="dialog-infobox event-dialog-skeleton_body" role="status" aria-live="polite">
      <span class="visually-hidden">Termin wird geladen …</span>
      <div class="event-dialog-skeleton_group" aria-hidden="true">
        <span class="event-dialog-skeleton_bar event-dialog-skeleton_bar--wide"></span>
        <span class="event-dialog-skeleton_bar event-dialog-skeleton_bar--medium"></span>
        <span class="event-dialog-skeleton_bar event-dialog-skeleton_bar--short"></span>
      </div>
      <div class="event-dialog-skeleton_buttons" aria-hidden="true">
        <span v-for="n in 3" :key="n" class="event-dialog-skeleton_button"></span>
      </div>
      <div class="event-dialog-skeleton_group" aria-hidden="true">
        <span v-for="n in 3" :key="n" class="event-dialog-skeleton_participant"></span>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppModal from '@components/ui/AppModal.vue'

defineEmits(['close'])

const modal = ref(null)

onMounted(() => {
  modal.value?.open()
})
</script>
