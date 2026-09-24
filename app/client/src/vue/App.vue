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

    <PwaInstallBanner />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppMenu from '@components/layout/AppMenu.vue'
import AppHeader from '@components/layout/AppHeader.vue'
import PwaInstallBanner from '@components/layout/PwaInstallBanner.vue'

const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

// Auth check is handled by router guard, no need to call it here
</script>
