<template>
  <div class="section section--DashboardPage">
    <AppHeader
      title="Dashboard"
      description="Willkommen auf deinem ToTeam Dashboard! Hier findest du eine Übersicht über deine anstehenden Termine und neuesten Aktivitäten."
    />

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
        <button @click="refresh()" class="button">Erneut versuchen</button>
      </div>

      <!-- Aktuelle Ankündigungen -->
      <div v-if="dashboardStore.hasLatestNotices" class="section_infobox">
        <h2 class="hl2">Aktuelle Ankündigungen</h2>
        <ul class="infobox_list infobox_list--flat">
          <li
            v-for="notice in dashboardStore.latestNotices"
            :key="notice.ID"
            class="notice-link"
            @click="openNotice(notice)"
          >
            <div class="item-meta">
              <span class="item-category">{{ notice.Category ?? 'Allgemein' }}</span>
              <div class="item-right">
                <span class="item-date">{{ formatDate(notice.Created) }}</span>
                <span v-if="notice.AuthorName" class="item-author">{{ notice.AuthorName }}</span>
              </div>
            </div>
            <p class="item-title truncate">{{ notice.Title }}</p>
          </li>
        </ul>
        <div class="card-footer">
          <router-link to="/notices">Alle Ankündigungen →</router-link>
        </div>
      </div>

      <!-- Das steht heute an -->
      <div v-if="todaysEvents.length" class="section_infobox">
        <h2 class="hl2">Das steht heute an</h2>
        <ul class="infobox_list infobox_list--events">
          <li v-for="event in todaysEvents" :key="event.ID">
            <EventCard :event="event" :date-display="formatDate(event.DateStart)" @click="openEvent" />
          </li>
        </ul>
        <div class="card-footer">
          <router-link to="/calendar">Zum Kalender →</router-link>
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
        <div class="card-footer">
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
        <div class="card-footer">
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
        <div class="card-footer">
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
import AppHeader from '@components/AppHeader.vue'
import EventCard from '@components/EventCard.vue'

const router = useRouter()
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const eventsStore = useEventsStore()

function openEvent(event) {
  router.push({ name: 'Calendar', query: { date: event.DateStart, eventID: event.ID } })
}

function openNotice(notice) {
  router.push({ name: 'Notices', query: { noticeID: notice.ID } })
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
