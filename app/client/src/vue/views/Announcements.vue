<template>
  <div class="section section--AnnouncementsPage">
    <div class="section_content">
      <!-- Neuer Beitrag (Text, als Person oder Organisation, öffentlich oder intern, optional geplant) -->
      <FeedComposer class="announcements-composer" />

      <div v-if="store.loading" class="section_infobox">
        <p>Lade Feed...</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.refresh()">Erneut versuchen</AppButton>
      </div>

      <!-- Feed: neueste Beiträge zuerst -->
      <div v-else class="feed-list">
        <FeedPostCard v-for="post in store.posts" :key="post.ID" :post="post" @delete="deletePost" />

        <div v-if="store.posts.length === 0" class="section_infobox">
          <p>Noch keine Beiträge — schreib den ersten!</p>
        </div>
      </div>

      <!-- Abgelaufene Beiträge -->
      <div v-if="!store.loading && !store.error" class="announcements-archive">
        <AppIconButton
          variant="neutral"
          class="announcements-archive_toggle"
          :aria-label="showArchive ? 'Vergangene Beiträge ausblenden' : 'Vergangene Beiträge anzeigen'"
          :title="showArchive ? 'Vergangene Beiträge ausblenden' : 'Vergangene Beiträge anzeigen'"
          :aria-expanded="showArchive"
          @click="toggleArchive"
        >
          <span class="icon-mask" :style="historyIconStyle"></span>
        </AppIconButton>

        <template v-if="showArchive">
          <h2 class="announcements-archive_title">Vergangene Beiträge</h2>

          <div v-if="store.archiveLoading" class="section_infobox">
            <p>Lade vergangene Beiträge...</p>
          </div>

          <div v-else-if="store.archiveError" class="section_infobox error">
            <p>Fehler beim Laden: {{ store.archiveError }}</p>
            <AppButton variant="primary" @click="store.fetchArchive()">Erneut versuchen</AppButton>
          </div>

          <div v-else class="feed-list feed-list--archive">
            <FeedPostCard v-for="post in store.archivedPosts" :key="post.ID" :post="post" @delete="deletePost" />

            <div v-if="store.archivedPosts.length === 0" class="section_infobox">
              <p>Keine vergangenen Beiträge.</p>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAnnouncementsStore } from '@stores/announcements'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import FeedComposer from '@components/announcements/FeedComposer.vue'
import FeedPostCard from '@components/announcements/FeedPostCard.vue'
import HistoryIcon from '../../../icons/actions/action_history.svg'

const store = useAnnouncementsStore()
const historyIconStyle = { maskImage: `url("${HistoryIcon}")`, WebkitMaskImage: `url("${HistoryIcon}")` }
const showArchive = ref(false)
usePageHeaderStore().setHeader('Mitteilungen', 'Beiträge aus deinen Organisationen und ganz ToTeam.')

async function deletePost(post) {
  if (!confirm('Diesen Beitrag wirklich löschen?')) return
  try {
    await store.deletePost(post.ID)
  } catch (e) {
    alert(e.message)
  }
}

async function toggleArchive() {
  showArchive.value = !showArchive.value
  if (showArchive.value && !store.archiveLoaded) {
    await store.fetchArchive()
  }
}

onMounted(() => store.fetchFeed())
</script>
