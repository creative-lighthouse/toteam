<template>
  <AppModal ref="modal" class="marketing-entry-modal" :title="isEdit ? 'Eintrag bearbeiten' : 'Neuer Verteil-Eintrag'" @close="close">
    <form id="marketing-entry-form" @submit.prevent="submit">

      <div v-if="!isEdit && store.organizations.length > 1" class="form-field">
        <label class="form-label" for="marketing-entry-org">Organisation *</label>
        <select
          id="marketing-entry-org"
          v-model="form.OrganizationID"
          class="input"
          required
          @change="orgsStore.setLastOrganizationId(form.OrganizationID)"
        >
          <option :value="0" disabled>Bitte wählen</option>
          <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
        </select>
      </div>

      <div class="form-field">
        <label class="form-label" for="marketing-entry-location">Ort *</label>
        <div class="marketing-entry-modal_location-row">
          <input
            id="marketing-entry-location"
            v-model="form.Location"
            type="text"
            class="input"
            placeholder="z.B. Marktplatz, schwarzes Brett"
            autofocus
          />
          <AppButton type="button" variant="secondary" size="small" :disabled="geoLoading" @click="useCurrentLocation">
            {{ geoLoading ? 'Ermittle Standort…' : '📍 Position erfassen' }}
          </AppButton>
        </div>
        <p v-if="form.Latitude && form.Longitude" class="marketing-entry-modal_geo-value">
          Koordinaten erfasst ({{ form.Latitude }}, {{ form.Longitude }})
          <button type="button" class="marketing-entry-modal_geo-clear" @click="clearLocation">Entfernen</button>
        </p>
        <p v-if="geoError" class="app-modal_error">{{ geoError }}</p>
        <p v-if="!hasLocationInfo" class="marketing-entry-modal_hint">
          Bitte entweder einen Ort eingeben oder die aktuelle Position erfassen.
        </p>
      </div>

      <div class="form-field">
        <div class="form-field-row">
          <div class="form-field">
            <label class="form-label" for="marketing-entry-size">Größe *</label>
            <select
              id="marketing-entry-size"
              v-model="form.PosterSizeID"
              class="input"
              required
              :disabled="!form.OrganizationID || sizesForOrg.length === 0"
            >
              <option value="" disabled>Bitte wählen</option>
              <option v-for="size in sizesForOrg" :key="size.ID" :value="size.ID">{{ size.Title }}</option>
            </select>
          </div>

          <div class="form-field">
            <label class="form-label" for="marketing-entry-quantity">Anzahl *</label>
            <input
              id="marketing-entry-quantity"
              v-model.number="form.Quantity"
              type="number"
              min="1"
              class="input"
              required
            />
          </div>
        </div>

        <p v-if="!form.OrganizationID" class="marketing-entry-modal_hint">
          Bitte zuerst eine Organisation auswählen.
        </p>
        <p v-else-if="sizesForOrg.length === 0" class="marketing-entry-modal_hint">
          Für diese Organisation wurden noch keine Plakat-Größen angelegt.
        </p>
      </div>

      <div class="form-field">
        <label class="form-label" for="marketing-entry-distributed-at">Zeitpunkt *</label>
        <input
          id="marketing-entry-distributed-at"
          v-model="form.DistributedAt"
          type="datetime-local"
          class="input"
          required
        />
      </div>

      <div class="form-field">
        <label class="form-label" for="marketing-entry-note">Notiz</label>
        <textarea
          id="marketing-entry-note"
          v-model="form.Note"
          class="input"
          rows="2"
          placeholder="Optionale Anmerkung…"
        />
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton
        type="submit"
        form="marketing-entry-form"
        variant="primary"
        :disabled="saving || !hasLocationInfo || !form.PosterSizeID || !form.OrganizationID || !form.DistributedAt"
      >
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Erstellen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useMarketingStore } from '@stores/marketing'
import { useOrganizationsStore } from '@stores/organizations'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const emit = defineEmits(['saved'])
const store = useMarketingStore()
const orgsStore = useOrganizationsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const geoLoading = ref(false)
const geoError = ref(null)
const isEdit = ref(false)
let editingId = null

