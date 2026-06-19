<template>
    <Transition name="pwa-banner">
        <div v-if="visible" class="pwa-banner" role="complementary" aria-label="App installieren">
            <div class="pwa-banner_content">
                <div class="pwa-banner_icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                        <path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/>
                    </svg>
                </div>
                <div class="pwa-banner_text">
                    <strong class="pwa-banner_title">{{ title }}</strong>
                    <span v-if="mode === 'ios'" class="pwa-banner_subtitle pwa-banner_subtitle--ios">
                        Tippe auf
                        <svg class="pwa-banner_share-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="currentColor" width="16" height="16">
                            <path d="M30.3 13.7L25 8.4l-5.3 5.3-1.4-1.4L25 5.6l6.7 6.7z"/>
                            <path d="M24 7h2v21h-2z"/>
                            <path d="M35 40H15c-1.7 0-3-1.3-3-3V19c0-1.7 1.3-3 3-3h7v2h-7c-.6 0-1 .4-1 1v18c0 .6.4 1 1 1h20c.6 0 1-.4 1-1V19c0-.6-.4-1-1-1h-7v-2h7c1.7 0 3 1.3 3 3v18c0 1.7-1.3 3-3 3z"/>
                        </svg>
                        und dann <em>„Zum Home-Bildschirm"</em>
                    </span>
                    <span v-else class="pwa-banner_subtitle">{{ subtitle }}</span>
                </div>
            </div>
            <div class="pwa-banner_actions">
                <a v-if="mode === 'open'" :href="appUrl" target="_blank" rel="noopener" class="button pwa-banner_btn">Öffnen</a>
                <button v-else-if="mode === 'install'" class="button pwa-banner_btn" @click="install">Installieren</button>
                <button class="pwa-banner_close" @click="dismiss" aria-label="Schließen">&#x2715;</button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const STORAGE_KEY = 'pwa_banner_dismissed_date'

const deferredPrompt = ref(null)
const mode = ref(null) // 'install' | 'open' | 'ios'
const visible = ref(false)

const appUrl = window.location.href

const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream

const title = computed(() => {
    if (mode.value === 'open') return 'Als App öffnen'
    return 'App installieren'
})

const subtitle = computed(() =>
    mode.value === 'open'
        ? 'ToTeam ist als App auf deinem Gerät verfügbar.'
        : 'Installiere ToTeam für schnelleren Zugriff.'
)

function isDismissedToday() {
    return localStorage.getItem(STORAGE_KEY) === new Date().toDateString()
}

function dismiss() {
    visible.value = false
    localStorage.setItem(STORAGE_KEY, new Date().toDateString())
}

async function install() {
    if (!deferredPrompt.value) return
    deferredPrompt.value.prompt()
    const { outcome } = await deferredPrompt.value.userChoice
    deferredPrompt.value = null
    if (outcome === 'accepted') {
        visible.value = false
    }
}

function handleBeforeInstallPrompt(e) {
    e.preventDefault()
    deferredPrompt.value = e
    if (!isDismissedToday()) {
        mode.value = 'install'
        visible.value = true
    }
}

onMounted(() => {
    // Already running as PWA → never show
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        return
    }

    if (isDismissedToday()) return

    if (isIos) {
        mode.value = 'ios'
        visible.value = true
        return
    }

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)

    // If no install prompt fires within 1s, the app is likely already installed
    setTimeout(() => {
        if (!deferredPrompt.value && !visible.value) {
            mode.value = 'open'
            visible.value = true
        }
    }, 1000)
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
})
</script>
