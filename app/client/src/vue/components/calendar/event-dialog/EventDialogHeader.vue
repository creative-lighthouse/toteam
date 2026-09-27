<template>
  <AppOrgLogo
    v-if="primaryOrg"
    :src="primaryOrg.LogoURL"
    :name="primaryOrg.Title"
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
import { computed } from 'vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'

const props = defineProps({
  event: { type: Object, required: true }
})

// Erste Organisation des Termins (ohne Logo zeigt AppOrgLogo die Initiale — wie in der EventCard)
const primaryOrg = computed(() =>
  props.event.OrganizationLogos?.[0]
    || (props.event.OrganizationLogoURL ? { LogoURL: props.event.OrganizationLogoURL, Title: '' } : null)
)
</script>
