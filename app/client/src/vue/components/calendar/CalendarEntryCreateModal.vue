<template>
  <AppModal
    ref="modal"
    class="calendar-entry-create-modal"
    :title="headerTitle"
    :tabs="visibleTabs"
    :tab="activeTab"
    @update:tab="activeTab = $event"
    @close="close"
  >
        <!-- Tab: Abwesenheit -->

        <div class="dialog-infobox" v-if="activeTab === 'absence'">
            <form id="absence-form" class="modalform" @submit.prevent="submitAbsence">
                <DateTimeRangeField :model-value="absence" @update:model-value="v => Object.assign(absence, v)" />

                <label class="field field--3">
                    Wiederholung
                    <select v-model="absence.recurrence">
                        <option value="Never">Nie</option>
                        <option value="Daily">Täglich</option>
                        <option value="Weekly">Wöchentlich</option>
                        <option value="Monthly">Monatlich</option>
                        <option value="Yearly">Jährlich</option>
                    </select>
                </label>

                <label class="field">
                    Notiz
                    <textarea v-model="absence.note" rows="2" placeholder="Optionale Notiz"></textarea>
                </label>

                <div v-if="absenceError" class="form-error">{{ absenceError }}</div>
            </form>
        </div>

        <!-- Tab: Termin hinzufügen -->
        <div class="dialog-infobox" v-if="activeTab === 'appointment' && canManageContent">
            <form id="appointment-form" class="modalform" @submit.prevent="submitAppointment">
                <InviteePicker
                    ref="apptInviteePickerRef"
                    v-model:organization-ids="appt.organizationIds"
                    v-model:invited-member-ids="appt.invitedMemberIds"
                    :available-orgs="managedOrgs"
                    :auto-select-all="!editMode"
                />

                <template v-if="appt.organizationIds.length">
                    <label class="field">
                        Titel *
                        <input type="text" v-model="appt.title" required />
                    </label>

                    <label class="field">
                        Status *
                        <select v-model="appt.status">
                            <option value="Scheduled">Geplant</option>
                            <option value="Suggested">Vorgeschlagen</option>
                            <option value="Cancelled">Abgesagt</option>
                        </select>
                    </label>

                    <DateTimeRangeField
                        :model-value="appt"
                        @update:model-value="v => Object.assign(appt, v)"
                        time="toggle"
                    />

                    <label class="field">
                        Ort
                        <input type="text" v-model="appt.location" />
                    </label>

                    <label class="field">
                        Typ
                        <select v-model="appt.typeId">
                            <option value="">– kein Typ –</option>
                            <option v-for="t in appointmentTypes" :key="t.ID" :value="t.ID">{{ t.Title }}</option>
                        </select>
                    </label>

                    <label class="field">
                        Beschreibung
                        <textarea v-model="appt.description" rows="3"></textarea>
                    </label>

                    <AppToggle v-model="appt.enableMeals" label="Mahlzeiten" />
                    <AppToggle v-model="appt.enableAgenda" label="Tagesordnung" />

                    <div v-if="apptError" class="form-error">{{ apptError }}</div>
                </template>
            </form>
        </div>

        <!-- Tab: Terminfindung -->
        <div class="dialog-infobox" v-if="activeTab === 'poll' && canManageContent">
            <form id="poll-form" class="modalform" @submit.prevent="submitPoll">
                <InviteePicker
                    ref="pollInviteePickerRef"
                    v-model:organization-ids="poll.organizationIds"
                    v-model:invited-member-ids="poll.invitedMemberIds"
                    :available-orgs="managedOrgs"
                    :auto-select-all="!editMode"
                />

                <template v-if="poll.organizationIds.length">
                    <label class="field">
                        Titel *
                        <input type="text" v-model="poll.title" required />
                    </label>

                    <label class="field">
                        Ort
                        <input type="text" v-model="poll.location" />
                    </label>

                    <label class="field">
                        Beschreibung
                        <textarea v-model="poll.description" rows="3"></textarea>
                    </label>

                    <div class="field field--poll-options">
                        <span class="poll-options-header">
                            Terminoptionen *
                            <AppButton type="button" size="small" variant="secondary" @click="addPollOption">+ Option hinzufügen</AppButton>
                        </span>
                        <div v-for="(option, index) in poll.options" :key="index" class="poll-option">
                            <div class="modalform">
                                <DateTimeRangeField
                                    :model-value="option"
                                    @update:model-value="v => Object.assign(option, v)"
                                    time="toggle"
                                />
                            </div>

                            <AppIconButton
                                variant="danger"
                                aria-label="Option entfernen"
                                :disabled="poll.options.length <= 2"
                                @click="removePollOption(index)"
                            >
                                <span class="icon-mask" :style="trashIconStyle" />
                            </AppIconButton>
                        </div>
                    </div>

                    <div v-if="pollError" class="form-error">{{ pollError }}</div>
                </template>
            </form>
        </div>

        <div v-else-if="(activeTab === 'appointment' || activeTab === 'poll') && !canManageContent" class="dialog-infobox">
            <p>{{ noPermissionMessage }}</p>
        </div>

    <template #actions>
      <template v-if="activeTab === 'absence'">
        <AppIconButton
          v-if="editMode === 'absence'"
          variant="danger"
          class="calendar-entry-create-modal_delete"
          aria-label="Abwesenheit löschen"
          title="Löschen"
          :disabled="absenceSubmitting"
          @click="deleteAbsence"
        >
          <span class="icon-mask" :style="trashIconStyle" />
        </AppIconButton>
        <AppButton type="submit" form="absence-form" variant="primary" :disabled="absenceSubmitting">
          {{ absenceSubmitting ? 'Wird gespeichert…' : (editMode === 'absence' ? 'Speichern' : 'Abwesenheit eintragen') }}
        </AppButton>
      </template>
      <template v-else-if="activeTab === 'appointment' && canManageContent">
        <AppIconButton
          v-if="editMode === 'appointment'"
          variant="danger"
          class="calendar-entry-create-modal_delete"
          aria-label="Termin löschen"
          title="Löschen"
          :disabled="apptSubmitting"
          @click="deleteAppointment"
        >
          <span class="icon-mask" :style="trashIconStyle" />
        </AppIconButton>
        <AppButton type="submit" form="appointment-form" variant="primary" :disabled="apptSubmitting">
          {{ apptSubmitting ? 'Wird gespeichert…' : (editMode === 'appointment' ? 'Speichern' : 'Termin erstellen') }}
        </AppButton>
      </template>
      <template v-else-if="activeTab === 'poll' && canManageContent">
        <AppIconButton
          v-if="editMode === 'poll'"
          variant="danger"
          class="calendar-entry-create-modal_delete"
          aria-label="Terminfindung löschen"
          title="Löschen"
          :disabled="pollSubmitting"
          @click="deletePoll"
        >
          <span class="icon-mask" :style="trashIconStyle" />
        </AppIconButton>
        <AppButton type="submit" form="poll-form" variant="primary" :disabled="pollSubmitting">
          {{ pollSubmitting ? 'Wird gespeichert…' : (editMode === 'poll' ? 'Speichern' : 'Terminfindung erstellen') }}
        </AppButton>
      </template>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import { useEventsStore } from '@stores/events'
