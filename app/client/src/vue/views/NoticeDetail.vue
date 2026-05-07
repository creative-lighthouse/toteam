<template>
  <div class="section section--NoticeDetailPage">
    <AppHeader :title="notice?.Title ?? 'Mitteilung'" />

    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Mitteilung...</p>
      </div>

      <div v-else-if="!notice" class="section_infobox">
        <p>Mitteilung nicht gefunden.</p>
      </div>

      <div v-else class="notice-detail_card">
        <!-- Organisation(en) -->
        <div v-if="notice.Organisations?.length" class="notice-detail_orgs">
          <div
            v-for="org in notice.Organisations"
            :key="org.ID"
            class="notice-detail_org"
          >
            <img
              v-if="org.LogoURL"
              :src="org.LogoURL"
              :alt="org.Title"
              class="notice-detail_org-logo"
            >
            <span class="notice-detail_org-name">{{ org.Title }}</span>
          </div>
        </div>

        <!-- Titel + Meta -->
        <div class="notice-detail_header">
          <h2 class="hl2 notice-detail_title">{{ notice.Title }}</h2>
          <div class="notice-detail_meta">
            <span>{{ notice.Created }}</span>
            <span v-if="notice.AuthorName">von {{ notice.AuthorName }}</span>
            <span v-if="notice.Category?.Title ?? notice.Category" class="notice-detail_category">
              {{ notice.Category?.Title ?? notice.Category }}
            </span>
          </div>
        </div>

        <!-- Langtext -->
        <div class="notice-detail_content" v-html="notice.LongText"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useNoticesStore } from '@stores/notices'
import AppHeader from '@components/AppHeader.vue'

const route = useRoute()
const noticesStore = useNoticesStore()

const notice = ref(null)
const loading = ref(true)

onMounted(async () => {
  const id = Number(route.params.id)

  // Use cached data first, then fall back to fetch
  let found = noticesStore.getNoticeById(id)
  if (!found) {
    await noticesStore.fetchNotices()
    found = noticesStore.getNoticeById(id)
  }

  notice.value = found
  loading.value = false
})
</script>
