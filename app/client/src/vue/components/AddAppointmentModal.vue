<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="event-modal addappointmentmodal" @cancel.prevent="close">
      <div class="dialog-content" @click.stop>

        <!-- Header -->
        <div class="dialog-header">
          <h2 class="hl2">{{ editMode === 'appointment' ? 'Termin bearbeiten' : editMode === 'absence' ? 'Abwesenheit bearbeiten' : canManageContent ? 'Termin hinzufügen' : 'Abwesenheit eintragen' }}</h2>
          <button type="button" class="button button--close" @click="close" aria-label="Schließen">✕</button>
        </div>

        <!-- Tabs (nur beim Anlegen, nicht beim Bearbeiten) -->
        <div v-if="!editMode" class="modal-tabs">
          <button
            type="button"
            class="tab-btn"
            :class="{ 'tab-btn--active': activeTab === 'absence' }"
            @click="activeTab = 'absence'"
          >Abwesenheit eintragen</button>
          <button
            type="button"
            class="tab-btn"
            :class="{ 'tab-btn--active': activeTab === 'appointment' }"
            @click="activeTab = 'appointment'"
          >Termin hinzufügen</button>
        </div>

        <!-- Tab: Abwesenheit -->

        <div class="dialog-infobox" v-if="activeTab === 'absence'">
            <form class="modal-form form--absence" @submit.prevent="submitAbsence">
                <label class="field field--organizations">
                    Kalender
                    <div class="multiselect-group">
                        <label class="checkbox-label">
                        <input type="checkbox" v-model="absence.allOrgs" @change="onAllOrgsChange" />
                        Alle
                        </label>
                        <label
                        v-for="org in memberOrgs"
                        :key="org.ID"
                        class="checkbox-label"
                        >
                        <input
                            type="checkbox"
                            :value="org.ID"
                            v-model="absence.organizationIds"
                            :disabled="absence.allOrgs"
                        />
                        {{ org.Title }}
                        </label>
                    </div>
                </label>

                <label class="field field--startdate">
                    Startdatum
                    <input type="date" v-model="absence.dateStart" @change="onAbsenceStartChange" required />
                </label>
                <label class="field field--enddate">
                    Enddatum
                    <input type="date" v-model="absence.dateEnd" :min="absence.dateStart" />
                </label>

                <label class="field field--recurrence">
                    Wiederholung
                    <select v-model="absence.recurrence">
                        <option value="Never">Nie</option>
                        <option value="Daily">Täglich</option>
                        <option value="Weekly">Wöchentlich</option>
                        <option value="Monthly">Monatlich</option>
                        <option value="Yearly">Jährlich</option>
                    </select>
                </label>

                <label class="field field--note">
                    Notiz
                    <textarea v-model="absence.note" rows="2" placeholder="Optionale Notiz"></textarea>
                </label>

                <div v-if="absenceError" class="form-error">{{ absenceError }}</div>

                <div class="form-actions">
                    <button type="submit" class="button" :disabled="absenceSubmitting">
                        {{ absenceSubmitting ? 'Wird gespeichert…' : (editMode === 'absence' ? 'Speichern' : 'Abwesenheit eintragen') }}
                    </button>
                    <button
                        v-if="editMode === 'absence'"
                        type="button"
                        class="button button--danger"
                        :disabled="absenceSubmitting"
                        @click="deleteAbsence"
                    >Löschen</button>
                </div>
            </form>
        </div>

        <!-- Tab: Termin hinzufügen -->
        <div class="dialog-infobox" v-if="activeTab === 'appointment' && canManageContent">
            <form class="modal-form form--appointment" @submit.prevent="submitAppointment">
                <label class="field field--organizations">
                    Organisation(en) *
                    <div class="multiselect-group">
                        <label
                        v-for="org in managedOrgs"
                        :key="org.ID"
                        class="checkbox-label"
                        >
                            <input type="checkbox" :value="org.ID" v-model="appt.organizationIds" />
                            {{ org.Title }}
                        </label>
                    </div>
                </label>

                <label class="field field--title">
                    Titel *
                    <input type="text" v-model="appt.title" required />
                </label>

                <label class="field field--status">
                    Status *
                    <select v-model="appt.status">
                        <option value="Scheduled">Geplant</option>
                        <option value="Suggested">Vorgeschlagen</option>
                        <option value="Cancelled">Abgesagt</option>
                    </select>
                </label>

                <label class="field field--allday">
                    <input type="checkbox" v-model="appt.allDay" checked />
                    Ganztägig
                </label>

                <label v-if="appt.allDay" class="field field--startdate">
                    Startdatum *
                    <input type="date" v-model="appt.dateStart" required />
                </label>

                <label v-else class="field field--startdatetime">
                    Start *
                    <input type="datetime-local" v-model="apptDateTimeStart" required aria-label="Startdatum und Startzeit" />
                </label>

                <label v-if="appt.allDay" class="field field--enddate">
                    Enddatum *
                    <input type="date" v-model="appt.dateEnd" :min="appt.dateStart" aria-label="Enddatum" />
                </label>

                <label v-else class="field field--enddatetime">
                    Ende *
                    <input type="datetime-local" v-model="apptDateTimeEnd" aria-label="Enddatum und Endzeit" />
                </label>

                <label class="field field--location">
                    Ort
                    <input type="text" v-model="appt.location" />
                </label>

                <label class="field field--type">
                    Typ
                    <select v-model="appt.typeId">
                        <option value="">– kein Typ –</option>
                        <option v-for="t in appointmentTypes" :key="t.ID" :value="t.ID">{{ t.Title }}</option>
                    </select>
                </label>

                <label class="field field--description">
                    Beschreibung
                    <textarea v-model="appt.description" rows="3"></textarea>
                </label>

                <label class="field field--features">
                    Features
                    <div class="multiselect-group">
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="appt.enableMeals" />
                            Mahlzeiten
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="appt.enableAgenda" />
                            Tagesordnung
                        </label>
                    </div>
                </label>

                <div v-if="apptError" class="form-error">{{ apptError }}</div>

                <div class="form-actions">
                    <button type="submit" class="button" :disabled="apptSubmitting">
                        {{ apptSubmitting ? 'Wird gespeichert…' : (editMode === 'appointment' ? 'Speichern' : 'Termin erstellen') }}
                    </button>
                    <button
                        v-if="editMode === 'appointment'"
                        type="button"
                        class="button button--danger"
                        :disabled="apptSubmitting"
                        @click="deleteAppointment"
                    >Löschen</button>
                </div>
            </form>
        </div>
        <div v-else-if="activeTab === 'appointment' && !canManageContent" class="dialog-infobox">
            <p>{{ noPermissionMessage }}</p>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import { useEventsStore } from '@stores/events'
