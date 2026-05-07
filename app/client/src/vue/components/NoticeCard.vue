<template>
  <div
    class="notice-card"
    @click="$emit('click', notice)"
  >
    <!-- Org-Logos -->

    <div class="notice-card_header">

        <h3 class="hl3 notice-card_title-text">{{ notice.Title }}</h3>
        <span class="notice-card_date">{{ notice.Created }}</span>
        <div v-if="notice.Organisations?.length" class="notice-card_orgs">
            <img
                v-for="org in notice.Organisations.slice(0, 3)"
                :key="org.ID"
                :src="org.LogoURL"
                :alt="org.Title"
                class="notice-card_org-logo"
            >
        </div>
    </div>

    <div v-if="notice.ShortText" class="notice-card_short-text">{{ notice.ShortText }}</div>

    <div class="notice-card_footer">
      <span v-if="categoryLabel" class="notice-card_category-badge">{{ categoryLabel }}</span>
    </div>
  </div>
</template>

<script setup>
    import { computed } from 'vue'

    const props = defineProps({
        notice: {
            type: Object,
            required: true
        }
    })

    defineEmits(['click'])

    const categoryLabel = computed(() => {
        if (!props.notice.Category) return null
        if (typeof props.notice.Category === 'string') return props.notice.Category
        return props.notice.Category.Title ?? null
    })
</script>
