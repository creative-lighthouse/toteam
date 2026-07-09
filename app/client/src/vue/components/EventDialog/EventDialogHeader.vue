<template>
  <div class="dialog-header">
    <img
      v-if="event.OrganizationLogoURL"
      :src="event.OrganizationLogoURL"
      class="event-dialog_org-logo"
      alt=""
    >
    <div class="event_title">
      <h2 class="hl2">{{ event.Title }}</h2>
      <p v-if="event.Status === 'Suggested'" class="event-card_status">(Vorschlag)</p>
      <p v-else-if="event.Status === 'Cancelled'" class="event-card_status">(Abgesagt)</p>
    </div>
    <AppIconButton variant="ghost" aria-label="Schließen" @click="$emit('close')">
      ✕
    </AppIconButton>
  </div>
</template>

<script setup>
import AppIconButton from '@components/AppIconButton.vue'

defineProps({
  event: { type: Object, required: true }
})

defineEmits(['close'])
</script>
