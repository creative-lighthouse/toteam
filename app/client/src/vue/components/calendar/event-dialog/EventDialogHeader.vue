<template>
  <AppOrgLogo
    v-if="event.OrganizationLogoURL"
    :src="event.OrganizationLogoURL"
    alt=""
    :size="32"
  />
  <div class="event_title">
    <h2 class="hl2">{{ event.Title }}</h2>
    <p v-if="event.Status === 'Suggested'" class="event-card_status">(Vorschlag)</p>
    <p v-else-if="event.Status === 'Cancelled'" class="event-card_status">(Abgesagt)</p>
  </div>
</template>

<script setup>
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'

defineProps({
  event: { type: Object, required: true }
})
</script>
