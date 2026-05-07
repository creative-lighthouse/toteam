<template>
  <div class="section section--NoticesPage">
    <AppHeader title="Mitteilungen" description="Hier findest du alle wichtigen Mitteilungen deines Teams.">
      <span v-if="notificationsStore.unreadCount > 0" class="badge">
        {{ notificationsStore.unreadCount }} ungelesen
      </span>
    </AppHeader>

    <div class="section_content">
      <!-- Category Filter -->
      <div class="section_filter">
        <button
          @click="noticesStore.setCategory(null)"
          class="button"
          :class="{ active: !noticesStore.selectedCategory }"
        >
          Alle
        </button>
        <button
          v-for="category in noticesStore.categories"
          :key="category.ID"
          @click="noticesStore.setCategory(category)"
          class="button"
          :class="{ active: noticesStore.selectedCategory?.ID === category.ID }"
        >
          {{ category.Title }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="noticesStore.loading" class="section_infobox">
        <p>Lade Mitteilungen...</p>
      </div>

      <!-- Error State -->
      <div v-if="noticesStore.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ noticesStore.error }}</p>
        <button @click="noticesStore.refresh()" class="button">Erneut versuchen</button>
      </div>

      <!-- Notices List -->
      <div v-if="!noticesStore.loading && !noticesStore.error" class="notices-list">
        <NoticeCard
          v-for="notice in noticesStore.filteredNotices"
          :key="notice.ID"
          :id="`notice-${notice.ID}`"
          :notice="notice"
          @click="openNotice"
        />

        <div v-if="noticesStore.filteredNotices.length === 0" class="section_infobox">
          <p>Keine Mitteilungen gefunden.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNoticesStore } from '@stores/notices'
import { useNotificationsStore } from '@stores/notifications'
import AppHeader from '@components/AppHeader.vue'
import NoticeCard from '@components/NoticeCard.vue'

const router = useRouter()
const noticesStore = useNoticesStore()
const notificationsStore = useNotificationsStore()

function openNotice(notice) {
  router.push({ name: 'NoticeDetail', params: { id: notice.ID } })
}

onMounted(async () => {
  await noticesStore.fetchNotices(true)
})
</script>
