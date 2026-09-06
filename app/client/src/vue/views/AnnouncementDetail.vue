<template>
  <div class="section section--AnnouncementDetailPage">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Mitteilung...</p>
      </div>

      <div v-else-if="!announcement" class="section_infobox">
        <p>Mitteilung nicht gefunden.</p>
      </div>

      <div v-else class="announcement-detail_card">
        <!-- Organisation(en) -->
        <div v-if="announcement.Organisations?.length" class="announcement-detail_orgs">
          <div
            v-for="org in announcement.Organisations"
            :key="org.ID"
            class="announcement-detail_org"
          >
            <AppOrgLogo
              :src="org.LogoURL"
              :alt="org.Title"
              :size="32"
            />
            <span class="announcement-detail_org-name">{{ org.Title }}</span>
          </div>
        </div>

        <!-- Titel + Meta -->
        <div class="announcement-detail_header">
          <h2 class="hl2 announcement-detail_title">{{ announcement.Title }}</h2>
          <div class="announcement-detail_meta">
            <span>{{ announcement.Created }}</span>
            <span v-if="announcement.AuthorName">von {{ announcement.AuthorName }}</span>
            <span v-if="announcement.Category?.Title ?? announcement.Category" class="announcement-detail_category">
              {{ announcement.Category?.Title ?? announcement.Category }}
            </span>
          </div>
        </div>

        <!-- Langtext -->
        <div class="announcement-detail_content" v-html="announcement.LongText"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAnnouncementsStore } from '@stores/announcements'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppOrgLogo from '@components/AppOrgLogo.vue'

const route = useRoute()
const announcementsStore = useAnnouncementsStore()
const pageHeaderStore = usePageHeaderStore()

pageHeaderStore.setHeader('Mitteilung')

const announcement = ref(null)
const loading = ref(true)

watch(announcement, (val) => {
  pageHeaderStore.setTitle(val?.Title ?? 'Mitteilung')
})

onMounted(async () => {
  const id = Number(route.params.id)

  let found = announcementsStore.getAnnouncementById(id)
  if (!found) {
    await announcementsStore.fetchAnnouncements()
    found = announcementsStore.getAnnouncementById(id)
  }

  announcement.value = found
  loading.value = false
})
</script>
