<template>
  <AppModal ref="modal" class="room-form-modal" :title="isEdit ? 'Raum bearbeiten' : 'Neuer Raum'" @close="close">
    <form id="room-form-form" class="modalform" @submit.prevent="submit">

      <div v-if="!isEdit" class="field">
        <label>Organisation</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.organizations" />
      </div>

      <label class="field">
        Titel *
        <input
          id="room-title"
          v-model="form.Title"
          type="text"
          placeholder="Raumtitel"
          required
          autofocus
        />
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="room-description"
          v-model="form.Description"
          rows="3"
          placeholder="Optionale Beschreibung…"
        />
      </label>

      <div v-if="form.OrganizationID" class="field room-form-modal_type">
        <label for="room-type">Art</label>
        <div class="room-form-modal_type-row">
          <select id="room-type" v-model="form.TypeID">
            <option :value="null">Keine Art</option>
            <option v-for="type in roomTypes" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
          </select>
          <AppButton v-if="canManageTypes" variant="secondary" @click="typeModal?.open(form.OrganizationID, 'room')">+ Neue Art</AppButton>
        </div>
      </div>

      <InventoryFieldInputs v-if="selectedType" v-model="form.values" :fields="selectedType.Fields" />

      <AppToggle v-model="form.IsRentable" label="Kann reserviert werden" />

      <div class="field">
        <label>Aufgaben</label>
        <p v-if="loadingTasks" class="room-form-modal_tasks-loading">Lade Aufgaben…</p>
        <p v-else-if="!form.OrganizationID" class="room-form-modal_tasks-loading">Bitte zuerst eine Organisation wählen.</p>
        <AppChipSelect
          v-else-if="attachableTasks.length"
          v-model="form.TaskIDs"
          class="room-form-modal_tasks"
          aria-label="Aufgaben"
          :options="attachableTasks.map(t => ({ value: t.ID, label: t.Title }))"
        />
        <p v-else class="room-form-modal_tasks-loading">Keine Aufgaben in dieser Organisation.</p>
      </div>

      <div v-if="existingImages.length" class="field">
        <label>Bilder</label>
        <ul class="room-form-modal_images">
          <li v-for="img in existingImages" :key="img.ID">
            <img :src="img.Thumbnail" :alt="img.Name">
            <AppIconButton variant="ghost" aria-label="Bild entfernen" title="Bild entfernen" @click="removeExisting(img)">✕</AppIconButton>
          </li>
        </ul>
      </div>
      <AppFileUpload
        v-model="newImages"
        :label="existingImages.length ? 'Weitere Bilder' : 'Bilder'"
        multiple
        :max-files="10"
        accept="image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
      />

      <div v-if="existingDocuments.length" class="field">
        <label>Dokumente</label>
        <ul class="room-form-modal_documents">
          <li v-for="doc in existingDocuments" :key="doc.ID">
            <a :href="doc.URL" target="_blank" rel="noopener">{{ doc.Name }}</a>
            <AppIconButton variant="ghost" aria-label="Dokument entfernen" title="Dokument entfernen" @click="removeExisting(doc)">✕</AppIconButton>
          </li>
        </ul>
      </div>
      <AppFileUpload
        v-model="newDocuments"
        :label="existingDocuments.length ? 'Weitere Dokumente' : 'Dokumente'"
        multiple
        :max-files="10"
        accept="application/pdf,image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
        hint="z.B. Grundriss, Hausordnung oder Schlüsselregelung – als PDF, JPG, PNG oder WebP, max. 10 MB"
      />

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="room-form-form" variant="primary" :disabled="saving || !form.Title.trim() || !form.OrganizationID">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
      </AppButton>
    </template>
  </AppModal>

  <InventoryTypeModal ref="typeModal" @saved="onTypeSaved" />
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRoomsStore } from '@stores/rooms'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import AppChipSelect from '@components/ui/AppChipSelect.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import InventoryFieldInputs from '@components/inventory/InventoryFieldInputs.vue'
import InventoryTypeModal from '@components/inventory/InventoryTypeModal.vue'
import { useInventoryStore } from '@stores/inventory'

const emit = defineEmits(['saved'])
const store = useRoomsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const attachableTasks = ref([])
const loadingTasks = ref(false)
const editingRoomId = ref(null)
const existingImages = ref([])
const existingDocuments = ref([])
const newImages = ref([])
const newDocuments = ref([])

function resetFiles(room = null) {
  existingImages.value = [...(room?.Images || [])]
  existingDocuments.value = [...(room?.Documents || [])]
  newImages.value = []
  newDocuments.value = []
}

