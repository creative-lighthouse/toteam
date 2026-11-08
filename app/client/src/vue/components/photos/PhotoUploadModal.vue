<template>
  <AppModal ref="modal" class="photo-upload-modal" title="Fotos hochladen" @close="close">
    <form id="photo-upload-form" class="modalform" @submit.prevent="start">

      <!-- Aus einem Ordner heraus stehen Organisation und Event fest -->
      <p v-if="fixed" class="photo-upload-modal_target">
        Ziel: <strong>{{ targetOrg?.Title }}</strong> › <strong>{{ targetEventTitle }}</strong>
      </p>

      <div v-else-if="orgs.length > 1" class="field">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="orgs" />
      </div>

      <template v-if="form.OrganizationID">
        <label v-if="!fixed" class="field">
          Event
          <select v-model="form.EventID" class="input" :disabled="busy">
            <option :value="0">Kein Event</option>
            <option v-for="ev in options?.events ?? []" :key="ev.ID" :value="ev.ID">{{ ev.Title }}</option>
          </select>
        </label>

        <label class="field">
          Copyright für alle
          <input v-model="form.Copyright" type="text" maxlength="255" placeholder="z.B. Steffen Kahl" :disabled="busy" list="photo-upload-copyrights">
          <datalist id="photo-upload-copyrights">
            <option v-for="value in options?.copyrights ?? []" :key="value" :value="value" />
          </datalist>
          <small class="photo-upload-modal_hint">Wird bei jedem Foto mit © angezeigt und lässt sich später einzeln ändern.</small>
        </label>

        <div class="field photo-upload-modal_tagging">
          <span class="photo-upload-modal_label">Tags für alle</span>
          <PhotoTagFields
            v-model="tagging"
            :options="options"
            :event-ids="form.EventID ? [form.EventID] : []"
            :disabled="busy"
          />
          <small class="photo-upload-modal_hint">Kommen an jedes hochgeladene Foto und lassen sich später einzeln ändern.</small>
        </div>

        <div class="field">
          <label class="button photo-upload-modal_pick" :class="{ 'photo-upload-modal_pick--disabled': busy }">
            Fotos auswählen
            <input
              ref="fileInput"
              type="file"
              :accept="ACCEPT_ATTRIBUTE"
              multiple
              class="file-input-hidden"
              :disabled="busy"
              @change="onFilesSelected"
            >
          </label>
          <small class="photo-upload-modal_hint">
            Die Fotos werden vor dem Hochladen verkleinert (max. 2560 px). Aufnahmezeit und GPS-Position werden übernommen.
          </small>
        </div>

        <ul v-if="queue.length" class="photo-upload-modal_queue">
          <li v-for="entry in queue" :key="entry.key" class="photo-upload-modal_entry" :class="`photo-upload-modal_entry--${entry.status}`">
            <span class="photo-upload-modal_name">{{ entry.file.name }}</span>
            <span class="photo-upload-modal_status">{{ statusLabel(entry) }}</span>
          </li>
        </ul>
        <p v-if="queue.length" class="photo-upload-modal_summary" role="status">
          {{ doneCount }} von {{ queue.length }} fertig<template v-if="failedCount">, {{ failedCount }} fehlgeschlagen</template>
        </p>
      </template>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" @click="close">{{ finished ? 'Schließen' : 'Abbrechen' }}</AppButton>
      <AppButton type="submit" form="photo-upload-form" variant="primary" :disabled="!canStart">
        {{ busy ? 'Lädt hoch…' : `${pendingCount || ''} Hochladen`.trim() }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { usePhotosStore } from '@stores/photos'
import { preparePhoto, readPhotoMeta, isAcceptedImage, ACCEPT_ATTRIBUTE, ACCEPTED_LABEL } from '@utils/photoUpload'
import AppModal from '@components/ui/AppModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import PhotoTagFields from '@components/photos/PhotoTagFields.vue'
import { emptyTagging, taggingFields } from '@utils/photoTagging'

const emit = defineEmits(['uploaded'])
const store = usePhotosStore()

const PARALLEL = 2

const modal = ref(null)
const fileInput = ref(null)
const error = ref(null)
const busy = ref(false)
// { key, file, status: pending|preparing|uploading|done|duplicate|failed, message }
const queue = ref([])

const form = reactive({ OrganizationID: null, EventID: 0, Copyright: '' })
// Personen, Charaktere, Räume, Schlagworte für alle Fotos (siehe utils/photoTagging.js)
const tagging = ref(emptyTagging())

// Wer viel hochlädt, ist meist selbst der Fotograf: letzten Copyright-Eintrag vorschlagen
const COPYRIGHT_KEY = 'toteam_photo_copyright'
function readCopyright() {
  try {
    return localStorage.getItem(COPYRIGHT_KEY) || ''
  } catch {
    return ''
  }
}
function rememberCopyright(value) {
  try {
    if (value) localStorage.setItem(COPYRIGHT_KEY, value)
    else localStorage.removeItem(COPYRIGHT_KEY)
  } catch {
    // egal
  }
}
// Organisation und Event vorgegeben (Hochladen aus einem Ordner der Galerie)
const fixed = ref(false)

const orgs = computed(() => store.organizations.filter(o => o.CanUpload && o.Configured))
const options = computed(() => store.optionsByOrg[form.OrganizationID] ?? null)
const targetOrg = computed(() => store.organizations.find(o => o.ID === form.OrganizationID) ?? null)
const targetEventTitle = computed(() => {
  if (!form.EventID) return 'Ohne Event'
  return options.value?.events.find(e => e.ID === form.EventID)?.Title
    ?? store.folders?.events.find(e => e.ID === form.EventID)?.Title
    ?? 'Event'
})

const pendingCount = computed(() => queue.value.filter(e => e.status === 'pending').length)
const doneCount = computed(() => queue.value.filter(e => e.status === 'done' || e.status === 'duplicate').length)
const failedCount = computed(() => queue.value.filter(e => e.status === 'failed').length)
const finished = computed(() => queue.value.length > 0 && !busy.value && pendingCount.value === 0)
const canStart = computed(() => !busy.value && !!form.OrganizationID && pendingCount.value > 0)

watch(() => form.OrganizationID, (orgId, previous) => {
  if (orgId) store.fetchOptions(orgId, true).catch(() => {})
  // Tags gehören zur Organisation
  if (previous) tagging.value = emptyTagging()
})

// Lageplan-Räume gehören zum Event
watch(() => form.EventID, () => {
  tagging.value = { ...tagging.value, rooms: tagging.value.rooms.filter(r => !String(r.id).startsWith('a')) }
})

function open({ organizationId = null, eventId = 0, fixed: isFixed = false, files = [] } = {}) {
  error.value = null
  queue.value = []
  fixed.value = isFixed && !!organizationId
  form.OrganizationID = organizationId && orgs.value.some(o => o.ID === organizationId)
    ? organizationId
    : (orgs.value.length === 1 ? orgs.value[0].ID : null)
  form.EventID = eventId || 0
  tagging.value = emptyTagging()
  form.Copyright = readCopyright()
  // Immer frisch laden — z.B. ein gerade angelegtes Event soll sofort wählbar sein
  if (form.OrganizationID) store.fetchOptions(form.OrganizationID, true).catch(() => {})
  // Auf die Galerie gezogene Dateien gleich übernehmen
  if (files.length) addFiles(files)
  modal.value?.open()
}

function close() {
  if (busy.value && !confirm('Es werden noch Fotos hochgeladen. Wirklich abbrechen?')) return
  busy.value = false
  modal.value?.close()
}

let keyCounter = 0
function onFilesSelected(event) {
  const files = [...(event.target.files ?? [])]
  if (fileInput.value) fileInput.value.value = ''
  addFiles(files)
}

/**
 * Dateien in die Warteschlange — nur unterstützte Bildformate, der Rest wird mit Hinweis
 * übersprungen. Auch für Dateien, die auf die Galerie gezogen wurden.
 * @returns {number} Anzahl übernommener Dateien
 */
function addFiles(files) {
  const images = files.filter(isAcceptedImage)
  const skipped = files.filter(f => !isAcceptedImage(f))
  error.value = skipped.length
    ? `${skipped.length === 1 ? `„${skipped[0].name}“ ist` : `${skipped.length} Dateien sind`} kein unterstütztes Bild und ${skipped.length === 1 ? 'wurde' : 'wurden'} übersprungen (erlaubt: ${ACCEPTED_LABEL}).`
    : null
  queue.value = [
    ...queue.value.filter(e => e.status !== 'done' && e.status !== 'duplicate'),
    ...images.map(file => ({ key: ++keyCounter, file, status: 'pending', message: null })),
  ]
  return images.length
}

function statusLabel(entry) {
  return {
    pending: 'Wartet',
    preparing: 'Wird verkleinert…',
    uploading: 'Lädt hoch…',
    done: 'Fertig',
    duplicate: 'Schon vorhanden',
    failed: entry.message || 'Fehlgeschlagen',
  }[entry.status]
}

async function processEntry(entry, settings) {
  try {
    entry.status = 'preparing'
    const [meta, prepared] = await Promise.all([readPhotoMeta(entry.file), preparePhoto(entry.file)])
    if (!busy.value) {
      entry.status = 'pending'
      return
    }
    entry.status = 'uploading'
    const result = await store.upload({ ...settings, file: entry.file, prepared, meta })
    entry.status = result.duplicate ? 'duplicate' : 'done'
  } catch (err) {
    entry.status = 'failed'
    entry.message = err.message
  }
}

async function start() {
  if (!canStart.value) return
  error.value = null
  busy.value = true
  const settings = {
    organizationId: form.OrganizationID,
    eventId: form.EventID || null,
    tagging: taggingFields(tagging.value),
    copyright: form.Copyright.trim(),
  }
  rememberCopyright(settings.copyright)

  const next = () => queue.value.find(e => e.status === 'pending')
  const worker = async () => {
    let entry
    while (busy.value && (entry = next())) {
      await processEntry(entry, settings)
    }
  }
  try {
    await Promise.all(Array.from({ length: PARALLEL }, worker))
  } finally {
    busy.value = false
    if (doneCount.value) emit('uploaded')
    if (settings.tagging.Tags.length || settings.copyright) store.fetchOptions(settings.organizationId, true).catch(() => {})
  }
}

/** Ob das Modal gerade offen ist (dann landen weitere abgelegte Dateien in derselben Warteschlange) */
function isOpen() {
  return !!document.querySelector('dialog.photo-upload-modal[open]')
}

defineExpose({ open, close, addFiles, isOpen })
</script>
