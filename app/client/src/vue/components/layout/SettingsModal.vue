<template>
  <AppModal ref="modal" class="settings-modal" title="Einstellungen" @close="close">
    <!-- Notifications -->
    <section class="settings-section">
      <h3 class="settings-section_title">Benachrichtigungen</h3>

      <div v-if="loadingPrefs" class="settings-section_loading">Lädt...</div>

      <template v-else>
        <AppToggle v-model="prefs.NotifyEvents" label="Termine" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyAnnouncements" label="Ankündigungen" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyMeals" label="Essensvorschläge" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyMaps" label="Lagepläne" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyApplications" label="Organisationsbewerbungen" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyInventory" label="Inventar-Ausleihen" field-class="settings-toggle" @update:model-value="savePrefs" />
      </template>
    </section>

    <!-- Appearance -->
    <section class="settings-section">
      <h3 class="settings-section_title">Erscheinungsbild</h3>

      <AppToggle v-model="darkMode" label="Dark Mode" field-class="settings-toggle" @update:model-value="applyDarkMode" />
    </section>
  </AppModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { apiGet, apiPost } from '@utils/api'
import { useUiStore } from '@stores/ui'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'

const uiStore = useUiStore()

const modal = ref(null)
const loadingPrefs = ref(true)

const prefs = reactive({
  NotifyEvents: true,
  NotifyAnnouncements: true,
  NotifyMeals: true,
  NotifyMaps: true,
  NotifyApplications: true,
  NotifyInventory: true,
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
