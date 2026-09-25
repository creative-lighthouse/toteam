<template>
  <AppModal ref="modal" class="inventory-item-form-modal" :title="modalTitle" @close="close">
    <form id="inventory-item-form" class="modalform" @submit.prevent="submit">

      <!-- Schritt 1: Besitzer -->
      <AppSegmentedToggle
        v-if="!isEdit"
        v-model="form.OwnerType"
        label="Wem gehört das Objekt?"
        :options="[
          { value: 'organization', label: 'Organisation', disabled: !store.creatableOrgs.length },
          { value: 'member', label: 'Mir (privat)' },
        ]"
      />
      <p v-else class="field inventory-item-form-modal_owner">
        Gehört: <strong>{{ form.OwnerType === 'member' ? 'dir (privat)' : store.orgById(form.OrganizationID)?.Title }}</strong>
      </p>

      <div v-if="!isEdit && form.OwnerType === 'organization' && store.creatableOrgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.creatableOrgs" />
      </div>

      <div v-if="ownerChosen && shareOptions.length" class="field">
        <label>Sichtbar/Ausleihbar für</label>
        <OrganizationPicker v-model="form.SharedWithIDs" :orgs="shareOptions" multiple :searchable="shareOptions.length > 5" />
        <p class="inventory-item-form-modal_hint">{{ shareHint }}</p>
      </div>

      <!-- Schritt 2: Art -->
      <div v-if="ownerChosen" class="field inventory-item-form-modal_type">
        <label for="inventory-item-type">Art *</label>
        <div class="inventory-item-form-modal_type-row">
          <select id="inventory-item-type" v-model="form.TypeID" required>
            <option :value="null" disabled>Art auswählen…</option>
            <template v-if="typeGroups.length > 1">
              <optgroup v-for="group in typeGroups" :key="group.org.ID" :label="group.org.Title">
                <option v-for="type in group.types" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
              </optgroup>
            </template>
            <template v-else>
              <option v-for="type in availableTypes" :key="type.ID" :value="type.ID">{{ type.Title }}</option>
            </template>
          </select>
          <AppButton v-if="typeManageOrgs.length" variant="secondary" @click="openNewType">+ Neue Art</AppButton>
        </div>
        <p v-if="!availableTypes.length" class="inventory-item-form-modal_hint">
          {{ typeManageOrgs.length ? 'Noch keine Arten vorhanden – lege zuerst eine an.' : 'Noch keine Arten vorhanden. Bitte jemanden mit der Berechtigung „Inventar-Arten verwalten“, eine anzulegen.' }}
        </p>
      </div>

      <!-- Schritt 3: Felder -->
      <template v-if="ownerChosen && selectedType">
        <label class="field field--4">
          Name *
          <input v-model="form.Title" type="text" :placeholder="isVehicle ? 'z.B. VW Crafter' : 'z.B. LED-Scheinwerfer'" required>
        </label>

        <label class="field field--2">
          Inventarnummer *
          <input
            v-model="form.InventoryNumber"
            type="text"
            placeholder="z.B. LI-0042"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            required
          >
        </label>

        <label class="field">
          Beschreibung
          <textarea v-model="form.Description" rows="3" placeholder="Optionale Beschreibung…" />
        </label>

        <label class="field field--3">
          Zustand
          <select v-model="form.Status">
            <option v-for="s in store.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </label>

        <label v-if="isVehicle" class="field field--3">
          Kilometerstand
          <input v-model="form.Mileage" type="text" inputmode="numeric" placeholder="z.B. 48200" autocomplete="off">
        </label>

        <label v-if="!isEdit" class="field field--3">
          Anzahl
          <input v-model.number="form.Count" type="number" min="1" max="500" inputmode="numeric">
        </label>
        <p v-if="!isEdit && form.Count > 1" class="field inventory-item-form-modal_hint">
          Es werden {{ form.Count }} gleiche Objekte angelegt, jedes mit eigener Nummer ({{ numberPreview }}).
          Zustand<template v-if="individualLabels"> und {{ individualLabels }}</template> pflegst du danach pro Objekt; in der Liste erscheinen sie zusammengefasst.
        </p>

        <template v-if="isEdit && groupSize > 1">
          <AppToggle v-model="form.ApplyToGroup" :label="`Für alle ${groupSize} gleichen Objekte übernehmen`" />
          <p class="field inventory-item-form-modal_hint">
            {{ form.ApplyToGroup
              ? `Name, Beschreibung, Art, Freigaben, neue Dateien und Zusatzfelder gelten für alle. Nummer, Zustand${individualLabels ? ` und ${individualLabels}` : ''} bleiben je Objekt.`
              : 'Nur dieses Objekt wird geändert. Mit anderem Namen oder anderer Art gehört es danach nicht mehr zur Gruppe.' }}
          </p>
        </template>

        <InventoryFieldInputs
          v-model="form.values"
          :fields="visibleFields"
          :individual-hint="form.ApplyToGroup ? 'nur dieses Objekt' : ''"
        />

        <div v-if="existingImages.length" class="field">
          <label>Bilder</label>
          <ul class="inventory-item-form-modal_images">
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
          <ul class="inventory-item-form-modal_documents">
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
          hint="z.B. Anleitung, Rechnung oder Prüfprotokoll – als PDF, JPG, PNG oder WebP, max. 10 MB"
        />
      </template>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="inventory-item-form" variant="primary" :disabled="saving || !canSubmit">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Anlegen') }}
      </AppButton>
    </template>
  </AppModal>

  <InventoryTypeModal ref="typeModal" @saved="onTypeSaved" />
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import InventoryTypeModal from '@components/inventory/InventoryTypeModal.vue'
import InventoryFieldInputs from '@components/inventory/InventoryFieldInputs.vue'

