<template>
  <AppModal ref="modal" class="settings-modal" title="Einstellungen" @close="close">
    <!-- Notifications -->
    <section class="settings-section">
      <h3 class="settings-section_title">Benachrichtigungen</h3>

      <div v-if="loadingPrefs" class="settings-section_loading">Lädt...</div>

      <template v-else>
        <label class="settings-toggle">
          <span class="settings-toggle_label">Termine</span>
          <input type="checkbox" v-model="prefs.NotifyEvents" @change="savePrefs" class="settings-toggle_input">
          <span class="settings-toggle_track"></span>
        </label>

        <label class="settings-toggle">
          <span class="settings-toggle_label">Ankündigungen</span>
          <input type="checkbox" v-model="prefs.NotifyAnnouncements" @change="savePrefs" class="settings-toggle_input">
          <span class="settings-toggle_track"></span>
        </label>

        <label class="settings-toggle">
          <span class="settings-toggle_label">Essensvorschläge</span>
          <input type="checkbox" v-model="prefs.NotifyMeals" @change="savePrefs" class="settings-toggle_input">
          <span class="settings-toggle_track"></span>
        </label>

        <label class="settings-toggle">
          <span class="settings-toggle_label">Lagepläne</span>
          <input type="checkbox" v-model="prefs.NotifyMaps" @change="savePrefs" class="settings-toggle_input">
          <span class="settings-toggle_track"></span>
        </label>

        <label class="settings-toggle">
          <span class="settings-toggle_label">Organisationsbewerbungen</span>
          <input type="checkbox" v-model="prefs.NotifyApplications" @change="savePrefs" class="settings-toggle_input">
          <span class="settings-toggle_track"></span>
        </label>
      </template>
    </section>

    <!-- Appearance -->
    <section class="settings-section">
      <h3 class="settings-section_title">Erscheinungsbild</h3>

      <label class="settings-toggle">
        <span class="settings-toggle_label">Dark Mode</span>
        <input type="checkbox" v-model="darkMode" @change="applyDarkMode" class="settings-toggle_input">
        <span class="settings-toggle_track"></span>
      </label>
    </section>
  </AppModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { apiGet, apiPost } from '@utils/api'
import { useUiStore } from '@stores/ui'
import AppModal from '@components/AppModal.vue'

const uiStore = useUiStore()

const modal = ref(null)
const loadingPrefs = ref(true)

const prefs = reactive({
  NotifyEvents: true,
  NotifyAnnouncements: true,
  NotifyMeals: true,
  NotifyMaps: true,
})

const darkMode = ref(false)

onMounted(() => {
  darkMode.value = document.body.classList.contains('theme--dark')
})

async function open() {
  modal.value?.open()
  await loadPrefs()
}

function close() {
  modal.value?.close()
}

async function loadPrefs() {
  try {
    loadingPrefs.value = true
    const data = await apiGet('/settings', false)
    prefs.NotifyEvents = data.NotifyEvents
    prefs.NotifyAnnouncements = data.NotifyAnnouncements
    prefs.NotifyMeals = data.NotifyMeals
    prefs.NotifyMaps = data.NotifyMaps
  } catch (err) {
    console.error('Could not load settings:', err)
  } finally {
    loadingPrefs.value = false
  }
}

async function savePrefs() {
  try {
    await apiPost('/settings', { ...prefs })
  } catch (err) {
    console.error('Could not save settings:', err)
  }
}

function applyDarkMode() {
  document.body.classList.toggle('theme--dark', darkMode.value)
  localStorage.setItem('theme', darkMode.value ? 'dark' : 'light')
}

defineExpose({ open, close })
</script>
