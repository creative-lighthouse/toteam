<template>
  <div class="section section--DashboardPage">
    <div class="section_content">
      <!-- Welcome Box -->
      <div class="section_infobox infobox--welcome">
        <p class="welcome_text">
          Willkommen zurück, <b>{{ authStore.user?.FirstName }} {{ authStore.user?.Surname }}!</b>
        </p>
        <div class="welcome_profileimage">
          <img
            v-if="authStore.user?.ProfileImage?.URL"
            :src="authStore.user.ProfileImage.URL"
            :alt="`Profilbild von ${authStore.user.FirstName}`"
          >
          <img
            v-else
            :src="authStore.user?.Gravatar"
            alt="Standard Profilbild"
          >
        </div>
      </div>

      <!-- Error -->
      <div v-if="hasError && !isLoading" class="section_infobox">
        <p>Fehler beim Laden der Daten.</p>
        <AppButton variant="primary" @click="refresh()">Erneut versuchen</AppButton>
      </div>

      <!-- Aktuelle Ankündigungen -->
      <div v-if="dashboardStore.hasLatestAnnouncements" class="section_infobox">
        <h2 class="hl2 dashboard-announcements_title">Aktuelle Ankündigungen</h2>
        <div class="announcements-list">
          <AnnouncementCard
            v-for="announcement in dashboardStore.latestAnnouncements"
            :key="announcement.ID"
            :announcement="announcement"
            @click="openAnnouncement"
          />
        </div>
        <router-link to="/announcements" class="section_infobox_footer dashboard-announcements_footer">Alle Ankündigungen →</router-link>
      </div>

      <!-- Das steht heute an -->
      <div v-if="todaysEvents.length" class="section_infobox">
        <h2 class="hl2">Das steht heute an</h2>
        <ul class="infobox_list infobox_list--events">
          <li v-for="event in todaysEvents" :key="event.ID">
            <EventCard :event="event" :date-display="formatDate(event.DateStart)" @click="openEvent" />
            <ul v-if="event.Meals?.length" class="event-meals-list">
              <li v-for="meal in event.Meals" :key="meal.ID">
                <router-link :to="`/food/meal/${meal.ID}`" class="event-meal-link">
                  <span class="event-meal-time">{{ meal.RenderTime }} Uhr</span>
                  <span class="event-meal-name">{{ meal.Title }}</span>
                  <span class="event-meal-response" :class="mealResponseClass(meal.UserResponse)">
                    {{ mealResponseLabel(meal.UserResponse) }}
                  </span>
                  <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" style="opacity:.4;flex-shrink:0">
                    <path d="M6.22 3.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L9.94 8 6.22 4.28a.75.75 0 010-1.06z"/>
                  </svg>
                </router-link>
              </li>
            </ul>
          </li>
        </ul>
        <div class="section_infobox_footer">
          <router-link to="/calendar">Zum Kalender →</router-link>
        </div>
      </div>

      <!-- Meine zugesagten Beiträge -->
      <div v-if="dashboardStore.hasUpcomingContributions" class="section_infobox">
        <h2 class="hl2">Meine zugesagten Beiträge</h2>
        <ul class="infobox_list infobox_list--flat">
          <li v-for="item in dashboardStore.myUpcomingContributions" :key="`${item.foodId}-${item.mealId}`">
            <router-link :to="`/food/meal/${item.mealId}`" class="contribution-item">
              <div v-if="item.organizationLogoUrl" class="contribution-item_logo">
                <img :src="item.organizationLogoUrl" :alt="item.organizationTitle" />
              </div>
              <div class="contribution-item_info">
                <span class="contribution-item_food">{{ item.foodTitle }}</span>
                <span class="contribution-item_context">
                  {{ item.mealTitle }} · {{ formatDate(item.date) }}, {{ item.mealTime }} Uhr
                </span>
              </div>
              <span v-if="item.foodPreference !== 'None'" class="contribution-item_pref">
                {{ item.foodPreference === 'Vegetarian' ? '🥗' : '🌱' }}
              </span>
            </router-link>
          </li>
        </ul>
        <div class="section_infobox_footer">
          <router-link to="/food">Zum Essensplan →</router-link>
        </div>
      </div>

      <!-- Deine nächsten Termine -->
      <div v-if="upcomingAccepted.length" class="section_infobox">
        <h2 class="hl2">Deine nächsten Termine</h2>
        <ul class="infobox_list infobox_list--events">
          <li v-for="event in upcomingAccepted" :key="event.ID">
            <EventCard :event="event" :date-display="formatDate(event.DateStart)" @click="openEvent" />
          </li>
        </ul>
        <div class="section_infobox_footer">
          <router-link to="/calendar">Zum Kalender →</router-link>
        </div>
      </div>

      <!-- Offene Termine ohne Rückmeldung -->
      <div v-if="pendingFeedback.length" class="section_infobox">
        <h2 class="hl2">Offene Termine ohne Rückmeldung</h2>
        <ul class="infobox_list infobox_list--events">
          <li v-for="event in pendingFeedback" :key="event.ID">
            <EventCard :event="event" :date-display="formatDate(event.DateStart)" @click="openEvent" />
          </li>
        </ul>
        <div class="section_infobox_footer">
          <router-link to="/calendar">Zum Kalender →</router-link>
        </div>
      </div>

      <!-- Neues Feedback -->
      <div v-if="dashboardStore.hasNewFeedback" class="section_infobox">
        <h2 class="hl2">Neues Feedback</h2>
        <ul class="infobox_list infobox_list--flat">
          <li
            v-for="feedback in dashboardStore.newFeedback"
            :key="feedback.ID"
          >
            <div class="item-meta">
              <span class="item-from">
                von {{ feedback.IsAnonymous ? 'Anonym' : feedback.SenderName }} an dich
              </span>
              <span class="item-date">{{ formatDate(feedback.Created) }}</span>
            </div>
            <p class="item-title truncate">{{ feedback.Title }}</p>
          </li>
        </ul>
        <div class="section_infobox_footer">
          <a href="/app/suggestionbox">Zum Kummerkasten →</a>
        </div>
      </div>

      <!-- Refresh -->
      <div class="dashboard-refresh">
        <button
          @click="refresh()"
          class="refresh-button"
          :class="{ 'is-loading': spinning }"
          title="Aktualisieren"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useDashboardStore } from '@stores/dashboard'