import { apiGet } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import InviteePicker from '@components/ui/InviteePicker.vue'
import DateTimeRangeField from '@components/ui/DateTimeRangeField.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const emit = defineEmits([
  'appointment-created', 'absence-created', 'poll-created',
  'appointment-updated', 'absence-updated', 'poll-updated',
  'appointment-deleted', 'absence-deleted', 'poll-deleted',
  'closed',
])

const savedThisSession = ref(false)

const modal = ref(null)
const activeTab = ref('absence')
const editMode = ref(null) // null | 'absence' | 'appointment' | 'poll'
const editId = ref(null)
const orgsStore = useOrganizationsStore()
const eventsStore = useEventsStore()
const apptInviteePickerRef = ref(null)
const pollInviteePickerRef = ref(null)

const appointmentTypes = ref([])

const ALL_TABS = [
  { id: 'absence', label: '+ Abwesenheit' },
  { id: 'appointment', label: '+ Termin' },
  { id: 'poll', label: '+ Terminfindung' },
]

// Tabs nur beim Anlegen anzeigen — beim Bearbeiten ist ohnehin nur der
// jeweilige Modus relevant, und AppModal blendet die Tableiste bei nur
// einem Eintrag automatisch aus.
const visibleTabs = computed(() => (editMode.value ? [] : ALL_TABS))

