<template>
  <!-- Ein Event mit allen seinen Terminen; Verwalter können es umbenennen oder löschen -->
  <AppModal ref="modal" class="org-event-detail-modal" :title="event?.Title ?? 'Event'" @close="close">
    <template v-if="event">
      <div class="org-event-detail-modal_meta">
        <AppOrgLogo
          v-if="event.OrganizationTitle"
          :src="event.OrganizationLogoURL"
          :alt="event.OrganizationTitle"
          :name="event.OrganizationTitle"
          :size="22"
        />
        <span>{{ event.OrganizationTitle }}</span>
        <span v-if="event.DateStart">· {{ formatDateRange(event.DateStart, event.DateEnd) }}</span>
      </div>

      <form v-if="editing" id="org-event-rename-form" class="modalform org-event-detail-modal_rename" @submit.prevent="saveTitle">
        <label class="field">
          Titel
          <input v-model="title" type="text" required />
        </label>
      </form>

      <p v-if="loading" class="org-event-detail-modal_empty">Lade Termine…</p>
      <ul v-else-if="appointments.length" class="org-event-detail-modal_list">
        <li v-for="appt in appointments" :key="appt.ID">
          <button
            type="button"
            class="org-event-appointment"
            :class="{ 'org-event-appointment--cancelled': appt.Status === 'Cancelled', 'org-event-appointment--past': isPast(appt) }"
            @click="openAppointment(appt)"
          >
            <span class="org-event-appointment_date">{{ formatAppointmentDate(appt) }}</span>
            <span class="org-event-appointment_title">
              {{ appt.Title }}
              <span v-if="appt.Status === 'Cancelled'" class="org-event-appointment_badge">Abgesagt</span>
              <span v-else-if="appt.Status === 'Suggested'" class="org-event-appointment_badge">Vorschlag</span>
            </span>
            <span v-if="appt.Location || appt.EventType" class="org-event-appointment_info">
              {{ [appt.EventType, appt.Location].filter(Boolean).join(' · ') }}
            </span>
            <ParticipationIcon :participationType="appt.UserResponse?.toLowerCase() ?? 'none'" />
          </button>
        </li>
      </ul>
      <p v-else class="org-event-detail-modal_empty">Diesem Event sind noch keine Termine zugeordnet.</p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </template>

    <template #actions>
      <template v-if="editing">
        <AppButton variant="secondary" @click="editing = false">Abbrechen</AppButton>
        <AppButton type="submit" form="org-event-rename-form" variant="primary" :disabled="saving">
          {{ saving ? 'Speichern…' : 'Speichern' }}
        </AppButton>
      </template>
      <template v-else>
        <template v-if="event?.CanManage">
          <AppButton variant="danger" :disabled="saving" @click="remove">Löschen</AppButton>
          <AppButton variant="secondary" @click="startEditing">Umbenennen</AppButton>
        </template>
        <AppButton variant="secondary" @click="close">Schließen</AppButton>
      </template>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOrgEventsStore } from '@stores/orgEvents'
import { formatDate, formatDateRange } from '@utils/inventory'
import { formatEventRange, isMultiDay } from '@utils/eventDates'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ParticipationIcon from '@components/calendar/ParticipationIcon.vue'

const router = useRouter()
const orgEventsStore = useOrgEventsStore()

const modal = ref(null)
const event = ref(null)
const appointments = ref([])
const loading = ref(false)
const error = ref(null)
const editing = ref(false)
const title = ref('')
const saving = ref(false)

const today = () => new Date().toISOString().slice(0, 10)

function isPast(appt) {
  return (appt.DateEnd || appt.DateStart) < today()
}

function formatAppointmentDate(appt) {
  if (isMultiDay(appt)) return formatEventRange(appt, { weekday: true })
  const time = !appt.AllDay && appt.TimeStart ? `, ${appt.TimeStart.slice(0, 5)}` : ''
  return formatDate(appt.DateStart) + time
}

async function open(summary) {
  event.value = summary
  appointments.value = []
  error.value = null
  editing.value = false
  modal.value?.open()

  loading.value = true
  try {
    const res = await orgEventsStore.fetchEvent(summary.ID)
    event.value = res.event
    appointments.value = res.appointments ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function close() {
  modal.value?.close()
}

function openAppointment(appt) {
  close()
  router.push({ name: 'Calendar', query: { date: appt.DateStart, eventID: appt.ID } })
}

function startEditing() {
  title.value = event.value.Title
  error.value = null
  editing.value = true
}

async function saveTitle() {
  if (!title.value.trim()) return
  saving.value = true
  error.value = null
  try {
    event.value = await orgEventsStore.renameEvent(event.value.ID, title.value.trim())
    editing.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirm(`Event „${event.value.Title}“ wirklich löschen? Die Termine bleiben im Kalender erhalten.`)) return
  saving.value = true
  error.value = null
  try {
    await orgEventsStore.deleteEvent(event.value.ID)
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
