<template>
  <AppModal ref="modal" class="inventory-storage-form-modal" :title="isEdit ? 'Lagerpunkt bearbeiten' : 'Neuer Lagerpunkt'" @close="close">
    <form id="inventory-storage-form" class="modalform" @submit.prevent="submit">

      <!-- Besitzer (nur beim Anlegen) -->
      <AppSegmentedToggle
        v-if="!isEdit"
        v-model="form.OwnerType"
        label="Wem gehört der Lagerpunkt?"
        :options="[
          { value: 'organization', label: 'Organisation', disabled: !store.creatableOrgs.length },
          { value: 'member', label: 'Mir (privat)' },
        ]"
      />
      <p v-else class="field inventory-storage-form-modal_owner">
        Gehört: <strong>{{ form.OwnerType === 'member' ? 'dir (privat)' : ownerOrgTitle }}</strong>
      </p>

      <div v-if="!isEdit && !isPrivate && store.creatableOrgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.creatableOrgs" />
      </div>

      <template v-if="ownerChosen">
        <label class="field">
          Name *
          <input v-model="form.Title" type="text" placeholder="z.B. Vereinsheim, Kiste 3, Stellplatz Hof" required>
        </label>

        <div class="field inventory-storage-form-modal_type">
          <label for="inventory-storage-type">Art</label>
          <div class="inventory-storage-form-modal_type-row">
            <select id="inventory-storage-type" v-model="form.TypeID">
              <option :value="null">Keine Art</option>
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
        </div>

        <InventoryStorageSelect
          v-model="form.ParentID"
          label="Lagert in"
          empty-label="– Oberste Ebene –"
          :exclude-id="editingId"
        />

        <label class="field">
          Beschreibung
          <textarea v-model="form.Description" rows="3" placeholder="Optionale Beschreibung, z.B. Adresse oder Zugang…" />
        </label>

        <InventoryFieldInputs v-if="selectedType" v-model="form.values" :fields="selectedType.Fields" />

        <div v-if="shareOptions.length" class="field">
          <label>Sichtbar für</label>
          <OrganizationPicker v-model="form.SharedWithIDs" :orgs="shareOptions" multiple :searchable="shareOptions.length > 5" />
          <p class="inventory-storage-form-modal_hint">{{ shareHint }}</p>
        </div>
      </template>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="inventory-storage-form" variant="primary" :disabled="saving || !canSubmit">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Anlegen') }}
      </AppButton>
    </template>
  </AppModal>

  <InventoryTypeModal ref="typeModal" @saved="type => (form.TypeID = type.ID)" />
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useStorageStore } from '@stores/storage'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import InventoryFieldInputs from '@components/inventory/InventoryFieldInputs.vue'
import InventoryStorageSelect from '@components/inventory/InventoryStorageSelect.vue'
import InventoryTypeModal from '@components/inventory/InventoryTypeModal.vue'

const emit = defineEmits(['saved'])

const store = useStorageStore()
const inventoryStore = useInventoryStore()

const modal = ref(null)
const typeModal = ref(null)
const saving = ref(false)
const error = ref(null)
const editingId = ref(null)
const ownerOrgTitle = ref('')

const isEdit = computed(() => editingId.value !== null)

const defaultForm = () => ({
  OwnerType: store.creatableOrgs.length ? 'organization' : 'member',
  OrganizationID: store.creatableOrgs.length === 1 ? store.creatableOrgs[0].ID : null,
  Title: '',
  Description: '',
  TypeID: null,
  ParentID: null,
  SharedWithIDs: [],
  values: {},
})

const form = reactive(defaultForm())

const isPrivate = computed(() => form.OwnerType === 'member')
const ownerChosen = computed(() => isPrivate.value || !!form.OrganizationID)

