<template>
  <AppModal ref="modal" class="settings-modal" title="Einstellungen" @close="close">
    <!-- Notifications -->
    <section class="settings-section">
      <h3 class="settings-section_title">Benachrichtigungen</h3>

      <!-- Push auf diesem Gerät -->
      <div class="settings-push">
        <p class="settings-push_status">{{ pushStatusText }}</p>
        <AppButton
          v-if="pushPermission === 'default' || (pushPermission === 'granted' && !pushRegistered)"
          size="small"
          :disabled="pushBusy"
          @click="activatePush"
        >Aktivieren</AppButton>
        <AppButton
          v-else-if="pushPermission === 'granted'"
          size="small"
          variant="secondary"
          :disabled="pushBusy"
          @click="sendTestPush"
        >Testen</AppButton>
      </div>
      <p v-if="pushMessage" class="settings-push_message" role="status">{{ pushMessage }}</p>

      <div v-if="loadingPrefs" class="settings-section_loading">Lädt...</div>

      <template v-else>
        <AppToggle v-model="prefs.NotifyEvents" label="Termine" field-class="settings-toggle" @update:model-value="savePrefs" />
        <AppToggle v-model="prefs.NotifyAnnouncements" label="Beiträge von Organisationen" field-class="settings-toggle" @update:model-value="savePrefs" />
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
import { ref, reactive, computed } from 'vue'
import { apiGet, apiPost } from '@utils/api'
import { useUiStore } from '@stores/ui'
import { getPushPermission, enablePush, registerPushToken } from '@utils/push'
import { isDarkMode, setDarkMode } from '@utils/theme'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import AppButton from '@components/ui/AppButton.vue'

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

// Das Theme setzt app.js schon vor dem Mounten, daher hier direkt lesbar
const darkMode = ref(isDarkMode())

// ── Push auf diesem Gerät ──
const pushPermission = ref(getPushPermission())
const pushRegistered = ref(false)
const pushBusy = ref(false)
const pushMessage = ref('')

const pushStatusText = computed(() => {
  switch (pushPermission.value) {
    case 'unsupported':
      return /iPhone|iPad/.test(navigator.userAgent)
        ? 'Push-Benachrichtigungen gibt es auf iPhone/iPad nur, wenn ToTeam zum Home-Bildschirm hinzugefügt wurde.'
        : 'Dieser Browser unterstützt keine Push-Benachrichtigungen.'
    case 'denied':
      return 'Push-Benachrichtigungen sind für ToTeam im Browser blockiert. Du kannst sie in den Website-Einstellungen des Browsers wieder erlauben.'
    case 'granted':
      return pushRegistered.value
        ? 'Push-Benachrichtigungen sind auf diesem Gerät aktiv.'
        : 'Dieses Gerät ist noch nicht für Push-Benachrichtigungen registriert.'
    default:
      return 'Push-Benachrichtigungen auf diesem Gerät erhalten?'
  }
})

async function refreshPushState() {
  pushPermission.value = getPushPermission()
  pushRegistered.value = pushPermission.value === 'granted' && await registerPushToken()
}

async function activatePush() {
  pushBusy.value = true
  pushMessage.value = ''
  try {
    const ok = await enablePush()
    pushPermission.value = getPushPermission()
    pushRegistered.value = ok
    if (!ok && pushPermission.value === 'granted') {
      pushMessage.value = 'Das Gerät konnte nicht registriert werden. Bitte später erneut versuchen.'
    }
  } finally {
    pushBusy.value = false
  }
}

async function sendTestPush() {
  pushBusy.value = true
  pushMessage.value = ''
  try {
    const response = await apiPost('/notifications/test-notification')
    pushMessage.value = response?.success
      ? 'Test-Benachrichtigung verschickt — sie sollte gleich erscheinen.'
      : (response?.error || 'Test fehlgeschlagen.')
  } catch {
    pushMessage.value = 'Test fehlgeschlagen.'
  } finally {
    pushBusy.value = false
  }
}

async function open() {
  modal.value?.open()
  pushMessage.value = ''
  refreshPushState()
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
    prefs.NotifyApplications = data.NotifyApplications
    prefs.NotifyInventory = data.NotifyInventory
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
  setDarkMode(darkMode.value)
}

defineExpose({ open, close })
</script>
