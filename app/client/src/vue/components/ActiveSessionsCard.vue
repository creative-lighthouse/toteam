<template>
  <div class="active-sessions">
    <h3 class="active-sessions_title">Aktive Sitzungen</h3>

    <div v-if="loading" class="empty-hint">Wird geladen…</div>
    <div v-else-if="sessions.length === 0" class="empty-hint">Keine aktiven Sitzungen gefunden.</div>

    <ul v-else class="active-sessions_list">
      <li v-for="session in sessions" :key="session.ID" class="active-sessions_item">
        <div class="active-sessions_info">
          <strong>{{ session.DeviceLabel }}</strong>
          <span v-if="session.IsCurrent" class="active-sessions_current-badge">Dieses Gerät</span>
          <span class="active-sessions_meta">
            {{ session.IPAddress }} · zuletzt aktiv {{ formatDate(session.LastUsedAt) }}
          </span>
        </div>
        <AppButton
          v-if="!session.IsCurrent"
          size="small"
          variant="danger"
          :disabled="revokingID === session.ID"
          @click="revoke(session)"
        >
          {{ revokingID === session.ID ? '…' : 'Abmelden' }}
        </AppButton>
      </li>
    </ul>

    <p v-if="error" class="status-text status-text--error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { apiGet, apiDelete } from '@utils/api'
import AppButton from '@components/AppButton.vue'

const sessions = ref([])
const loading = ref(false)
const error = ref(null)
const revokingID = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await apiGet('/auth/sessions', false)
    sessions.value = data.sessions ?? []
  } catch (err) {
    error.value = 'Sitzungen konnten nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

async function revoke(session) {
  revokingID.value = session.ID
  try {
    const result = await apiDelete(`/auth/removeSession/${session.ID}`)
    if (result.success) {
      sessions.value = sessions.value.filter(s => s.ID !== session.ID)
    } else {
      error.value = result.error ?? 'Abmelden fehlgeschlagen.'
    }
  } catch (err) {
    error.value = 'Abmelden fehlgeschlagen.'
  } finally {
    revokingID.value = null
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  const d = new Date(dateStr.replace(' ', 'T'))
  return d.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>
