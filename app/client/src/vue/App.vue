<template>
  <div id="vue-app">
    <AppHeader :title="pageHeaderStore.title" :description="pageHeaderStore.description" />

    <main class="area_content main">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in" @after-enter="onPageEntered">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <!-- Menü nach dem Inhalt: optisch unten fixiert, im DOM (und damit per Tab) hinter der Seite -->
    <div class="area_header" v-if="authStore.isAuthenticated">
      <AppMenu />
    </div>

    <PwaInstallBanner />
  </div>
</template>

<script setup>
import { watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppMenu from '@components/layout/AppMenu.vue'
import AppHeader from '@components/layout/AppHeader.vue'
import PwaInstallBanner from '@components/layout/PwaInstallBanner.vue'

const authStore = useAuthStore()
const pageHeaderStore = usePageHeaderStore()

// Auth check is handled by router guard, no need to call it here

// ── Seitenwechsel für Tastatur und Screenreader ─────────────────────────────

// Titel im Browser-Tab folgt der Seitenüberschrift
watch(() => pageHeaderStore.title, (title) => {
  document.title = title ? `${title} – ToTeam` : 'ToTeam'
}, { immediate: true })

// Nach einem Seitenwechsel den Fokus auf die Seitenüberschrift setzen — sonst
// bleibt er auf dem entfernten Element hängen und die neue Seite wird nicht
// angesagt. Nicht beim ersten Laden und nicht, wenn sich nur die Query ändert
// (z.B. ein Termin im Kalender geöffnet/geschlossen wird).
const router = useRouter()
let focusPending = false
let fallbackTimer = null

function focusPageTitle() {
  focusPending = false
  clearTimeout(fallbackTimer)
  // Ist ein Modal offen (z.B. per Link geöffneter Termin), behält es den Fokus
  if (document.querySelector('dialog[open]')) return
  document.querySelector('.AppHeader_title')?.focus({ preventScroll: true })
}

router.afterEach((to, from) => {
  if (!from.matched.length || to.path === from.path) return
  focusPending = true
  // Gleiche Seite mit anderem Parameter (z.B. /food/meal/1 → /2): keine Transition
  clearTimeout(fallbackTimer)
  fallbackTimer = setTimeout(() => { if (focusPending) focusPageTitle() }, 600)
})

function onPageEntered() {
  if (focusPending) focusPageTitle()
}
</script>
