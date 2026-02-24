<template>
  <header>
    <!-- Primary Menu -->
    <ul class="primary_menu">
      <li>
        <router-link to="/" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Dashboard' }">
          <div class="nav_icon">
            <img 
              class="nav_image" 
              :src="$route.name === 'Dashboard' ? dashboardTotem : dashboardTotemInactive" 
              alt="ToTeam Logo - Zum Dashboard"
            >
          </div>
          <p class="nav_title">Dashboard</p>
        </router-link>
      </li>
      
      <li>
        <router-link to="/notices" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Notices' }">
          <div class="nav_icon">
            <img 
              :src="$route.name === 'Notices' ? nachrichtenTotem : nachrichtenTotemInactive" 
              alt="Nachrichten Icon" 
              class="nav_image"
            >
            <p v-if="noticesStore.unreadCount > 0" class="nav_badge">{{ noticesStore.unreadCount }}</p>
          </div>
          <p class="nav_title">Wichtiges</p>
        </router-link>
      </li>
      
      <li>
        <router-link to="/calendar" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Calendar' }">
          <div class="nav_icon">
            <img 
              class="nav_image" 
              :src="$route.name === 'Calendar' ? kalenderTotem : kalenderTotemInactive" 
              alt="Kalender Icon"
            >
          </div>
          <p class="nav_title">Kalender</p>
        </router-link>
      </li>
      
      <li>
        <div class="nav_link" @click="toggleSecondaryMenu">
          <div class="nav_icon">
            <div class="nav_button" :class="{ active: isSecondaryMenuOpen }">
              <span></span>
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
          <p class="nav_title">Mehr</p>
        </div>
      </li>
    </ul>
    
    <!-- Secondary Menu -->
    <div class="secondarynav_wrap">
      <div class="secondarynav">
        <div class="nav_top">
          <img class="nav_logo" :src="dashboardTotem" alt="ToTeam Logo - Zum Dashboard">
          <h2 class="nav_title">ToTeam</h2>
        </div>
        <ul class="secondary_menu">
          <li>
            <router-link to="/food" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Food' }" @click="closeSecondaryMenu">
              <div class="nav_icon">
                <img :src="essenTotem" alt="Essen Icon" class="nav_image">
              </div>
              <p class="nav_title">Essen</p>
            </router-link>
          </li>
          
          <li>
            <router-link to="/links" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Links' }" @click="closeSecondaryMenu">
              <div class="nav_icon">
                <img :src="downloadsTotem" alt="Links Icon" class="nav_image">
              </div>
              <p class="nav_title">Links & Downloads</p>
            </router-link>
          </li>
          
          <li>
            <router-link to="/map" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Map' }" @click="closeSecondaryMenu">
              <div class="nav_icon">
                <img :src="kartenTotem" alt="Karte Icon" class="nav_image">
              </div>
              <p class="nav_title">Lagepläne</p>
            </router-link>
          </li>
        </ul>
        <p class="version_note"><i>ToTeam Vue</i> <kbd>BETA</kbd></p>
        <div class="nav_profile_wrap">
          <router-link to="/profile" class="nav_profile" @click="closeSecondaryMenu">
            <div class="nav_icon nav_icon--profile">
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
            <div class="nav_text">
              <p class="nav_title">{{ authStore.user?.FirstName }}</p>
              <p class="nav_subtitle">Profil ansehen</p>
            </div>
          </router-link>
          <button @click="handleLogout" class="nav_logout" title="Abmelden">
            <div class="nav_icon nav_icon--logout">
              <img :src="actionLogout" alt="Abmelden Icon" class="logout_image">
            </div>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useNoticesStore } from '@stores/notices'

// Import totem icons
import dashboardTotem from '../../../icons/totems/dashboard_totem.png'
import dashboardTotemInactive from '../../../icons/totems/dashboard_totem_inactive.png'
import nachrichtenTotem from '../../../icons/totems/nachrichten_totem.png'
import nachrichtenTotemInactive from '../../../icons/totems/nachrichten_totem_inactive.png'
import kalenderTotem from '../../../icons/totems/kalender_totem.png'
import kalenderTotemInactive from '../../../icons/totems/kalender_totem_inactive.png'
import essenTotem from '../../../icons/totems/essen_totem.png'
import downloadsTotem from '../../../icons/totems/downloads_totem.png'
import kartenTotem from '../../../icons/totems/karten_totem.png'
import actionLogout from '../../../icons/actions/action_logout.svg'

const router = useRouter()
const authStore = useAuthStore()
const noticesStore = useNoticesStore()
const isSecondaryMenuOpen = ref(false)

function toggleSecondaryMenu() {
  // Toggle body class like the original JavaScript does
  document.body.classList.toggle('secnav--open')
  isSecondaryMenuOpen.value = document.body.classList.contains('secnav--open')
}

function closeSecondaryMenu() {
  document.body.classList.remove('secnav--open')
  isSecondaryMenuOpen.value = false
}

async function handleLogout() {
  await authStore.logout()
  closeSecondaryMenu()
  router.push({ name: 'Login' })
}

onMounted(() => {
  if (authStore.isAuthenticated) {
    // Load unread count for badge - but don't fail if it errors
    noticesStore.fetchNotices().catch(err => {
      console.warn('Could not fetch notices for badge:', err)
    })
  }
})

onUnmounted(() => {
  // Clean up body class when component is destroyed
  closeSecondaryMenu()
})
</script>

<style scoped>
/* All styles are inherited from main.scss - this component uses existing classes */
</style>
