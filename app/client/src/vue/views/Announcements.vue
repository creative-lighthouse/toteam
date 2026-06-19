<template>
  <div class="section section--AnnouncementsPage">
    <div class="section_content">
      <!-- Category Filter -->
      <div class="section_filter">
        <button
          @click="announcementsStore.setCategory(null)"
          class="button"
          :class="{ active: !announcementsStore.selectedCategory }"
        >
          Alle
        </button>
        <button
          v-for="category in announcementsStore.usedCategories"
          :key="category.ID"
          @click="announcementsStore.setCategory(category)"
          class="button"
          :class="{ active: announcementsStore.selectedCategory?.ID === category.ID }"
        >
          {{ category.Title }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="announcementsStore.loading" class="section_infobox">
        <p>Lade Mitteilungen...</p>
      </div>

      <!-- Error State -->
      <div v-if="announcementsStore.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ announcementsStore.error }}</p>
        <button @click="announcementsStore.refresh()" class="button">Erneut versuchen</button>
      </div>

      <!-- Announcements List -->
      <div v-if="!announcementsStore.loading && !announcementsStore.error" class="announcements-list">
        <AnnouncementCard
          v-for="announcement in announcementsStore.filteredAnnouncements"
          :key="announcement.ID"
          :id="`announcement-${announcement.ID}`"
          :announcement="announcement"
          @click="openAnnouncement"
        />

        <div v-if="announcementsStore.filteredAnnouncements.length === 0" class="section_infobox">
          <p>Keine Mitteilungen gefunden.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAnnouncementsStore } from '@stores/announcements'
import { usePageHeaderStore } from '@stores/pageHeader'
import AnnouncementCard from '@components/AnnouncementCard.vue'

const router = useRouter()
const announcementsStore = useAnnouncementsStore()
usePageHeaderStore().setHeader('Mitteilungen', 'Hier findest du alle wichtigen Mitteilungen deines Teams.')

function openAnnouncement(announcement) {
  router.push({ name: 'AnnouncementDetail', params: { id: announcement.ID } })
}

onMounted(async () => {
  await announcementsStore.fetchAnnouncements(true)
})
</script>