const emit = defineEmits(['saved'])
const store = useInventoryStore()

const modal = ref(null)
const typeModal = ref(null)
const saving = ref(false)
const error = ref(null)
const editingItemId = ref(null)
const existingImages = ref([])
const existingDocuments = ref([])
const newImages = ref([])
const newDocuments = ref([])

const isEdit = computed(() => editingItemId.value !== null)

function defaultOrgId() {
  const filter = store.filterOrganization
  if (typeof filter === 'number' && store.creatableOrgs.some(o => o.ID === filter)) return filter
  return store.creatableOrgs.length === 1 ? store.creatableOrgs[0].ID : null
}

const defaultForm = () => ({
  // Ohne Recht, Org-Inventar anzulegen (oder bei aktivem "Privat"-Filter), direkt privat
  OwnerType: !store.creatableOrgs.length || ['private', 'mine'].includes(store.filterOrganization) ? 'member' : 'organization',
  OrganizationID: defaultOrgId(),
  // Weitere Organisationen, die das Objekt sehen und ausleihen dürfen — standardmäßig keine
  SharedWithIDs: [],
  Count: 1,
  ApplyToGroup: false,
  Kind: 'item',
  Mileage: '',
  TypeID: null,
  Title: '',
  InventoryNumber: '',
  Description: '',
  Status: 'available',
  values: {},
})

const form = reactive(defaultForm())

const isPrivate = computed(() => form.OwnerType === 'member')
// Fahrzeuge: eigener Tab, eigene Arten, Kilometerstand
const isVehicle = computed(() => form.Kind === 'vehicle')
const modalTitle = computed(() => {
  if (isVehicle.value) return isEdit.value ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug'
  return isEdit.value ? 'Objekt bearbeiten' : 'Neues Objekt'
})

// Org-Objekte nutzen die Arten ihrer Organisation, privates Equipment die Arten aller eigenen Organisationen
const availableTypes = computed(() => {
  const appliesTo = isVehicle.value ? 'vehicle' : 'item'
  if (isPrivate.value) return isVehicle.value ? store.vehicleTypes : store.itemTypes
  return form.OrganizationID ? store.typesForOrg(form.OrganizationID, appliesTo) : []
})

const typeGroups = computed(() =>
  store.organizations
    .map(org => ({ org, types: availableTypes.value.filter(t => t.OrganizationID === org.ID) }))
    .filter(group => group.types.length)
)

const selectedType = computed(() => availableTypes.value.find(t => t.ID === form.TypeID) ?? null)

// Organisationen, in denen hier eine neue Art angelegt werden darf
const typeManageOrgs = computed(() =>
  isPrivate.value
    ? store.manageableTypeOrgs
    : store.manageableTypeOrgs.filter(o => o.ID === form.OrganizationID)
)

