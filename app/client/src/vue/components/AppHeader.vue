<template>
  <header class="app-header">
    <div class="header_content">
      <div class="header_logo">
        <router-link to="/">
          <h1>ToTeam</h1>
        </router-link>
      </div>
      
      <nav class="header_nav" v-if="authStore.isAuthenticated">
        <router-link to="/" class="nav-link" :class="{ active: $route.name === 'Dashboard' }">
          Dashboard
        </router-link>
        <router-link to="/calendar" class="nav-link" :class="{ active: $route.name === 'Calendar' }">
          Kalender
        </router-link>
        <router-link to="/food" class="nav-link" :class="{ active: $route.name === 'Food' }">
          Essen
        </router-link>
        <router-link to="/notices" class="nav-link" :class="{ active: $route.name === 'Notices' }">
          Mitteilungen
          <span v-if="noticesStore.unreadCount > 0" class="badge">
            {{ noticesStore.unreadCount }}
          </span>
        </router-link>
        <router-link to="/map" class="nav-link" :class="{ active: $route.name === 'Map' }">
          Karte
        </router-link>
        <router-link to="/links" class="nav-link" :class="{ active: $route.name === 'Links' }">
          Links
        </router-link>
        <router-link to="/profile" class="nav-link" :class="{ active: $route.name === 'Profile' }">
          Profil
        </router-link>
      </nav>
    </div>
  </header>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@stores/auth'
import { useNoticesStore } from '@stores/notices'

const authStore = useAuthStore()
const noticesStore = useNoticesStore()

onMounted(() => {
  if (authStore.isAuthenticated) {
    // Load unread count for badge
    noticesStore.fetchNotices()
  }
})
</script>

<style scoped>
.app-header {
  background-color: var(--ColorPrimary, #4E9DAE);
  color: white;
  padding: 1rem 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header_content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header_logo h1 {
  margin: 0;
  font-size: 1.5rem;
  color: white;
}

.header_logo a {
  text-decoration: none;
  color: white;
}

.header_nav {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.nav-link {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  transition: background-color 0.2s;
  position: relative;
}

.nav-link:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-link.active {
  background-color: rgba(255, 255, 255, 0.2);
  font-weight: 500;
}

.badge {
  position: absolute;
  top: 0;
  right: 0;
  background-color: #f44336;
  color: white;
  border-radius: 10px;
  padding: 0.125rem 0.375rem;
  font-size: 0.75rem;
  font-weight: bold;
  transform: translate(25%, -25%);
}

@media (max-width: 768px) {
  .header_content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .header_nav {
    width: 100%;
    justify-content: center;
  }
  
  .nav-link {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
  }
}
</style>
