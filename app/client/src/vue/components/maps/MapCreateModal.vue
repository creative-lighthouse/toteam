<template>
  <AppModal ref="modal" class="map-create-modal" title="Neuen Lageplan erstellen" @close="close">
    <p v-if="loadingOrgs" class="map-create-modal_status">Lade Organisationen…</p>
    <p v-else-if="!organizations.length" class="map-create-modal_status">
      Du hast keine Organisation, für die du Lagepläne erstellen kannst.
    </p>

    <form v-else id="map-create-form" class="modalform" @submit.prevent="submit">
      <div class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.organizationId" :orgs="organizations" />
      </div>

      <label class="field">
        Titel *
        <input v-model="form.title" type="text" required placeholder="z.B. Vereinsgelände" />
      </label>

      <label class="field">
        Beschreibung
        <textarea v-model="form.shortText" rows="3" placeholder="Kurze Beschreibung des Lageplans"></textarea>
      </label>

      <AppFileUpload
        v-model="backgroundImage"
        label="Hintergrundbild"
        accept="image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
        button-label="Bild auswählen"
        change-label="Anderes Bild wählen"
        hint="JPG, PNG oder WebP, max. 10 MB"
      />

      <AppCollapse title="Koordinaten" subtitle="(optional)">
        <div class="modalform">
          <label class="field field--3">
            Oben links (Lat, Lng)
            <input v-model="form.coordinatesUpperLeft" type="text" placeholder="53.6371, 10.3829" />
          </label>
          <label class="field field--3">
            Oben rechts (Lat, Lng)
            <input v-model="form.coordinatesUpperRight" type="text" placeholder="53.6369, 10.3834" />
          </label>
          <label class="field field--3">
            Unten links (Lat, Lng)
            <input v-model="form.coordinatesLowerLeft" type="text" placeholder="53.6369, 10.3824" />
          </label>
          <label class="field field--3">
            Unten rechts (Lat, Lng)
            <input v-model="form.coordinatesLowerRight" type="text" placeholder="53.6365, 10.3829" />
          </label>
        </div>
      </AppCollapse>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton
        v-if="organizations.length"
        type="submit"
        form="map-create-form"
        variant="primary"
        :disabled="saving || !form.title.trim() || !form.organizationId"
      >
        {{ saving ? 'Wird erstellt…' : 'Erstellen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiGet, apiPost, apiPostForm, clearCacheForEndpoint } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const emit = defineEmits(['created'])

const modal = ref(null)
const organizations = ref([])
const loadingOrgs = ref(false)
const saving = ref(false)
const error = ref(null)
const backgroundImage = ref(null)

const defaultForm = () => ({
  title: '',
  shortText: '',
  organizationId: null,
  coordinatesUpperLeft: '',
  coordinatesUpperRight: '',
  coordinatesLowerLeft: '',
  coordinatesLowerRight: '',
})
const form = ref(defaultForm())

async function loadOrgs() {
  loadingOrgs.value = true
  try {
    const data = await apiGet('/maps/managedorgs', false)
    // Format des OrganizationPicker ({ ID, Title, LogoURL })
    organizations.value = (data.organizations || []).map(o => ({ ID: o.id, Title: o.title, LogoURL: o.logoUrl }))
  } catch (e) {
    error.value = e.message
  } finally {
    loadingOrgs.value = false
  }
  // Nur eine Organisation zur Auswahl: direkt vorauswählen
  if (organizations.value.length === 1) {
    form.value.organizationId = organizations.value[0].ID
  }
}

function open() {
  form.value = defaultForm()
  backgroundImage.value = null
  error.value = null
  saving.value = false
  modal.value?.open()
  loadOrgs()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (saving.value) return
  error.value = null
  saving.value = true
  try {
    const result = await apiPost('/maps/createmap', { ...form.value })
    if (!result?.success) {
      error.value = result?.error || 'Lageplan konnte nicht erstellt werden.'
      return
    }

    const mapId = result.data.mapId
    await clearCacheForEndpoint('/maps')

    if (backgroundImage.value) {
      const formData = new FormData()
      formData.append('image', backgroundImage.value)
      const upload = await apiPostForm(`/maps/uploadbackgroundimage/${mapId}`, formData)
      if (upload && upload.success === false) {
        // Lageplan existiert bereits — trotzdem weiter, Bild lässt sich dort nachreichen
        console.warn('Hintergrundbild-Upload fehlgeschlagen:', upload.error)
      }
    }

    emit('created', mapId)
    close()
  } catch (e) {
    error.value = e.message || 'Lageplan konnte nicht erstellt werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
