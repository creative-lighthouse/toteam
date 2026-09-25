<template>
  <AppModal ref="modal" class="inventory-type-modal" :title="modalTitle" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">

      <div v-if="choosingOrg" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="organizationId" :orgs="store.manageableTypeOrgs" :searchable="false" />
      </div>

      <label class="field">
        Titel *
        <input v-model="form.Title" type="text" :placeholder="{ item: 'z.B. Scheinwerfer, Kabel, Kostüm', vehicle: 'z.B. Transporter, PKW, Anhänger', room: 'z.B. Proberaum, Lager, Büro' }[appliesTo]" required>
      </label>

      <label class="field">
        Beschreibung
        <textarea v-model="form.Description" rows="2" placeholder="Optionale Beschreibung…" />
      </label>

      <div class="field">
        <label>Zusatzfelder</label>
        <p class="inventory-type-modal_hint">
          <template v-if="isRoomType">Welche Angaben sollen Räume dieser Art haben? „Öffentlich“: auf der geteilten Seite ohne Anmeldung sichtbar – nicht für personenbezogene Daten. „In Liste“: in der Raumliste angezeigt (z.B. Fläche oder Plätze).</template>
          <template v-else>Welche Angaben sollen {{ appliesTo === 'vehicle' ? 'Fahrzeuge' : 'Objekte' }} dieser Art haben? „Pro Objekt“: bei gleichen Objekten verschieden (z.B. Seriennummer). „Öffentlich“: auf der geteilten Seite ohne Anmeldung sichtbar – nicht für personenbezogene Daten. „In Liste“: in der Inventarliste angezeigt (z.B. Kabellänge).</template>
        </p>

        <ul v-if="form.Fields.length" class="inventory-type-modal_fields">
          <li v-for="(field, index) in form.Fields" :key="field.key" class="inventory-type-modal_field">
            <input
              v-model="field.Label"
              type="text"
              class="input inventory-type-modal_field-label"
              placeholder="Bezeichnung, z.B. Kabellänge"
              :aria-label="`Bezeichnung Feld ${index + 1}`"
              required
            >
            <select v-model="field.Format" class="input inventory-type-modal_field-format" :aria-label="`Format Feld ${index + 1}`">
              <option v-for="f in store.fieldFormats" :key="f.value" :value="f.value">{{ f.label }}</option>
            </select>
            <div class="inventory-type-modal_field-flags">
              <label v-if="!isRoomType" title="Wert ist bei gleichen Objekten verschieden (z.B. Seriennummer)">
                <input v-model="field.Individual" type="checkbox">
                pro Objekt
              </label>
              <label :title="`Auch ohne Anmeldung auf der geteilten Seite ${isRoomType ? 'des Raums' : 'des Objekts'} sichtbar`">
                <input v-model="field.IsPublic" type="checkbox">
                öffentlich
              </label>
              <label title="Wert in der Inventarliste anzeigen (z.B. Kabellänge)">
                <input v-model="field.ShowInList" type="checkbox">
                in Liste
              </label>
            </div>
            <div class="inventory-type-modal_field-actions">
              <AppIconButton variant="ghost" aria-label="Nach oben" title="Nach oben" :disabled="index === 0" @click="move(index, -1)">↑</AppIconButton>
              <AppIconButton variant="ghost" aria-label="Nach unten" title="Nach unten" :disabled="index === form.Fields.length - 1" @click="move(index, 1)">↓</AppIconButton>
              <AppIconButton variant="danger" aria-label="Feld entfernen" title="Feld entfernen" @click="removeField(index)">
                <span class="icon-mask" :style="trashIconStyle" />
              </AppIconButton>
            </div>
          </li>
        </ul>

        <AppButton variant="secondary" size="small" class="inventory-type-modal_add-field" @click="addField">+ Feld hinzufügen</AppButton>
      </div>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppIconButton
        v-if="isEdit"
        variant="danger"
        class="inventory-type-modal_delete"
        aria-label="Art löschen"
        :title="inUse ? `Wird noch von ${{ item: 'Objekten', vehicle: 'Fahrzeugen', room: 'Räumen' }[appliesTo]} verwendet` : 'Löschen'"
        :disabled="saving || inUse"
        @click="remove"
      >
        <span class="icon-mask" :style="trashIconStyle" />
      </AppIconButton>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !form.Title.trim() || !organizationId">
        {{ saving ? 'Speichern…' : (isEdit ? 'Speichern' : 'Anlegen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'
import AppModal from '@components/ui/AppModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const emit = defineEmits(['saved', 'deleted'])
const store = useInventoryStore()

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

// Das Modal kommt mehrfach auf einer Seite vor (Objekt-Formular und Arten-Verwaltung) —
// eine feste ID würde den Speichern-Button (form="…") an das falsche Formular binden
const formId = `inventory-type-form-${Math.random().toString(36).slice(2)}`

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const organizationId = ref(null)
const editingType = ref(null)
// Ohne vorgegebene Organisation (z.B. neue Art für privates Equipment) wird sie im Modal gewählt
const choosingOrg = ref(false)
// 'item' oder 'room'
const appliesTo = ref('item')
const isRoomType = computed(() => appliesTo.value === 'room')
const modalTitle = computed(() => {
  const noun = { item: 'Art', vehicle: 'Fahrzeug-Art', room: 'Raum-Art' }[appliesTo.value] || 'Art'
  return isEdit.value ? `${noun} bearbeiten` : `Neue ${noun}`
})

const isEdit = computed(() => editingType.value !== null)
// Raum-Arten: Anzahl kommt vom Server; Objekt-Arten: aus den geladenen Objekten (immer aktuell)
const inUse = computed(() => {
  if (!editingType.value) return false
  return isRoomType.value ? editingType.value.ItemCount > 0 : store.itemCountForType(editingType.value.ID) > 0
})

const form = reactive({ Title: '', Description: '', Fields: [] })

// Stabiler Schlüssel für v-for, auch für noch nicht gespeicherte Felder
let fieldKey = 0
function toFormField(field = {}) {
  return {
    key: ++fieldKey,
    ID: field.ID ?? null,
    Label: field.Label ?? '',
    Format: field.Format ?? 'text',
    Individual: !!field.Individual,
    IsPublic: !!field.IsPublic,
    ShowInList: !!field.ShowInList,
  }
}

function addField() {
  form.Fields.push(toFormField())
}

function move(index, direction) {
  const target = index + direction
  if (target < 0 || target >= form.Fields.length) return
  const fields = [...form.Fields]
  ;[fields[index], fields[target]] = [fields[target], fields[index]]
  form.Fields = fields
}

function removeField(index) {
  const field = form.Fields[index]
  if (field.ID && !confirm(`Feld „${field.Label}“ entfernen? Die eingetragenen Werte aller Objekte dieser Art gehen dabei verloren.`)) return
  form.Fields.splice(index, 1)
}

function open(orgId = null, kind = 'item') {
  editingType.value = null
  appliesTo.value = kind
  choosingOrg.value = !orgId
  organizationId.value = orgId
  Object.assign(form, { Title: '', Description: '', Fields: [] })
  error.value = null
  modal.value?.open()
}

function openForEdit(type) {
  editingType.value = type
  appliesTo.value = type.AppliesTo || 'item'
  choosingOrg.value = false
  organizationId.value = type.OrganizationID
  Object.assign(form, {
    Title: type.Title || '',
    Description: type.Description || '',
    Fields: (type.Fields || []).map(toFormField),
  })
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.Title.trim()) return
  saving.value = true
  error.value = null

  try {
    const payload = {
      Title: form.Title.trim(),
      Description: form.Description,
      Fields: form.Fields.map(({ ID, Label, Format, Individual, IsPublic, ShowInList }) => ({
        ID, Label: Label.trim(), Format, Individual, IsPublic, ShowInList,
      })),
    }
    const response = isEdit.value
      ? await store.updateType(editingType.value.ID, payload)
      : await store.createType({ ...payload, OrganizationID: organizationId.value, AppliesTo: appliesTo.value })

    if (response.success) {
      emit('saved', response.data.type)
      close()
    } else {
      error.value = response.error || 'Fehler beim Speichern der Art.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!editingType.value) return
  if (!confirm(`Art „${editingType.value.Title}“ wirklich löschen?`)) return
  saving.value = true
  try {
    const response = await store.deleteType(editingType.value.ID)
    if (response.success) {
      emit('deleted', editingType.value.ID)
      close()
    } else {
      error.value = response.error || 'Fehler beim Löschen der Art.'
    }
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
