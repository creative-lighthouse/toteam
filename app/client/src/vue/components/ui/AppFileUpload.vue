<!--
  Datei-Upload für Modal-Formulare (.modalform) — Label, Auswahl-Button mit
  verstecktem <input type="file">, Drag & Drop, Vorschau (Bild-Thumbnail bzw.
  Dateiname + Größe), Entfernen und Validierung von Typ/Größe direkt im Feld.
  Rendert sich selbst als kompletter .field-Block.

  Einzelne Datei — v-model ist ein File oder null:
    <AppFileUpload
      v-model="receiptFile"
      label="Beleg"
      :required="requiresReceipt"
      accept="image/jpeg,image/png,application/pdf"
      :max-size="5 * 1024 * 1024"
    />

  Mehrere Dateien — v-model ist ein File[] (neue Dateien werden angehängt):
    <AppFileUpload v-model="files" label="Belege" multiple :max-files="5" />

  Hochgeladen wird vom Aufrufer.
-->
<template>
  <div
    class="field app-file-upload"
    :class="{ 'app-file-upload--dragging': dragging }"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="onDrop"
  >
    <label :for="inputId">{{ label }}{{ required ? ' *' : '' }}</label>

    <ul v-if="files.length" class="app-file-upload_list">
      <li v-for="(file, index) in files" :key="fileKey(file, index)" class="app-file-upload_selected">
        <img v-if="previewUrls.get(file)" :src="previewUrls.get(file)" alt="Vorschau" class="app-file-upload_preview" />
        <div class="app-file-upload_file">
          <span class="app-file-upload_name">{{ file.name }}</span>
          <span class="app-file-upload_size">{{ formatSize(file.size) }}</span>
        </div>
        <AppIconButton variant="ghost" aria-label="Datei entfernen" title="Datei entfernen" @click="remove(index)">✕</AppIconButton>
      </li>
    </ul>

    <label v-if="canAddMore" :for="inputId" class="button button--secondary app-file-upload_button">
      {{ currentButtonLabel }}
    </label>
    <input
      :id="inputId"
      ref="inputEl"
      type="file"
      class="app-file-upload_input"
      :aria-label="label"
      :accept="accept"
      :multiple="multiple"
      @change="onChange"
    />

    <p v-if="error" class="app-file-upload_error">{{ error }}</p>
    <p v-else-if="!files.length && existingHint" class="app-file-upload_hint">{{ existingHint }}</p>
    <p v-else-if="hint" class="app-file-upload_hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import AppIconButton from '@components/ui/AppIconButton.vue'