// Org-Lagerpunkte nutzen die Arten ihrer Organisation, private die aller eigenen Organisationen
const storageTypes = computed(() => inventoryStore.types.filter(t => t.AppliesTo === 'storage'))
const availableTypes = computed(() =>
  isPrivate.value ? storageTypes.value : storageTypes.value.filter(t => t.OrganizationID === form.OrganizationID)
)
const typeGroups = computed(() =>
  store.organizations
    .map(org => ({ org, types: availableTypes.value.filter(t => t.OrganizationID === org.ID) }))
    .filter(group => group.types.length)
)
const selectedType = computed(() => availableTypes.value.find(t => t.ID === form.TypeID) ?? null)

const typeManageOrgs = computed(() =>
  isPrivate.value
    ? inventoryStore.manageableTypeOrgs
    : inventoryStore.manageableTypeOrgs.filter(o => o.ID === form.OrganizationID)
)

// Freigaben: eigene Organisationen, bei Org-Lagerpunkten ohne die besitzende
const shareOptions = computed(() =>
  isPrivate.value ? store.organizations : store.organizations.filter(o => o.ID !== form.OrganizationID)
)

const shareHint = computed(() => {
  if (form.SharedWithIDs.length) {
    return 'Mitglieder dieser Organisationen sehen den Lagerpunkt und können Objekte darin einlagern.'
  }
  return isPrivate.value ? 'Nur für dich sichtbar.' : 'Nur für die eigene Organisation sichtbar.'
})

const canSubmit = computed(() => ownerChosen.value && !!form.Title.trim())

watch(availableTypes, (types) => {
  if (form.TypeID && !types.some(t => t.ID === form.TypeID)) form.TypeID = null
})

watch(shareOptions, (options) => {
  const valid = new Set(options.map(o => o.ID))
  if (form.SharedWithIDs.some(id => !valid.has(id))) {
    form.SharedWithIDs = form.SharedWithIDs.filter(id => valid.has(id))
  }
})

function openNewType() {
  const orgs = typeManageOrgs.value
  typeModal.value?.open(orgs.length === 1 ? orgs[0].ID : null, 'storage')
}

/** @param {{ parentId?: number|null }} options z.B. "Hier einlagern" an einem Lagerpunkt */
function open({ parentId = null } = {}) {
  editingId.value = null
  Object.assign(form, defaultForm(), { ParentID: parentId })
  // In einem Org-Lagerpunkt standardmäßig dieselbe Organisation (sofern erlaubt)
  const parent = parentId ? store.byId.get(parentId) : null
  if (parent?.IsPrivate && parent.IsMine) {
    form.OwnerType = 'member'
  } else if (parent?.OrganizationID && store.creatableOrgs.some(o => o.ID === parent.OrganizationID)) {
    form.OwnerType = 'organization'
    form.OrganizationID = parent.OrganizationID
  }
  error.value = null
  modal.value?.open()
}

function openForEdit(location) {
  editingId.value = location.ID
  ownerOrgTitle.value = location.Organization?.Title ?? ''
  Object.assign(form, {
    OwnerType: location.OwnerType,
    OrganizationID: location.OrganizationID,
    Title: location.Title || '',
    Description: location.Description || '',
    TypeID: location.TypeID,
    ParentID: location.ParentID,
    SharedWithIDs: (location.SharedWith || []).map(o => o.ID),
    values: { ...(location.Values || {}) },
  })
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!canSubmit.value) return
  saving.value = true
  error.value = null
  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      TypeID: form.TypeID ?? 0,
      ParentID: form.ParentID ?? 0,
      SharedWithIDs: form.SharedWithIDs,
      Values: Object.fromEntries((selectedType.value?.Fields ?? []).map(field => [field.ID, form.values[field.ID] ?? ''])),
    }
    const response = isEdit.value
      ? await store.updateLocation(editingId.value, payload)
      : await store.createLocation({
        ...payload,
        OwnerType: form.OwnerType,
        OrganizationID: isPrivate.value ? null : form.OrganizationID,
      })
    if (!response.success) {
      error.value = response.error || 'Fehler beim Speichern des Lagerpunkts.'
      return
    }
    emit('saved', response.data.location)
    close()
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
