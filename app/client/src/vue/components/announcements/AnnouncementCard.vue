<template>
  <div
    class="announcement-card"
    :class="{ 'announcement-card--compact': compact }"
    @click="$emit('click', announcement)"
  >
    <!-- Compact: title + category/date/short text in two lines, e.g. on the dashboard -->
    <template v-if="compact">
      <div class="announcement-card_compact-info">
        <span class="announcement-card_title-text">{{ announcement.Title }}</span>
        <span class="announcement-card_compact-meta">
          <span v-if="categoryLabel" class="announcement-card_category-badge">{{ categoryLabel }}</span>
          <span class="announcement-card_date">{{ announcement.Created }}</span>
          <span v-if="announcement.ShortText" class="announcement-card_short-text">{{ announcement.ShortText }}</span>
        </span>
      </div>
      <AppOrgLogo
        v-if="primaryOrg && !hideOrgLogo"
        :src="primaryOrg.LogoURL"
        :alt="primaryOrg.Title"
        :title="primaryOrg.Title"
        :size="22"
        class="announcement-card_org-logo"
      />
    </template>

    <template v-else>
      <div class="announcement-card_header">
        <h3 class="hl3 announcement-card_title-text">{{ announcement.Title }}</h3>
        <span class="announcement-card_date">{{ announcement.Created }}</span>
        <div v-if="announcement.Organisations?.length && !hideOrgLogo" class="announcement-card_orgs">
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
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'

const props = defineProps({
  announcement: {
    type: Object,
    required: true
  },
  compact: {
    type: Boolean,
    default: false
  },
  // Hide the organization logo(s), e.g. when the user only belongs to one org
  hideOrgLogo: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click'])

const primaryOrg = computed(() => props.announcement.Organisations?.[0] ?? null)

const categoryLabel = computed(() => {
  if (!props.announcement.Category) return null
  if (typeof props.announcement.Category === 'string') return props.announcement.Category
  return props.announcement.Category.Title ?? null
})
</script>
