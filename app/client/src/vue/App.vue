<template>
  <div id="vue-app">
    <div class="area_header" v-if="authStore.isAuthenticated">
      <AppMenu />
    </div>

    <main class="area_content main">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppMenu from './components/AppMenu.vue'

const authStore = useAuthStore()

onMounted(() => {
  // Check authentication status on app mount
  authStore.checkAuth()
})
</script>

<style>
/* View Transition Animation */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Ensure Vue app uses existing layout styles */
#vue-app {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 100%;
  grid-template-rows: auto 1fr auto;
  grid-template-areas:
    "header"
    "content"
    "footer";
  width: 100%;
  overflow-x: hidden;
  background-color: var(--ColorLightGray, #f5f5f5);
}

#vue-app .area_header {
  grid-area: header;
}

#vue-app .area_content {
  grid-area: content;
}
</style>
