<template>
  <div class="section section--DashboardPage">
    <AppHeader
      title="Dashboard"
      description="Willkommen auf deinem ToTeam Dashboard! Hier findest du eine Übersicht über deine anstehenden Termine und heutigen Mahlzeiten."
    />

    <div class="section_content">
      <!-- Welcome Box -->
      <div class="section_infobox infobox--welcome">
        <p class="welcome_text">Willkommen zurück, <b>{{ authStore.user?.FirstName }}!</b></p>
        <div class="welcome_profileimage">
          <img
            v-if="authStore.user?.ProfileImage"
            :src="authStore.user.ProfileImage.URL"
            :alt="`Profilbild von ${authStore.user.FirstName}`"
            class="profile_image"
          >
          <img
            v-else
            :src="authStore.user?.Gravatar"
            alt="Standard Profilbild"
            class="profile_image"
          >
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="dashboardStore.loading" class="section_infobox">
        <p>Lade Dashboard-Daten...</p>
      </div>

      <!-- Error State -->
      <div v-if="dashboardStore.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ dashboardStore.error }}</p>
        <button @click="dashboardStore.refresh()" class="button">Erneut versuchen</button>
      </div>

      <!-- Today's Participations -->
      <div v-if="dashboardStore.hasEventsToday" class="section_infobox">
        <h2 class="hl2">Das steht heute an:</h2>
        <ul class="infobox_list agenda">
          <li v-for="participation in dashboardStore.todaysParticipations" :key="participation.ID" class="agenda--item">
            <h3 class="hl3">{{ participation.Parent.Title }}</h3>
            <p><b>Uhrzeit:</b> {{ participation.Parent.RenderTime }}</p>
            <p><b>Ort:</b> {{ participation.Parent.Location }}</p>
            <p v-if="participation.Parent.Description">{{ participation.Parent.Description }}</p>
            <router-link :to="`/calendar/event/${participation.Parent.ID}`" class="button">
              Termindetails anzeigen
            </router-link>
          </li>
        </ul>
      </div>

      <!-- Upcoming Events -->
      <div v-if="dashboardStore.hasUpcomingEvents" class="section_infobox">
        <h2 class="hl2">Deine anstehenden Termine:</h2>
        <ul class="infobox_list">
          <li
            v-for="event in dashboardStore.upcomingEvents"
            :key="event.ID"
            :class="{ accepted: event.Type === 'Accept', maybe: event.Type === 'Maybe' }"
          >
            <router-link :to="`/calendar/event/${event.ID}`">
              {{ event.Title }} - <i>{{ event.RenderDateWithTime }}</i>
            </router-link>
          </li>
        </ul>
      </div>

      <!-- Events Without Feedback -->
      <div v-if="dashboardStore.needsFeedback" class="section_infobox">
        <h2 class="hl2">Termine ohne deine Rückmeldung:</h2>
        <ul class="infobox_list">
          <li v-for="event in dashboardStore.eventsWithoutFeedback.slice(0, 10)" :key="event.ID">
            <router-link :to="`/calendar/event/${event.ID}`">
              {{ event.RenderTitle }} - <i>{{ event.RenderDateWithTime }}</i>
            </router-link>
          </li>
        </ul>
      </div>

      <!-- Refresh Button -->
      <div class="section_actions">
        <button @click="dashboardStore.refresh()" class="button" :disabled="dashboardStore.loading">
          {{ dashboardStore.loading ? 'Aktualisiere...' : 'Aktualisieren' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@stores/auth'
import { useDashboardStore } from '@stores/dashboard'
import AppHeader from '@components/AppHeader.vue'

const authStore = useAuthStore()
const dashboardStore = useDashboardStore()

onMounted(() => {
  dashboardStore.fetchDashboardData()
})
</script>