const ownerChosen = computed(() => isPrivate.value || !!form.OrganizationID)

// Zusatzfelder der gewählten Art (je Organisation frei definiert). Beim Anlegen
// mehrerer Objekte fehlen die "pro Objekt"-Felder — die pflegt man danach einzeln.
const visibleFields = computed(() =>
  (selectedType.value?.Fields ?? []).filter(field => isEdit.value || form.Count <= 1 || !field.Individual)
)

const individualLabels = computed(() =>
  (selectedType.value?.Fields ?? []).filter(f => f.Individual).map(f => f.Label).join(', ')
)

// Andere Organisationen, für die ein Org-Objekt freigegeben werden kann
// Pseudo-Eintrag für "auch für private Zwecke ausleihbar" (ID 0, keine echte Organisation)
const PRIVATE_OPTION = { ID: 0, Title: 'Privat (für private Zwecke)', LogoURL: null }

// Eigene Organisationen — bei Org-Objekten ohne die besitzende — plus "Privat"
const shareOptions = computed(() => [
  ...(isPrivate.value ? store.organizations : store.organizations.filter(o => o.ID !== form.OrganizationID)),
  PRIVATE_OPTION,
])

const privateRentable = computed(() => form.SharedWithIDs.includes(PRIVATE_OPTION.ID))
const sharedOrgIDs = computed(() => form.SharedWithIDs.filter(id => id !== PRIVATE_OPTION.ID))

const shareHint = computed(() => {
  const privateNote = privateRentable.value ? ' „Privat“: Wer das Objekt sieht, kann es auch für private Zwecke ausleihen.' : ''
  if (!sharedOrgIDs.value.length) {
    if (isPrivate.value) {
      return privateRentable.value
        ? 'Nur für dich sichtbar – „Privat“ hat erst eine Wirkung, wenn du auch eine Organisation auswählst.'
        : 'Nur für dich sichtbar – z.B. als persönliche Inventarliste.'
    }
    return 'Nur für die eigene Organisation sichtbar.' + privateNote
  }
  return (isPrivate.value
    ? 'Mitglieder dieser Organisationen sehen das Objekt und können es bei dir ausleihen. Über Anfragen entscheidest du selbst.'
    : 'Mitglieder dieser Organisationen sehen das Objekt und können es beantragen. Genehmigt wird weiterhin von deiner Organisation.') + privateNote
})

// Beim Wechsel von Besitzer oder Organisation nur noch gültige Freigaben behalten
watch(shareOptions, (options) => {
  const valid = new Set(options.map(o => o.ID))
  if (form.SharedWithIDs.some(id => !valid.has(id))) {
    form.SharedWithIDs = form.SharedWithIDs.filter(id => valid.has(id))
  }
})

// Anzahl gleicher Objekte (inkl. diesem) beim Bearbeiten
const groupSize = ref(1)

const numberPreview = computed(() => {
  const base = form.InventoryNumber.trim() || 'NR'
  const width = Math.max(2, String(form.Count).length)
  const pad = n => String(n).padStart(width, '0')
  return `${base}-${pad(1)} … ${base}-${pad(form.Count)}`
})

const canSubmit = computed(() =>
  ownerChosen.value && !!selectedType.value && !!form.Title.trim() && !!form.InventoryNumber.trim()
)

// Die gewählte Art gehört ggf. nicht mehr zur Auswahl, wenn Besitzer oder Organisation wechseln
watch(availableTypes, (types) => {
  if (form.TypeID && !types.some(t => t.ID === form.TypeID)) form.TypeID = null
})

function openNewType() {
  const orgs = typeManageOrgs.value
  typeModal.value?.open(orgs.length === 1 ? orgs[0].ID : null, isVehicle.value ? 'vehicle' : 'item')
}

function resetFiles() {
  newImages.value = []
  newDocuments.value = []
}

/** @param {{ kind?: 'item'|'vehicle' }} options */
function open({ kind = 'item' } = {}) {
  editingItemId.value = null
  groupSize.value = 1
  Object.assign(form, defaultForm(), { Kind: kind })
  existingImages.value = []
  existingDocuments.value = []
  resetFiles()
  error.value = null
  modal.value?.open()
}

