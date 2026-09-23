<template>
  <div class="section section--AnnouncementsPage">
    <div class="section_content">
      <div class="announcements-toolbar">
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

        <!-- Neue Mitteilung (nur mit Berechtigung in mindestens einer Organisation) -->
        <AppIconButton
          v-if="announcementsStore.createOrganizations.length"
          variant="primary"
          class="announcements-toolbar_add"
          aria-label="Neue Mitteilung"
          title="Neue Mitteilung"
          @click="createModal?.open()"
        >
          <span class="icon-mask" :style="addMessageIconStyle"></span>
        </AppIconButton>
      </div>

      <!-- Loading State -->
      <div v-if="announcementsStore.loading" class="section_infobox">
        <p>Lade Mitteilungen...</p>
      </div>

      <!-- Error State -->
      <div v-if="announcementsStore.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ announcementsStore.error }}</p>
        <AppButton variant="primary" @click="announcementsStore.refresh()">Erneut versuchen</AppButton>
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

      <!-- Vergangene Mitteilungen -->
      <div v-if="!announcementsStore.loading && !announcementsStore.error" class="announcements-archive">
        <AppIconButton
          variant="neutral"
          class="announcements-archive_toggle"
          :aria-label="showArchive ? 'Vergangene Mitteilungen ausblenden' : 'Vergangene Mitteilungen anzeigen'"
          :title="showArchive ? 'Vergangene Mitteilungen ausblenden' : 'Vergangene Mitteilungen anzeigen'"
          :aria-expanded="showArchive"
          @click="toggleArchive"
        >
          <span class="icon-mask" :style="historyIconStyle"></span>
        </AppIconButton>

        <template v-if="showArchive">
          <h2 class="announcements-archive_title">Vergangene Mitteilungen</h2>

          <div v-if="announcementsStore.archiveLoading" class="section_infobox">
            <p>Lade vergangene Mitteilungen...</p>
          </div>

          <div v-else-if="announcementsStore.archiveError" class="section_infobox error">
            <p>Fehler beim Laden: {{ announcementsStore.archiveError }}</p>
            <AppButton variant="primary" @click="announcementsStore.fetchArchivedAnnouncements(true)">Erneut versuchen</AppButton>
          </div>

          <div v-else class="announcements-list announcements-list--archive">
            <AnnouncementCard
              v-for="announcement in announcementsStore.filteredArchivedAnnouncements"
              :key="announcement.ID"
              :id="`announcement-${announcement.ID}`"
              :announcement="announcement"
              @click="openAnnouncement"
            />

            <div v-if="announcementsStore.filteredArchivedAnnouncements.length === 0" class="section_infobox">
              <p>Keine vergangenen Mitteilungen gefunden.</p>
            </div>
          </div>
        </template>
      </div>
    </div>

    <AnnouncementCreateModal ref="createModal" @created="onCreated" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAnnouncementsStore } from '@stores/announcements'
import { usePageHeaderStore } from '@stores/pageHeader'
import AnnouncementCard from '@components/AnnouncementCard.vue'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AnnouncementCreateModal from '@components/AnnouncementCreateModal.vue'
import AddMessageIcon from '../../../icons/actions/action_addmessage.svg'
import HistoryIcon from '../../../icons/actions/action_history.svg'

const router = useRouter()
const announcementsStore = useAnnouncementsStore()
const createModal = ref(null)
const addMessageIconStyle = { maskImage: `url("${AddMessageIcon}")`, WebkitMaskImage: `url("${AddMessageIcon}")` }
const historyIconStyle = { maskImage: `url("${HistoryIcon}")`, WebkitMaskImage: `url("${HistoryIcon}")` }
const showArchive = ref(false)
usePageHeaderStore().setHeader('Mitteilungen', 'Hier findest du alle wichtigen Mitteilungen deines Teams.')

function openAnnouncement(announcement) {
  router.push({ name: 'AnnouncementDetail', params: { id: announcement.ID } })
}

// Geplante Mitteilungen sind noch nicht sichtbar, daher nur aktive direkt öffnen
function onCreated(announcement) {
  if (announcement.Status === 'active') openAnnouncement(announcement)
}

async function toggleArchive() {
  showArchive.value = !showArchive.value
  if (showArchive.value && !announcementsStore.archiveLoaded) {
    await announcementsStore.fetchArchivedAnnouncements(true)
  }
}

onMounted(async () => {
  await announcementsStore.fetchAnnouncements(true)
})
</script>
