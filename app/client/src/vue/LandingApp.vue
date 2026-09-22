<template>
  <div class="landingpage">
    <header class="area_header">
      <div class="area_header-side area_header-side--left">
        <router-link class="app-button app-button--default app-button--secondary" to="/rechtliches">
          Impressum / Datenschutz
        </router-link>
      </div>

      <div class="section_logo">
        <router-link to="/"><img :src="logoUrl" alt="ToTeam Logo" /></router-link>
      </div>

      <div class="area_header-side area_header-side--right">
        <a class="app-button app-button--default app-button--primary" :href="ctaHref">{{ ctaLabel }}</a>
      </div>
    </header>

    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Plain runtime string, not a static `src` attribute, so Vite's asset-url
// transform doesn't try to resolve it as a JS module import.
const logoUrl = '/_resources/app/client/icons/ToTeam-Logo-x512.png'

const isLoggedIn = window.__TOTEAM_LOGGED_IN__ === true

const ctaHref = computed(() => (isLoggedIn ? '/app/dashboard' : '/app/login'))
const ctaLabel = computed(() => (isLoggedIn ? 'App starten' : 'Login'))
</script>
