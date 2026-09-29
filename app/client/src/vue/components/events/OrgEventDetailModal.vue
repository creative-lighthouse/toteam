<template>
  <!-- Ein Event mit seinen Angaben und Terminen; Verwalter können es bearbeiten oder löschen -->
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
        <span v-if="event.RangeStart">· {{ formatOrgEventRange(event) }}</span>
        <span class="org-event-detail-modal_badge">{{ event.IsPublic ? 'Öffentlich' : 'Intern' }}</span>
      </div>

      <div v-if="event.ImageURL || hasDetails" class="org-event-detail-modal_info">
        <img v-if="event.ImageURL" :src="event.ImageURL" alt="" class="org-event-detail-modal_image">
        <dl v-if="hasDetails" class="org-event-detail-modal_details">
          <template v-if="event.Location">
            <dt>Ort</dt>
            <dd>{{ event.Location }}</dd>
          </template>
          <template v-if="event.TypeTitle">
            <dt>Art</dt>
            <dd>{{ event.TypeTitle }}</dd>
          </template>
          <template v-if="event.AgeGroups?.length">
            <dt>Empfohlen für</dt>
            <dd>{{ event.AgeGroups.map(g => g.Title).join(', ') }}</dd>
          </template>
        </dl>
      </div>

      <template v-if="event.Prices?.length">
        <h3 class="org-event-detail-modal_heading">Preise</h3>
        <table class="org-event-detail-modal_prices">
          <tbody>
            <tr v-for="price in event.Prices" :key="price.ID">
              <td>{{ price.Title }}</td>
              <td>{{ formatPrice(price.Price) }}</td>
            </tr>
          </tbody>
        </table>
      </template>

      <h3 class="org-event-detail-modal_heading">Termine</h3>
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
      <template v-if="event?.CanManage">
        <AppButton variant="danger" :disabled="saving" @click="remove">Löschen</AppButton>
        <AppButton variant="secondary" @click="edit">Bearbeiten</AppButton>
      </template>
      <AppButton variant="secondary" @click="close">Schließen</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useOrgEventsStore } from '@stores/orgEvents'
import { formatDate } from '@utils/inventory'
import { formatEventRange, isMultiDay } from '@utils/eventDates'
import { formatOrgEventRange, formatPrice } from '@utils/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ParticipationIcon from '@components/calendar/ParticipationIcon.vue'

const emit = defineEmits(['edit'])

const router = useRouter()
const orgEventsStore = useOrgEventsStore()

const modal = ref(null)
const event = ref(null)
const appointments = ref([])
const loading = ref(false)
const error = ref(null)
const saving = ref(false)

const hasDetails = computed(() => !!(event.value?.Location || event.value?.TypeTitle || event.value?.AgeGroups?.length))

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

function edit() {
  const current = event.value
  close()
  emit('edit', current)
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
