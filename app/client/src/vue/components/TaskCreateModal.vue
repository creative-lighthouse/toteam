<template>
  <AppModal ref="modal" class="task-create-modal" :title="parentTask ? 'Neue Unteraufgabe' : 'Neue Aufgabe'" @close="close">
    <form id="task-create-form" class="modalform" @submit.prevent="submit">

      <p v-if="parentTask" class="field task-create-modal_parent-hint">
        Unteraufgabe von <strong>{{ parentTask.Title }}</strong> ({{ parentTask.Organization?.Title }})
      </p>

      <div v-else class="field">
        <label>Organisation</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.organizations" />
      </div>

      <div class="field">
        <label for="task-owner">Verantwortlicher *</label>
        <select id="task-owner" v-model="form.OwnerID" :disabled="!form.OrganizationID || loadingOwners">
          <option value="0" disabled>{{ loadingOwners ? 'Lade Mitglieder…' : 'Bitte wählen' }}</option>
          <option v-for="owner in ownerOptions" :key="owner.ID" :value="owner.ID">{{ owner.Name }}</option>
        </select>
      </div>

      <label class="field">
        Titel *
        <input
          id="task-title"
          v-model="form.Title"
          type="text"
          placeholder="Aufgabentitel"
          required
          autofocus
        />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="task-description"
          v-model="form.Description"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </label>

      <div class="field field--3">
        <label for="task-state">Status</label>
        <select id="task-state" v-model="form.State">
          <option v-for="s in store.STATES" :key="s.value" :value="s.value">
            {{ s.label }}
          </option>
        </select>
      </div>

      <DateTimeRangeField
        :model-value="deadlineField"
        @update:model-value="v => (deadlineField = v)"
        time="none"
        :show-end-date="false"
        :required="false"
        start-label-date="Fälligkeitsdatum"
        start-field-class="field field--3"
      />

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="task-create-form" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID || !form.OwnerID">
        {{ saving ? 'Speichern…' : 'Erstellen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useTasksStore } from '@stores/tasks'
import { useAuthStore } from '@stores/auth'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'
import OrganizationPicker from '@components/OrganizationPicker.vue'
import DateTimeRangeField from '@components/DateTimeRangeField.vue'

const props = defineProps({
  // When set, the modal creates a subtask of this task instead of a top-level task
  parentTask: { type: Object, default: null },
})
const emit = defineEmits(['created'])
const store = useTasksStore()
const authStore = useAuthStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const ownerOptions = ref([])
const loadingOwners = ref(false)

const defaultForm = () => ({
  Title: '',
  Description: '',
  OrganizationID: props.parentTask
    ? props.parentTask.Organization?.ID ?? 0
    : (store.filterOrganization?.ID ?? (store.organizations.length === 1 ? store.organizations[0].ID : 0)),
  OwnerID: 0,
  State: 'open',
  Deadline: '',
})

const form = reactive(defaultForm())

// Adapter zwischen dem einzelnen "YYYY-MM-DD"-String und der
// { dateStart }-Form, die DateTimeRangeField per v-model erwartet/liefert.
const deadlineField = computed({
  get: () => ({ dateStart: form.Deadline }),
  set: (val) => { form.Deadline = val.dateStart },
})

async function loadOwners(orgId) {
  if (!orgId) {
    ownerOptions.value = []
    form.OwnerID = 0
    return
  }

  loadingOwners.value = true
  try {
    ownerOptions.value = await store.fetchOrgMembers(orgId)
  } finally {
    loadingOwners.value = false
  }

  const stillValid = ownerOptions.value.some(o => o.ID === form.OwnerID)
  if (!stillValid) {
    const currentUser = ownerOptions.value.find(o => o.ID === authStore.user?.ID)
    form.OwnerID = currentUser?.ID ?? ownerOptions.value[0]?.ID ?? 0
  }
}

watch(() => form.OrganizationID, orgId => loadOwners(orgId))

function open() {
  Object.assign(form, defaultForm())
  error.value = null
  loadOwners(form.OrganizationID)
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationID || !form.OwnerID) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      OrganizationID: form.OrganizationID ? parseInt(form.OrganizationID) : 0,
      OwnerID: parseInt(form.OwnerID),
      State: form.State,
      Deadline: form.Deadline || null,
    }
    if (props.parentTask) {
      payload.ParentID = props.parentTask.ID
    }

    const response = await store.createTask(payload)

    if (response.success) {
      emit('created', response.data.task)
      close()
    } else {
      error.value = response.error || 'Fehler beim Erstellen der Aufgabe.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