const props = defineProps({
  // Einzelmodus: File | null — mit `multiple`: File[]
  modelValue: { type: [File, Array, Object], default: null },
  label: { type: String, required: true },
  required: { type: Boolean, default: false },
  multiple: { type: Boolean, default: false },
  // Nur mit `multiple`: maximale Anzahl Dateien (0 = unbegrenzt)
  maxFiles: { type: Number, default: 0 },
  // Wie beim nativen Input, z. B. "image/*" oder "image/jpeg,application/pdf".
  // Wird zusätzlich selbst geprüft, da Drag & Drop den Datei-Dialog umgeht.
  accept: { type: String, default: '' },
  // Maximale Größe pro Datei in Bytes (0 = unbegrenzt)
  maxSize: { type: Number, default: 0 },
  buttonLabel: { type: String, default: '' },
  changeLabel: { type: String, default: 'Andere Datei wählen' },
  addLabel: { type: String, default: 'Weitere Datei hinzufügen' },
  // Allgemeiner Hinweis unter dem Button, z. B. erlaubte Formate
  hint: { type: String, default: '' },
  // Hinweis, dass bereits eine Datei existiert (z. B. beim Bearbeiten)
  existingHint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const inputId = `file-upload-${Math.random().toString(36).slice(2)}`
const inputEl = ref(null)
const error = ref(null)
const dragging = ref(false)

// Einheitlich als Liste, egal ob Einzel- oder Mehrfachmodus
const files = computed(() => {
  if (props.multiple) return Array.isArray(props.modelValue) ? props.modelValue : []
  return props.modelValue ? [props.modelValue] : []
})

const canAddMore = computed(() => {
  if (!props.multiple) return true
  return !props.maxFiles || files.value.length < props.maxFiles
})

const currentButtonLabel = computed(() => {
  if (props.multiple && files.value.length) return props.addLabel
  if (!props.multiple && (files.value.length || props.existingHint)) return props.changeLabel
  return props.buttonLabel || (props.multiple ? 'Dateien auswählen' : 'Datei auswählen')
})

// Vorschau-URLs für Bilder, freigegeben sobald die Datei nicht mehr ausgewählt ist
const previewUrls = ref(new Map())
watch(files, (current) => {
  const next = new Map()
  for (const file of current) {
    const existing = previewUrls.value.get(file)
    if (existing) next.set(file, existing)
    else if (file instanceof File && file.type.startsWith('image/')) next.set(file, URL.createObjectURL(file))
  }
  for (const [file, url] of previewUrls.value) {
    if (!next.has(file)) URL.revokeObjectURL(url)
  }
  previewUrls.value = next
  // Input leeren, damit dieselbe Datei nach dem Entfernen erneut gewählt werden kann
  if (!current.length && inputEl.value) inputEl.value.value = ''
}, { immediate: true })

onBeforeUnmount(() => {
  for (const url of previewUrls.value.values()) URL.revokeObjectURL(url)
})

const acceptRules = computed(() => props.accept.split(',').map(s => s.trim().toLowerCase()).filter(Boolean))

function isAccepted(file) {
  if (!acceptRules.value.length) return true
  const type = (file.type || '').toLowerCase()
  const name = file.name.toLowerCase()
  return acceptRules.value.some(rule => {
    if (rule.startsWith('.')) return name.endsWith(rule)
    if (rule.endsWith('/*')) return type.startsWith(rule.slice(0, -1))
    return type === rule
  })
}

function validate(file) {
  if (!isAccepted(file)) return `„${file.name}“: Dieser Dateityp ist hier nicht erlaubt.`
  if (props.maxSize && file.size > props.maxSize) return `„${file.name}“ ist zu groß (max. ${formatSize(props.maxSize)}).`
  return null
}

function select(fileList) {
  const incoming = Array.from(fileList || [])
  if (!incoming.length) return

  const errors = []
  let valid = []
  for (const file of incoming) {
    const message = validate(file)
    if (message) errors.push(message)
    else valid.push(file)
  }

  if (props.multiple) {
    if (props.maxFiles) {
      const free = props.maxFiles - files.value.length
      if (valid.length > free) {
        errors.push(`Es sind maximal ${props.maxFiles} Dateien möglich.`)
        valid = valid.slice(0, Math.max(0, free))
      }
    }
    if (valid.length) emit('update:modelValue', [...files.value, ...valid])
  } else if (valid.length) {
    emit('update:modelValue', valid[0])
  }

  error.value = errors.length ? errors.join(' ') : null
  if (inputEl.value) inputEl.value.value = ''
}

function onChange(e) {
  select(e.target.files)
}

function onDrop(e) {
  dragging.value = false
  const dropped = Array.from(e.dataTransfer?.files || [])
  select(props.multiple ? dropped : dropped.slice(0, 1))
}

function remove(index) {
  error.value = null
  if (props.multiple) {
    emit('update:modelValue', files.value.filter((_, i) => i !== index))
  } else {
    emit('update:modelValue', null)
  }
}

function fileKey(file, index) {
  return `${index}-${file.name}-${file.size}-${file.lastModified}`
}

function formatSize(bytes) {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toLocaleString('de-DE', { maximumFractionDigits: 1 })} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}
</script>