import { apiGet } from '@utils/api'

const emit = defineEmits(['appointment-created', 'absence-created', 'appointment-updated', 'absence-updated', 'appointment-deleted', 'absence-deleted', 'closed'])

const savedThisSession = ref(false)

const dialogEl = ref(null)
const activeTab = ref('absence')
const editMode = ref(null) // null | 'absence' | 'appointment'
const editId = ref(null)
const orgsStore = useOrganizationsStore()
const eventsStore = useEventsStore()

const appointmentTypes = ref([])

const memberOrgs = computed(() =>
  orgsStore.organizations.filter(o => o.MembershipStatus === 'member')
)

const managedOrgs = computed(() =>
  memberOrgs.value.filter(o => o.Permissions?.includes('CALENDAR_MANAGE'))
)

const canManageContent = computed(() => managedOrgs.value.length > 0)

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
    allOrgs: true,
    organizationIds: [],
  }
}

function onAbsenceStartChange() {}

function onAllOrgsChange() {
  if (absence.value.allOrgs) {
    absence.value.organizationIds = []
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
      organizationIds: absence.value.allOrgs ? [] : absence.value.organizationIds,
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

const apptDateTimeEnd = computed({
  get() {
    const date = appt.value.dateEnd
    const time = appt.value.timeEnd
    if (date && time) return `${date}T${time}`
    if (date) return `${date}T00:00`
    return ''
  },
  set(val) {
    if (val) {
      const [date, time] = val.split('T')
      appt.value.dateEnd = date ?? ''
      appt.value.timeEnd = time ?? ''
    } else {
      appt.value.dateEnd = ''
      appt.value.timeEnd = ''
    }
  }
})

const apptDateTimeStart = computed({
  get() {
    const date = appt.value.dateStart
    const time = appt.value.timeStart
    if (date && time) return `${date}T${time}`
    if (date) return `${date}T00:00`
    return ''
  },
  set(val) {
    if (val) {
      const [date, time] = val.split('T')
      appt.value.dateStart = date ?? ''
      appt.value.timeStart = time ?? ''
    } else {
      appt.value.dateStart = ''
      appt.value.timeStart = ''
    }
  }
})

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
  activeTab.value = canManageContent.value ? 'appointment' : 'absence'
  absence.value = resetAbsence(date)
  appt.value = resetAppt(date)

  await loadOrgsAndTypes()
  if (managedOrgs.value.length === 1) {
    appt.value.organizationIds = [managedOrgs.value[0].ID]
  }
  dialogEl.value?.showModal()
}

async function openEditAbsence(data) {
  editMode.value = 'absence'
  editId.value = data.AbsenceID
  absenceError.value = ''
  apptError.value = ''
  activeTab.value = 'absence'

  await loadOrgsAndTypes()

  const orgIds = (data.OrganizationIds ?? []).map(Number)
  absence.value = {
    dateStart: data.DateStart ?? '',
    dateEnd: data.DateEnd ?? '',
    recurrence: data.Recurrence ?? 'Never',
    note: data.Note ?? '',
    allOrgs: orgIds.length === 0,
    organizationIds: orgIds,
  }

  dialogEl.value?.showModal()
}

async function openEditAppointment(event) {
  editMode.value = 'appointment'
  editId.value = event.ID
  absenceError.value = ''
  apptError.value = ''
  activeTab.value = 'appointment'

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
    enableMeals: event.EnableMeals ?? true,
    enableAgenda: event.EnableAgenda ?? true,
  }

  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
  emit('closed', savedThisSession.value)
  savedThisSession.value = false
}

defineExpose({ open, openEditAbsence, openEditAppointment })
</script>