const memberOrgs = computed(() =>
  orgsStore.organizations.filter(o => o.MembershipStatus === 'member')
)

const managedOrgs = computed(() =>
  memberOrgs.value.filter(o => o.Permissions?.includes('CALENDAR_MANAGE'))
)

const canManageContent = computed(() => managedOrgs.value.length > 0)

const headerTitle = computed(() => {
  if (editMode.value === 'appointment') return 'Termin bearbeiten'
  if (editMode.value === 'poll') return 'Terminfindung bearbeiten'
  if (editMode.value === 'absence') return 'Abwesenheit bearbeiten'
  if (activeTab.value === 'poll') return 'Terminfindung erstellen'
  if (activeTab.value === 'appointment') return 'Termin hinzufügen'
  return 'Abwesenheit eintragen'
})

const calendarManagerRoleNames = computed(() => {
  const names = new Set()
  memberOrgs.value.forEach(o => {
    (o.CalendarManagerRoles || []).forEach(name => names.add(name))
  })
  return [...names]
})

const noPermissionMessage = computed(() => {
  const names = calendarManagerRoleNames.value
  if (!names.length) return 'Nur bestimmte Rollen können Termine erstellen.'
  return `Nur folgende Rollen können Termine erstellen: ${names.join(', ')}.`
})

// Absence form state
const absence = ref(resetAbsence())
const absenceSubmitting = ref(false)
const absenceError = ref('')

function resetAbsence(date = '') {
  return {
    dateStart: date,
    dateEnd: date,
    recurrence: 'Never',
    note: '',
  }
}

async function submitAbsence() {
  absenceError.value = ''
  absenceSubmitting.value = true
  try {
    const payload = {
      dateStart: absence.value.dateStart,
      dateEnd: absence.value.dateEnd || absence.value.dateStart,
      recurrence: absence.value.recurrence,
      note: absence.value.note || null,
    }
    savedThisSession.value = true
    if (editMode.value === 'absence') {
      await eventsStore.updateAbsence(editId.value, payload)
      emit('absence-updated')
    } else {
      await eventsStore.createAbsence(payload)
      emit('absence-created')
    }
    absence.value = resetAbsence(absence.value.dateStart)
    close()
  } catch (err) {
    absenceError.value = err.message || 'Fehler beim Speichern'
  } finally {
    absenceSubmitting.value = false
  }
}

async function deleteAbsence() {
  if (!confirm('Abwesenheit wirklich löschen?')) return
  absenceSubmitting.value = true
  try {
    savedThisSession.value = true
    await eventsStore.deleteAbsence(editId.value)
    emit('absence-deleted')
    close()
  } catch (err) {
    absenceError.value = err.message || 'Fehler beim Löschen'
  } finally {
    absenceSubmitting.value = false
  }
}

// Appointment form state
const appt = ref(resetAppt())
const apptSubmitting = ref(false)
const apptError = ref('')

function resetAppt(date = '') {
  return {
    title: '',
    dateStart: date,
    dateEnd: date,
    timeStart: '',
    timeEnd: '',
    allDay: true,
    location: '',
    typeId: '',
    description: '',
    status: 'Scheduled',
    organizationIds: [],
    invitedMemberIds: [],
    enableMeals: true,
    enableAgenda: true,
  }
}

