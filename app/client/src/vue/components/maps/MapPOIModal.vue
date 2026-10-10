<template>
  <!-- Marker eines Lageplans: ansehen (Titel + Beschreibung), bearbeiten oder neu anlegen.
       Änderungen gehen per Event an die Seite, die sie in den MapRenderer übernimmt —
       gespeichert wird erst mit "Speichern" des Lageplans. -->
  <AppModal ref="modal" class="map-poi-modal" :title="modalTitle" @close="close">
    <template v-if="mode === 'view'">
      <p v-if="form.description" class="map-poi-modal_description"><AppLinkifiedText :text="form.description" /></p>
      <p v-else class="map-poi-modal_empty">Keine Beschreibung vorhanden.</p>
    </template>

    <form v-else :id="formId" class="modalform" @submit.prevent="submit">
      <label class="field field--4">
        Titel *
        <input v-model="form.title" type="text" placeholder="z.B. Eingang, Bühne" required autofocus>
      </label>

      <label class="field field--2">
        Marker-Text
        <input v-model="form.markerText" type="text" maxlength="4" :placeholder="markerTextPlaceholder" autocomplete="off">
      </label>

      <label class="field">
        Beschreibung
        <textarea v-model="form.description" rows="3" placeholder="Optional, z.B. Hinweise für Helfer…" />
      </label>

      <label v-if="mode === 'edit'" class="field">
        Koordinaten (Lat, Lng)
        <input v-model="form.position" type="text" placeholder="53.6371, 10.3829" autocomplete="off">
        <span class="map-poi-modal_hint">Am einfachsten verschiebst du den Marker direkt auf der Karte.</span>
      </label>
      <p v-else class="field map-poi-modal_hint">Der Marker erscheint in der Kartenmitte – zieh ihn danach an die richtige Stelle.</p>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template v-if="mode !== 'view'" #actions>
      <AppButton v-if="mode === 'edit'" variant="danger" @click="remove">Löschen</AppButton>
      <AppButton variant="secondary" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="!form.title.trim()">
        {{ mode === 'create' ? 'Hinzufügen' : 'Übernehmen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'

// create: (values) · save: (poiData, values) · delete: (poiData)
const emit = defineEmits(['create', 'save', 'delete'])

const formId = `map-poi-form-${Math.random().toString(36).slice(2)}`
const COORDINATES = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/

const modal = ref(null)
// 'view' | 'edit' | 'create'
const mode = ref('view')
const error = ref(null)
let currentPoiData = null
let createContext = null

const form = reactive({ title: '', markerText: '', description: '', position: '' })

const modalTitle = computed(() => {
  if (mode.value === 'create') return 'Neuer Marker'
  if (mode.value === 'edit') return 'Marker bearbeiten'
  return form.title || 'Marker'
})

// Ohne Marker-Text zeigt der Marker den ersten Buchstaben des Titels
const markerTextPlaceholder = computed(() => (form.title.trim().charAt(0).toUpperCase() || 'z.B. A1'))

/** Marker ansehen (editable = false) oder bearbeiten; poiData wie vom MapRenderer ({ layerId, poiId, poi }) */
function open(poiData, { editable = false } = {}) {
  currentPoiData = poiData
  createContext = null
  mode.value = editable ? 'edit' : 'view'
  const poi = poiData.poi
  Object.assign(form, {
    title: poi.title || '',
    markerText: poi.markerText || '',
    description: poi.description || '',
    position: poi.position || '',
  })
  error.value = null
  modal.value?.open()
}

/** Neuen Marker anlegen; context wird mit dem create-Event zurückgegeben (z.B. die Ebene) */
function openCreate(context = null) {
  currentPoiData = null
  createContext = context
  mode.value = 'create'
  Object.assign(form, { title: '', markerText: '', description: '', position: '' })
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

function values() {
  const title = form.title.trim()
  return {
    title,
    markerText: (form.markerText.trim() || title.charAt(0).toUpperCase()).slice(0, 4),
    description: form.description,
    position: form.position.trim(),
  }
}

function submit() {
  if (!form.title.trim()) return
  if (mode.value === 'create') {
    emit('create', values(), createContext)
    close()
    return
  }
  const result = values()
  if (!COORDINATES.test(result.position)) {
    error.value = 'Ungültige Koordinaten. Bitte als „Breitengrad, Längengrad“ angeben, z.B. 53.6371, 10.3829.'
    return
  }
  emit('save', currentPoiData, result)
  close()
}

function remove() {
  if (!confirm(`Marker „${form.title || currentPoiData.poi.title}“ wirklich löschen?`)) return
  emit('delete', currentPoiData)
  close()
}

defineExpose({ open, openCreate, close })
</script>
