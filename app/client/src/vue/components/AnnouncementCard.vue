<template>
  <div
    class="announcement-card"
    @click="$emit('click', announcement)"
  >
    <div class="announcement-card_header">
      <h3 class="hl3 announcement-card_title-text">{{ announcement.Title }}</h3>
      <span class="announcement-card_date">{{ announcement.Created }}</span>
      <div v-if="announcement.Organisations?.length" class="announcement-card_orgs">
        <AppOrgLogo
          v-for="org in announcement.Organisations.slice(0, 3)"
          :key="org.ID"
          :src="org.LogoURL"
          :alt="org.Title"
          :size="25"
        />
      </div>
    </div>

    <div v-if="announcement.ShortText" class="announcement-card_short-text">{{ announcement.ShortText }}</div>

    <div class="announcement-card_footer">
      <span v-if="categoryLabel" class="announcement-card_category-badge">{{ categoryLabel }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import AppOrgLogo from './AppOrgLogo.vue'

const props = defineProps({
  announcement: {
    type: Object,
    required: true
  }
})

defineEmits(['click'])

const categoryLabel = computed(() => {
  if (!props.announcement.Category) return null
  if (typeof props.announcement.Category === 'string') return props.announcement.Category
  return props.announcement.Category.Title ?? null
})
</script>
