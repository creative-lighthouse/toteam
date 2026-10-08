<template>
  <!-- Karte in der Lageplan-Übersicht (Tab "Events"): Lageplan eines Events mit dem
       Event darauf -->
  <router-link
    :to="{ name: 'MapDetail', params: { id: entry.mapId } }"
    class="map-entry_link map-event-plan-link"
  >
    <div class="map-entry_thumbnail">
      <img v-if="entry.thumbnailUrl" :src="entry.thumbnailUrl" :alt="entry.title" />
      <div v-else class="map-entry_thumbnail--placeholder"></div>
      <AppOrgLogo
        v-if="entry.event.OrganizationTitle"
        :src="entry.event.OrganizationLogoURL"
        :alt="entry.event.OrganizationTitle"
        :name="entry.event.OrganizationTitle"
        :size="36"
        class="map-entry_org-logo"
      />
    </div>
    <div class="map-entry_info">
      <p class="map-event-plan-link_event">
        <span class="map-event-plan-link_event-title">{{ entry.event.Title }}</span>
        <span v-if="dateLabel" class="map-event-plan-link_event-date">{{ dateLabel }}</span>
      </p>
      <h3 class="map-entry_title">{{ entry.title }}</h3>
      <p class="map-event-plan-link_meta">{{ placedLabel }}</p>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import { formatOrgEventRange } from '@utils/orgEvents'

const props = defineProps({
  // Eintrag aus eventPlans von GET /maps: { mapId, title, thumbnailUrl, placedCount, event }
  entry: { type: Object, required: true },
})

const dateLabel = computed(() => (props.entry.event.RangeStart ? formatOrgEventRange(props.entry.event) : ''))

const placedLabel = computed(() => {
  const count = props.entry.placedCount
  if (!count) return 'Noch keine Objekte platziert'
  return count === 1 ? '1 Objekt platziert' : `${count} Objekte platziert`
})
</script>