async function removeExisting(file) {
  if (!confirm(`„${file.Name}“ wirklich löschen?`)) return
  const response = await store.removeFile(editingRoomId.value, file.ID)
  if (response.success) {
    existingImages.value = existingImages.value.filter(f => f.ID !== file.ID)
    existingDocuments.value = existingDocuments.value.filter(f => f.ID !== file.ID)
  } else {
    error.value = response.error || 'Datei konnte nicht entfernt werden.'
  }
}

const isEdit = computed(() => editingRoomId.value !== null)

const defaultForm = () => ({
  OrganizationID: store.filterOrganization?.ID ?? (store.organizations.length === 1 ? store.organizations[0].ID : 0),
  Title: '',
  Description: '',
  IsRentable: false,
  TaskIDs: [],
  TypeID: null,
  values: {},
})

const form = reactive(defaultForm())

// Raum-Arten kommen aus dem Inventar (gleiche Verwaltung wie Objekt-Arten)
const inventoryStore = useInventoryStore()
const typeModal = ref(null)
const roomTypes = computed(() => (form.OrganizationID ? inventoryStore.typesForOrg(parseInt(form.OrganizationID), 'room') : []))
const selectedType = computed(() => roomTypes.value.find(t => t.ID === form.TypeID) ?? null)
const canManageTypes = computed(() => !!inventoryStore.orgById(parseInt(form.OrganizationID))?.CanManageTypes)

function onTypeSaved(type) {
  form.TypeID = type.ID
}

// Beim Organisationswechsel (nur beim Anlegen möglich) gehört die Art nicht mehr dazu.
// Bewusst nicht auf roomTypes reagieren: sind die Arten noch nicht geladen, ginge sonst
// beim Bearbeiten die gespeicherte Art verloren.
// Beim Befüllen per open()/openForEdit() passen Organisation und Art bereits zusammen
let fillingForm = false

watch(() => form.OrganizationID, (orgId, oldOrgId) => {
  if (!fillingForm && oldOrgId && orgId !== oldOrgId) form.TypeID = null
  fillingForm = false
})

async function loadAttachableTasks(orgId, keepSelection = []) {
  if (!orgId) {
    attachableTasks.value = []
    form.TaskIDs = []
    return
  }

  loadingTasks.value = true
  try {
    attachableTasks.value = await store.fetchAttachableTasks(orgId)
  } finally {
    loadingTasks.value = false
  }

  const validIds = new Set(attachableTasks.value.map(t => t.ID))
  form.TaskIDs = keepSelection.filter(id => validIds.has(id))
}

watch(() => form.OrganizationID, orgId => loadAttachableTasks(orgId))

// Create a new room
function open() {
  editingRoomId.value = null
  Object.assign(form, defaultForm())
  resetFiles()
  error.value = null
  loadAttachableTasks(form.OrganizationID)
  modal.value?.open()
}

// Edit an existing room — `room` is the full detail object from fetchRoomDetail (incl. Tasks)
function openForEdit(room) {
  editingRoomId.value = room.ID
  resetFiles(room)
  fillingForm = form.OrganizationID !== (room.Organization?.ID ?? 0)
  Object.assign(form, {
    OrganizationID: room.Organization?.ID ?? 0,
    Title: room.Title,
    Description: room.Description || '',
    IsRentable: !!room.IsRentable,
    TaskIDs: (room.Tasks || []).map(t => t.ID),
    TypeID: room.TypeID ?? null,
    values: { ...(room.Values || {}) },
  })
  error.value = null
  loadAttachableTasks(form.OrganizationID, form.TaskIDs)
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim() || !form.OrganizationID) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      IsRentable: form.IsRentable,
      TaskIDs: form.TaskIDs,
      TypeID: form.TypeID || 0,
      Values: selectedType.value
        ? Object.fromEntries(selectedType.value.Fields.map(field => [field.ID, form.values[field.ID] ?? '']))
        : {},
    }

    const response = isEdit.value
      ? await store.updateRoom(editingRoomId.value, payload)
      : await store.createRoom({ ...payload, OrganizationID: parseInt(form.OrganizationID) })

    if (!response.success) {
      error.value = response.error || 'Fehler beim Speichern des Raums.'
      return
    }

    const room = response.data.room
    // Der Raum ist gespeichert, auch wenn danach ein Upload scheitert — ab hier
    // im Bearbeiten-Modus weitermachen, damit erneutes Speichern kein Duplikat erzeugt
    editingRoomId.value = room.ID

    const upload = await store.uploadFiles(room.ID, { images: newImages.value, documents: newDocuments.value })
    if (!upload.success) {
      resetFiles(upload.data?.room)
      error.value = upload.error || 'Dateien konnten nicht hochgeladen werden.'
      return
    }

    emit('saved', upload.data?.room || room)
    close()
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