// `item` ist das vollständige Detail-Objekt (inkl. Images/Documents)
/**
 * @param item vollständiges Detail-Objekt
 * @param {{ applyToGroup?: boolean }} options z.B. aus "Alle bearbeiten" der Gruppe
 */
function openForEdit(item, { applyToGroup = false } = {}) {
  editingItemId.value = item.ID
  groupSize.value = item.GroupSize ?? 1
  // Werte als Strings; alle bekannten Werte übernehmen, auch von Feldern einer
  // vorherigen Art (sie bleiben beim Speichern unangetastet)
  const values = { ...(item.Values || {}) }
  Object.assign(form, {
    OrganizationID: item.OrganizationID,
    Kind: item.Kind || 'item',
    Mileage: item.Mileage ?? '',
    OwnerType: item.OwnerType,
    SharedWithIDs: [...(item.SharedWith || []).map(o => o.ID), ...(item.PrivateRentable ? [PRIVATE_OPTION.ID] : [])],
    Count: 1,
    ApplyToGroup: applyToGroup && (item.GroupSize ?? 1) > 1,
    TypeID: item.TypeID,
    Title: item.Title || '',
    InventoryNumber: item.InventoryNumber || '',
    Description: item.Description || '',
    Status: item.Status || 'available',
    values,
  })
  existingImages.value = [...(item.Images || [])]
  existingDocuments.value = [...(item.Documents || [])]
  resetFiles()
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function onTypeSaved(type) {
  form.TypeID = type.ID
}

async function removeExisting(file) {
  if (!confirm(`„${file.Name}“ wirklich löschen?`)) return
  const response = await store.removeFile(editingItemId.value, file.ID, form.ApplyToGroup)
  if (response.success) {
    existingImages.value = existingImages.value.filter(f => f.ID !== file.ID)
    existingDocuments.value = existingDocuments.value.filter(f => f.ID !== file.ID)
  } else {
    error.value = response.error || 'Datei konnte nicht entfernt werden.'
  }
}

async function submit() {
  if (!canSubmit.value) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      TypeID: form.TypeID,
      Title: form.Title.trim(),
      InventoryNumber: form.InventoryNumber.trim(),
      Description: form.Description,
      Status: form.Status,
    }
    payload.Values = Object.fromEntries(visibleFields.value.map(field => [field.ID, form.values[field.ID] ?? '']))
    payload.SharedWithIDs = sharedOrgIDs.value
    payload.PrivateRentable = privateRentable.value
    if (isEdit.value && form.ApplyToGroup) payload.ApplyToGroup = true
    if (!isEdit.value) payload.Count = Math.min(500, Math.max(1, parseInt(form.Count) || 1))
    if (isVehicle.value) payload.Mileage = form.Mileage

    const response = isEdit.value
      ? await store.updateItem(editingItemId.value, payload)
      : await store.createItem({
        ...payload,
        OwnerType: form.OwnerType,
        Kind: form.Kind,
        OrganizationID: isPrivate.value ? null : form.OrganizationID,
      })

    if (!response.success) {
      error.value = response.error || 'Fehler beim Speichern des Objekts.'
      return
    }

    let item = response.data.item
    const createdCount = response.data.createdCount ?? 1
    const affectsGroup = createdCount > 1 || (isEdit.value && form.ApplyToGroup)
    // Beim Anlegen ist das Objekt gespeichert, auch wenn danach ein Upload scheitert —
    // ab hier also im Bearbeiten-Modus weitermachen, damit ein erneutes Speichern
    // kein Duplikat erzeugt
    editingItemId.value = item.ID

    const upload = await store.uploadFiles(item.ID, {
      images: newImages.value,
      documents: newDocuments.value,
      applyToGroup: affectsGroup,
    })
    // Mehrere Objekte angelegt/geändert — die ganze Liste neu laden
    if (affectsGroup) await store.fetchItems(true)
    if (upload.data?.item) item = upload.data.item
    if (!upload.success) {
      existingImages.value = [...(item.Images || [])]
      existingDocuments.value = [...(item.Documents || [])]
      resetFiles()
      error.value = upload.error || 'Dateien konnten nicht hochgeladen werden.'
      return
    }

    emit('saved', item, { createdCount })
    close()
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