async function submitAppointment() {
  apptError.value = ''
  if (!appt.value.organizationIds.length) {
    apptError.value = 'Bitte mindestens eine Organisation wählen.'
    return
  }
  apptSubmitting.value = true
  try {
    const payload = {
      title: appt.value.title,
      dateStart: appt.value.dateStart,
      dateEnd: appt.value.dateEnd || appt.value.dateStart,
      timeStart: appt.value.allDay ? null : (appt.value.timeStart || null),
      timeEnd: appt.value.allDay ? null : (appt.value.timeEnd || null),
      allDay: appt.value.allDay,
      location: appt.value.location,
      description: appt.value.description,
      status: appt.value.status,
      typeId: appt.value.typeId || null,
      organizationIds: appt.value.organizationIds,
      invitedMemberIds: appt.value.invitedMemberIds,
      enableMeals: appt.value.enableMeals,
      enableAgenda: appt.value.enableAgenda,
    }
    savedThisSession.value = true
    if (editMode.value === 'appointment') {
      await eventsStore.updateAppointment(editId.value, payload)
      emit('appointment-updated')
    } else {
      await eventsStore.createAppointment(payload)
      emit('appointment-created')
    }
    appt.value = resetAppt()
    close()
  } catch (err) {
    apptError.value = err.message || 'Fehler beim Speichern'
  } finally {
    apptSubmitting.value = false
  }
}

async function deleteAppointment() {
  if (!confirm('Termin wirklich löschen?')) return
  apptSubmitting.value = true
  try {
    savedThisSession.value = true
    await eventsStore.deleteAppointment(editId.value)
    emit('appointment-deleted')
    close()
  } catch (err) {
    apptError.value = err.message || 'Fehler beim Löschen'
  } finally {
    apptSubmitting.value = false
  }
}

// Terminfindung (Poll) form state
const poll = ref(resetPoll())
const pollSubmitting = ref(false)
const pollError = ref('')

function resetPollOption(date = '') {
  return { id: null, dateStart: date, dateEnd: date, timeStart: '', timeEnd: '', allDay: true }
}

function resetPoll(date = '') {
  return {
    title: '',
    description: '',
    location: '',
    organizationIds: [],
    invitedMemberIds: [],
    options: [resetPollOption(date), resetPollOption(date)],
  }
}

function addPollOption() {
  const lastDate = poll.value.options[poll.value.options.length - 1]?.dateStart || ''
  poll.value.options.push(resetPollOption(lastDate))
}

function removePollOption(index) {
  if (poll.value.options.length <= 2) return
  poll.value.options.splice(index, 1)
}

async function submitPoll() {
  pollError.value = ''
  if (!poll.value.organizationIds.length) {
    pollError.value = 'Bitte mindestens eine Organisation wählen.'
    return
  }
  if (poll.value.options.length < 2) {
    pollError.value = 'Bitte mindestens 2 Terminoptionen angeben.'
    return
  }
  if (poll.value.options.some(o => !o.dateStart)) {
    pollError.value = 'Bitte für jede Terminoption ein Datum angeben.'
    return
  }
  pollSubmitting.value = true
  try {
    const payload = {
      title: poll.value.title,
      description: poll.value.description,
      location: poll.value.location,
      organizationIds: poll.value.organizationIds,
      invitedMemberIds: poll.value.invitedMemberIds,
      options: poll.value.options.map(o => ({
        id: o.id || null,
        dateStart: o.dateStart,
        dateEnd: o.dateEnd || o.dateStart,
        timeStart: o.allDay ? null : (o.timeStart || null),
        timeEnd: o.allDay ? null : (o.timeEnd || null),
        allDay: o.allDay,
      })),
    }
    savedThisSession.value = true
    if (editMode.value === 'poll') {
      await eventsStore.updateSchedulingPoll(editId.value, payload)
      emit('poll-updated')
    } else {
      await eventsStore.createSchedulingPoll(payload)
      emit('poll-created')
    }
    poll.value = resetPoll()
    close()
  } catch (err) {
    pollError.value = err.message || 'Fehler beim Speichern'
  } finally {
    pollSubmitting.value = false
  }
}

async function deletePoll() {
  if (!confirm('Terminfindung wirklich löschen?')) return
  pollSubmitting.value = true
  try {
    savedThisSession.value = true
    await eventsStore.deleteSchedulingPoll(editId.value)
    emit('poll-deleted')
    close()
  } catch (err) {
    pollError.value = err.message || 'Fehler beim Löschen'
  } finally {
    pollSubmitting.value = false
  }
}

