<template>
  <div id="vue-app">
    <div class="area_header" v-if="authStore.isAuthenticated">
      <AppMenu />
    </div>

    <AppHeader :title="pageHeaderStore.title" :description="pageHeaderStore.description" />

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
import { usePageHeaderStore } from '@stores/pageHeader'
import AppMenu from './components/AppMenu.vue'
import AppHeader from './components/AppHeader.vue'

const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

onMounted(() => {
  authStore.checkAuth()
})
</script>
