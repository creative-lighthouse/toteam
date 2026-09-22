<template>
  <AppModal ref="modal" class="marketing-size-manager-modal" title="Plakat-Größen verwalten" @close="close">

    <div v-if="manageableOrganizations.length > 1" class="form-field">
      <label class="form-label" for="marketing-size-org">Organisation</label>
      <select id="marketing-size-org" v-model="selectedOrgId" class="input" @change="onOrgChange">
        <option v-for="org in manageableOrganizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
      </select>
    </div>

    <form class="marketing-size-manager-modal_new" @submit.prevent="addSize">
      <input
        v-model="newTitle"
        type="text"
        class="input"
        placeholder="Neue Größe, z.B. A3"
      />
      <AppButton type="submit" variant="primary" size="small" :disabled="!newTitle.trim() || !selectedOrgId || saving">
        + Hinzufügen
      </AppButton>
    </form>

    <div v-if="error" class="app-modal_error">{{ error }}</div>

    <div v-if="!sizesForOrg.length" class="marketing-size-manager-modal_empty">
      Für diese Organisation wurden noch keine Plakat-Größen angelegt.
    </div>

    <ul v-else class="marketing-size-manager-modal_list">
      <li v-for="size in sizesForOrg" :key="size.ID" class="marketing-size-manager-modal_item">
        <input
          v-if="editingId === size.ID"
          v-model="editingTitle"
          type="text"
          class="input"
          @keyup.enter="saveRename(size)"
          @keyup.esc="cancelRename"
        />
        <span v-else class="marketing-size-manager-modal_item-title">{{ size.Title }}</span>

        <div class="marketing-size-manager-modal_item-actions">
          <template v-if="editingId === size.ID">
            <AppIconButton variant="primary" aria-label="Speichern" title="Speichern" @click="saveRename(size)">
              <span class="icon-mask" :style="checkIconStyle" />
            </AppIconButton>
            <AppIconButton variant="ghost" aria-label="Abbrechen" title="Abbrechen" @click="cancelRename">✕</AppIconButton>
          </template>
          <template v-else>
            <AppIconButton variant="primary" aria-label="Umbenennen" title="Umbenennen" @click="startRename(size)">
              <span class="icon-mask" :style="editIconStyle" />
            </AppIconButton>
            <AppIconButton variant="danger" aria-label="Löschen" title="Löschen" @click="removeSize(size)">
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </template>
        </div>
      </li>
    </ul>

  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useMarketingStore } from '@stores/marketing'
import { useOrganizationsStore } from '@stores/organizations'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppModal from '@components/AppModal.vue'
import actionEdit from '../../../icons/actions/action_edit.svg'
import actionTrash from '../../../icons/actions/action_trash.svg'
import actionCheck from '../../../icons/actions/action_check.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }
const checkIconStyle = { maskImage: `url("${actionCheck}")`, WebkitMaskImage: `url("${actionCheck}")` }

const props = defineProps({
  organizations: { type: Array, default: () => [] },
})

const store = useMarketingStore()
const orgsStore = useOrganizationsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const newTitle = ref('')
const editingId = ref(null)
const editingTitle = ref('')
const selectedOrgId = ref(0)

// Only organizations the current member may manage sizes for — picking one is
// mandatory, never silently guessed, since different orgs distribute
// different poster sizes and mixing them up creates sizes under the wrong org.
const manageableOrganizations = computed(() => props.organizations.filter(o => o.CanManageSizes))

const sizesForOrg = computed(() => store.sizes.filter(s => s.OrganizationID === selectedOrgId.value))

function onOrgChange() {
  orgsStore.setLastOrganizationId(selectedOrgId.value)
}

function open() {
  error.value = null
  editingId.value = null

  const remembered = orgsStore.lastOrganizationId
  selectedOrgId.value = manageableOrganizations.value.some(o => o.ID === remembered)
    ? remembered
    : (manageableOrganizations.value[0]?.ID ?? 0)

  modal.value?.open()
}

function close() {
  editingId.value = null
  modal.value?.close()
}

async function addSize() {
  if (!newTitle.value.trim() || !selectedOrgId.value) return
  saving.value = true
  error.value = null
  try {
    const response = await store.createSize(selectedOrgId.value, newTitle.value.trim())
    if (response.success) {
      newTitle.value = ''
    } else {
      error.value = response.error || 'Fehler beim Anlegen der Größe.'
    }
  } finally {
    saving.value = false
  }
}

function startRename(size) {
  editingId.value = size.ID
  editingTitle.value = size.Title
}

function cancelRename() {
  editingId.value = null
}

async function saveRename(size) {
  if (!editingTitle.value.trim()) return
  const response = await store.updateSize(size.ID, editingTitle.value.trim())
  if (response.success) {
    editingId.value = null
  } else {
    error.value = response.error || 'Fehler beim Umbenennen der Größe.'
  }
}

async function removeSize(size) {
  if (!confirm(`Größe "${size.Title}" wirklich löschen? Bereits erfasste Einträge bleiben erhalten.`)) return
  const response = await store.deleteSize(size.ID)
  if (!response.success) {
    error.value = response.error || 'Fehler beim Löschen der Größe.'
  }
}

defineExpose({ open, close })
</script>