import { useEventsStore } from '@stores/events'
import { usePageHeaderStore } from '@stores/pageHeader'
import EventCard from '@components/EventCard.vue'
import AnnouncementCard from '@components/AnnouncementCard.vue'
import AppButton from '@components/AppButton.vue'

const router = useRouter()
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const eventsStore = useEventsStore()
usePageHeaderStore().setHeader('Dashboard', 'Willkommen auf deinem ToTeam Dashboard! Hier findest du eine Übersicht über deine anstehenden Termine und neuesten Aktivitäten.')

function openEvent(event) {
  router.push({ name: 'Calendar', query: { date: event.DateStart, eventID: event.ID } })
}

function openAnnouncement(announcement) {
  router.push({ name: 'AnnouncementDetail', params: { id: announcement.ID } })
}

const todaysEvents = computed(() =>
  eventsStore.upcomingEvents.filter(e => e.isToday() && (e.hasUserAccepted() || e.hasUserMaybe()))
)

const upcomingAccepted = computed(() =>
  eventsStore.upcomingEvents
    .filter(e => e.isFuture() && (e.hasUserAccepted() || e.hasUserMaybe()))
    .slice(0, 5)
)

const pendingFeedback = computed(() =>
  eventsStore.upcomingEvents
    .filter(e => !e.getUserParticipationType())
    .slice(0, 5)
)

const spinning = ref(false)
const isLoading = computed(() => dashboardStore.loading || eventsStore.loading)
const hasError = computed(() => !!(dashboardStore.error || eventsStore.error))

function mealResponseClass(response) {
  if (response === 'Accept')  return 'response--accept'
  if (response === 'Decline') return 'response--decline'
  return 'response--pending'
}

function mealResponseLabel(response) {
  if (response === 'Accept')  return '✓ Zugesagt'
  if (response === 'Decline') return '✗ Abgesagt'
  return '?'
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString
  return new Intl.DateTimeFormat('de-DE', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(date)
}

async function refresh() {
  if (spinning.value) return
  spinning.value = true
  const now = new Date()
  const y = now.getFullYear()
  const m = now.getMonth() + 1
  const nextM = m === 12 ? 1 : m + 1
  const nextY = m === 12 ? y + 1 : y
  await Promise.all([
    dashboardStore.refresh(),
    eventsStore.fetchEvents(y, m, true),
    eventsStore.fetchEvents(nextY, nextM, true),
    new Promise(resolve => setTimeout(resolve, 650)),
  ])
  spinning.value = false
}

onMounted(() => {
  const now = new Date()
  const y = now.getFullYear()
  const m = now.getMonth() + 1
  const nextM = m === 12 ? 1 : m + 1
  const nextY = m === 12 ? y + 1 : y
  dashboardStore.fetchDashboardData()
  eventsStore.fetchEvents(y, m)
  eventsStore.fetchEvents(nextY, nextM)
})
</script>