function defaultOrganizationId() {
  if (store.organizations.length === 1) return store.organizations[0].ID
  const remembered = orgsStore.lastOrganizationId
  if (remembered && store.organizations.some(o => o.ID === remembered)) return remembered
  return store.filterOrganization || 0
}

function nowLocal() {
  const d = new Date()
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const defaultForm = () => ({
  OrganizationID: defaultOrganizationId(),
  Location: '',
  PosterSizeID: '',
  Quantity: 1,
  Latitude: '',
  Longitude: '',
  Note: '',
  DistributedAt: nowLocal(),
})

const form = reactive(defaultForm())

// Ort und Koordinaten sind austauschbar — es reicht, wenn eines von beiden vorliegt.
const hasLocationInfo = computed(() => !!form.Location.trim() || !!(form.Latitude && form.Longitude))

// store.sizes can hold sizes from several of the member's organizations at
// once (e.g. when no org filter is active on the main list) — always scope
// the options to the org actually selected above, so members never pick a
// size that belongs to a different organization's poster catalog.
const sizesForOrg = computed(() => store.sizes.filter(s => s.OrganizationID === form.OrganizationID))

// Switching the organization can invalidate a previously chosen size from
// the old org's catalog — clear it rather than silently submitting a
// mismatched PosterSizeID.
watch(() => form.OrganizationID, () => {
  if (!sizesForOrg.value.some(s => s.ID === form.PosterSizeID)) {
    form.PosterSizeID = ''
  }
})

function open(distribution = null) {
  Object.assign(form, defaultForm())
  error.value = null
  geoError.value = null
  isEdit.value = !!distribution
  editingId = distribution?.ID ?? null

  if (distribution) {
    form.OrganizationID = distribution.OrganizationID
    form.Location = distribution.Location
    form.PosterSizeID = distribution.PosterSize?.ID ?? ''
    form.Quantity = distribution.Quantity
    form.Latitude = distribution.Latitude || ''
    form.Longitude = distribution.Longitude || ''
    form.Note = distribution.Note || ''
    form.DistributedAt = distribution.DistributedAt ? distribution.DistributedAt.replace(' ', 'T').slice(0, 16) : nowLocal()
  }

  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    geoError.value = 'Standortbestimmung wird von diesem Browser nicht unterstützt.'
    return
  }
  geoLoading.value = true
  geoError.value = null
  navigator.geolocation.getCurrentPosition(
    pos => {
      form.Latitude = pos.coords.latitude.toFixed(6)
      form.Longitude = pos.coords.longitude.toFixed(6)
      geoLoading.value = false
    },
    err => {
      geoError.value = `Standort konnte nicht ermittelt werden (${err.message}).`
      geoLoading.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

function clearLocation() {
  form.Latitude = ''
  form.Longitude = ''
}

async function submit() {
  if (!hasLocationInfo.value || !form.PosterSizeID || !form.OrganizationID || !form.DistributedAt) return

  saving.value = true
  error.value = null

  const payload = {
    Location: form.Location.trim(),
    PosterSizeID: parseInt(form.PosterSizeID),
    Quantity: parseInt(form.Quantity) || 1,
    Latitude: form.Latitude || '',
    Longitude: form.Longitude || '',
    Note: form.Note,
    DistributedAt: form.DistributedAt,
  }

  try {
    const response = isEdit.value
      ? await store.updateDistribution(editingId, payload)
      : await store.createDistribution({ ...payload, OrganizationID: parseInt(form.OrganizationID) })

    if (response.success) {
      orgsStore.setLastOrganizationId(parseInt(form.OrganizationID))
      emit('saved', response.data.distribution)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern des Eintrags.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