async function loadOrgsAndTypes() {
  await orgsStore.fetchOrganizations()
  if (!appointmentTypes.value.length) {
    try {
      const res = await apiGet('/calendar/appointmentTypes', false)
      appointmentTypes.value = res.types ?? []
    } catch {}
  }
}

async function open(preselectedDate = null) {
  const today = new Date().toISOString().slice(0, 10)
  const date = preselectedDate || today

  editMode.value = null
  editId.value = null
  absenceError.value = ''
  apptError.value = ''
  pollError.value = ''
  activeTab.value = canManageContent.value ? 'appointment' : 'absence'
  absence.value = resetAbsence(date)
  appt.value = resetAppt(date)
  poll.value = resetPoll(date)
  apptInviteePickerRef.value?.reset()
  pollInviteePickerRef.value?.reset()

  await loadOrgsAndTypes()
  if (managedOrgs.value.length === 1) {
    appt.value.organizationIds = [managedOrgs.value[0].ID]
    poll.value.organizationIds = [managedOrgs.value[0].ID]
  }
  modal.value?.open()
}

async function openEditAbsence(data) {
  editMode.value = 'absence'
  editId.value = data.AbsenceID
  absenceError.value = ''
  apptError.value = ''
  activeTab.value = 'absence'

  await loadOrgsAndTypes()

  absence.value = {
    dateStart: data.DateStart ?? '',
    dateEnd: data.DateEnd ?? '',
    recurrence: data.Recurrence ?? 'Never',
    note: data.Note ?? '',
  }

  modal.value?.open()
}

async function openEditAppointment(event) {
  editMode.value = 'appointment'
  editId.value = event.ID
  absenceError.value = ''
  apptError.value = ''
  pollError.value = ''
  activeTab.value = 'appointment'
  apptInviteePickerRef.value?.reset()
  pollInviteePickerRef.value?.reset()

  await loadOrgsAndTypes()

  appt.value = {
    title: event.Title ?? '',
    dateStart: event.DateStart ?? '',
    dateEnd: event.DateEnd ?? '',
    timeStart: event.TimeStart ? event.TimeStart.substring(0, 5) : '',
    timeEnd: event.TimeEnd ? event.TimeEnd.substring(0, 5) : '',
    allDay: !!event.AllDay,
    location: event.Location ?? '',
    description: event.Description ?? '',
    status: event.Status ?? 'Scheduled',
    typeId: event.TypeID ?? '',
    organizationIds: (event.OrganizationIDs ?? []).map(Number),
    invitedMemberIds: (event.InvitedMemberIDs ?? []).map(Number),
    enableMeals: event.EnableMeals ?? true,
    enableAgenda: event.EnableAgenda ?? true,
  }

  modal.value?.open()
}

async function openEditPoll(event) {
  editMode.value = 'poll'
  editId.value = event.PollID
  absenceError.value = ''
  apptError.value = ''
  pollError.value = ''
  activeTab.value = 'poll'
  apptInviteePickerRef.value?.reset()
  pollInviteePickerRef.value?.reset()

  await loadOrgsAndTypes()

  poll.value = {
    title: event.Title ?? '',
    description: event.Description ?? '',
    location: event.Location ?? '',
    organizationIds: (event.OrganizationIDs ?? []).map(Number),
    invitedMemberIds: (event.InvitedMemberIDs ?? []).map(Number),
    options: (event.PollOptions ?? []).map(o => ({
      id: o.OptionID,
      dateStart: o.DateStart ?? '',
      dateEnd: o.DateEnd ?? o.DateStart ?? '',
      timeStart: o.TimeStart ? o.TimeStart.substring(0, 5) : '',
      timeEnd: o.TimeEnd ? o.TimeEnd.substring(0, 5) : '',
      allDay: !!o.AllDay,
    })),
  }

  modal.value?.open()
}

function close() {
  modal.value?.close()
  emit('closed', savedThisSession.value)
  savedThisSession.value = false
}

defineExpose({ open, openEditAbsence, openEditAppointment, openEditPoll })
</script>
