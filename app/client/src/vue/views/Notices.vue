<template>
  <div class="section section--NoticesPage">
    <div class="intro-bar">
      <h1 class="hl1">Mitteilungen</h1>
      <p>Hier findest du alle wichtigen Mitteilungen deines Teams.</p>
      <span v-if="noticesStore.unreadCount > 0" class="badge">
        {{ noticesStore.unreadCount }} ungelesen
      </span>
    </div>
    
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
        <div 
          v-for="notice in noticesStore.filteredNotices" 
          :key="notice.ID"
          class="notice-item"
          :class="{ 'notice--unread': !notice.IsRead }"
        >
          <div class="notice-header">
            <h3 class="hl3">{{ notice.Title }}</h3>
            <span class="notice-date">{{ notice.Created }}</span>
          </div>
          <div class="notice-content" v-html="notice.Content"></div>
          <div class="notice-footer">
            <span class="notice-category">{{ notice.Category?.Title }}</span>
            <button 
              v-if="!notice.IsRead" 
              @click="markAsRead(notice.ID)" 
              class="button button--small"
            >
              Als gelesen markieren
            </button>
          </div>
        </div>

        <div v-if="noticesStore.filteredNotices.length === 0" class="section_infobox">
          <p>Keine Mitteilungen gefunden.</p>
        </div>
      </div>

      <!-- Refresh Button -->
      <div class="section_actions">
        <button @click="noticesStore.refresh()" class="button" :disabled="noticesStore.loading">
          {{ noticesStore.loading ? 'Aktualisiere...' : 'Aktualisieren' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useNoticesStore } from '@stores/notices'

const noticesStore = useNoticesStore()

onMounted(() => {
  noticesStore.fetchNotices()
})

async function markAsRead(noticeId) {
  try {
    await noticesStore.markAsRead(noticeId)
  } catch (error) {
    alert('Fehler beim Markieren als gelesen')
  }
}
</script>

<style scoped>
.section_filter {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.section_filter .button.active {
  background-color: var(--ColorPrimary, #4E9DAE);
  color: white;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background-color: #f44336;
  color: white;
  border-radius: 12px;
  font-size: 0.875rem;
  margin-left: 1rem;
}

.notices-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.notice-item {
  padding: 1.5rem;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.notice--unread {
  border-left: 4px solid var(--ColorPrimary, #4E9DAE);
  background-color: #f0f9fb;
}

.notice-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.notice-date {
  color: #666;
  font-size: 0.875rem;
}

.notice-content {
  margin-bottom: 1rem;
  line-height: 1.6;
}

.notice-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notice-category {
  padding: 0.25rem 0.75rem;
  background-color: #e0e0e0;
  border-radius: 4px;
  font-size: 0.875rem;
}

.button--small {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}
</style>
