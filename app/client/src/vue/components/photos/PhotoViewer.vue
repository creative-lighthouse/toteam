<!--
  Vollbild-Betrachter des Foto-Totems: wischen/Pfeiltasten zum Blättern, zoomen,
  Infos und Tags im Seitenbereich (Handy: Blatt von unten).

    <PhotoViewer
      :photos="store.items"      // Kurzform, Reihenfolge = Blätterreihenfolge
      :photo-id="openId"         // null = geschlossen
      :total="store.total"
      :has-more="store.hasMore"
      @change="id => …"          // anderes Foto angezeigt (z.B. URL anpassen)
      @close="…"
      @load-more="store.fetchMore()"
    />

  Ein Foto, das (noch) nicht in `photos` steht (z.B. per Link geöffnet), wird allein angezeigt.
-->
<template>
  <Teleport to="body">
    <dialog
      ref="dialogEl"
      class="photo-viewer"
      :class="{
        'photo-viewer--chrome-hidden': !chromeVisible,
        'photo-viewer--info-open': infoOpen,
      }"
      aria-label="Fotoansicht"
      @cancel.prevent="emit('close')"
      @keydown="onKeydown"
    >
      <div class="photo-viewer_main">
        <PhotoViewerStage
          ref="stage"
          :prev="prevPhoto"
          :current="currentPhoto"
          :next="nextPhoto"
          @prev="show(prevPhoto)"
          @next="show(nextPhoto)"
          @close="emit('close')"
          @tap="chromeVisible = !chromeVisible"
        />

        <div class="photo-viewer_bar">
          <button type="button" class="photo-viewer_button" aria-label="Schließen" title="Schließen (Esc)" @click="emit('close')">✕</button>
          <p v-if="position" class="photo-viewer_counter">{{ position }} / {{ total }}</p>
          <button
            type="button"
            class="photo-viewer_button photo-viewer_button--info"
            :aria-pressed="infoOpen"
            aria-label="Informationen und Tags"
            title="Informationen (i)"
            @click="toggleInfo"
          >i</button>
        </div>

        <button
          v-if="prevPhoto"
          type="button"
          class="photo-viewer_nav photo-viewer_nav--prev"
          aria-label="Vorheriges Foto"
          @click="stage?.go(1)"
        >‹</button>
        <button
          v-if="nextPhoto"
          type="button"
          class="photo-viewer_nav photo-viewer_nav--next"
          aria-label="Nächstes Foto"
          @click="stage?.go(-1)"
        >›</button>

        <div v-if="caption" class="photo-viewer_caption">
          <div class="photo-viewer_caption-text">
            <p class="photo-viewer_caption-title">{{ caption.title }}</p>
            <p v-if="caption.meta" class="photo-viewer_caption-meta">{{ caption.meta }}</p>
            <p v-if="downloadError" class="photo-viewer_caption-error" role="alert">{{ downloadError }}</p>
          </div>
          <button
            type="button"
            class="photo-viewer_button photo-viewer_button--download"
            :aria-label="downloading ? 'Foto wird heruntergeladen…' : 'Foto herunterladen'"
            :title="downloading ? 'Wird heruntergeladen…' : 'Herunterladen'"
            :disabled="downloading"
            @click="downloadCurrent"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 4v11" />
              <path d="m7 10 5 5 5-5" />
              <path d="M5 20h14" />
            </svg>
          </button>
        </div>
      </div>

      <Transition name="photo-viewer-info">
        <PhotoInfoPanel
          v-if="infoOpen"
          class="photo-viewer_info"
          :photo="currentDetail"
          :options="currentOptions"
          :busy="busy"
          :error="actionError"
          @close="toggleInfo"
          @change="applyChange"
          @untag="untag"
          @remove="remove"
        />
      </Transition>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { usePhotosStore } from '@stores/photos'
import { formatPhotoDate } from '@utils/photoUpload'
import PhotoViewerStage from '@components/photos/PhotoViewerStage.vue'
import PhotoInfoPanel from '@components/photos/PhotoInfoPanel.vue'

const props = defineProps({
  photos: { type: Array, required: true },
  photoId: { type: Number, default: null },
  total: { type: Number, default: 0 },
  hasMore: { type: Boolean, default: false },
})

const emit = defineEmits(['change', 'close', 'load-more'])
const store = usePhotosStore()

const INFO_KEY = 'toteam_photo_info'

const dialogEl = ref(null)
const stage = ref(null)
const currentId = ref(null)
const chromeVisible = ref(true)
const busy = ref(false)
const downloading = ref(false)
const downloadError = ref(null)
const actionError = ref(null)
// Detailform je Foto-ID (Tags, Rechte …), wird beim Anzeigen nachgeladen
const details = reactive({})

function readInfoPreference() {
  try {
    const stored = localStorage.getItem(INFO_KEY)
    if (stored !== null) return stored === '1'
  } catch {
    // Speicher nicht verfügbar
  }
  // Ohne gespeicherte Wahl: auf großen Bildschirmen offen, auf dem Handy zu
  return window.matchMedia?.('(min-width: 1000px)').matches ?? false
}
const infoOpen = ref(readInfoPreference())

function toggleInfo() {
  infoOpen.value = !infoOpen.value
  try {
    localStorage.setItem(INFO_KEY, infoOpen.value ? '1' : '0')
  } catch {
    // egal
  }
}

// ── Aktuelles Foto und Nachbarn ────────────────────────────────────────

const index = computed(() => props.photos.findIndex(p => p.ID === currentId.value))
const currentDetail = computed(() => details[currentId.value] ?? null)
// Auswahl zum Taggen (Personen, Charaktere, Events, Schlagworte) der Organisation des Fotos
const currentOptions = computed(() => (currentDetail.value ? store.optionsByOrg[currentDetail.value.OrganizationID] ?? null : null))
const currentPhoto = computed(() => (index.value >= 0 ? props.photos[index.value] : currentDetail.value))
const prevPhoto = computed(() => (index.value > 0 ? props.photos[index.value - 1] : null))
const nextPhoto = computed(() => (index.value >= 0 && index.value < props.photos.length - 1 ? props.photos[index.value + 1] : null))
const position = computed(() => (index.value >= 0 ? index.value + 1 : 0))

const caption = computed(() => {
  const p = currentDetail.value ?? currentPhoto.value
  if (!p) return null
  const date = formatPhotoDate(p.TakenAt || p.Created)
  return p.Title ? { title: p.Title, meta: date } : { title: date, meta: null }
})

function show(photo) {
  if (!photo || photo.ID === currentId.value) return
  currentId.value = photo.ID
  actionError.value = null
  downloadError.value = null
  emit('change', photo.ID)
}

async function loadDetail(id, force = false) {
  if (!id || (details[id] && !force)) return
  try {
    details[id] = await store.fetchDetail(id)
    loadOptions(details[id])
  } catch (err) {
    if (id === currentId.value) actionError.value = err.message
  }
}

// Auswahllisten je Organisation einmal pro Öffnen frisch holen (neue Events, Personen …)
const freshOptions = new Set()
function loadOptions(photo) {
  if (!photo?.CanEdit) return
  const orgId = photo.OrganizationID
  store.fetchOptions(orgId, !freshOptions.has(orgId)).catch(() => {})
  freshOptions.add(orgId)
}

watch(currentId, (id) => {
  loadDetail(id)
  // Kurz vor dem Ende der geladenen Fotos die nächsten holen
  if (props.hasMore && index.value >= props.photos.length - 4) emit('load-more')
})

// ── Öffnen/Schließen ───────────────────────────────────────────────────

function sync(id) {
  const el = dialogEl.value
  if (!el) return
  if (id) {
    if (id !== currentId.value) {
      currentId.value = id
      actionError.value = null
    }
    if (!el.open) {
      chromeVisible.value = true
      freshOptions.clear()
      el.showModal()
      nextTick(() => el.focus())
    }
  } else if (el.open) {
    stage.value?.reset()
    el.close()
  }
}

watch(() => props.photoId, sync)
onMounted(() => sync(props.photoId))

// ── Tastatur ───────────────────────────────────────────────────────────

function onKeydown(event) {
  if (event.target.closest?.('input, textarea, select') || event.ctrlKey || event.metaKey || event.altKey) return
  if (event.key === 'ArrowRight') {
    event.preventDefault()
    stage.value?.go(-1)
  } else if (event.key === 'ArrowLeft') {
    event.preventDefault()
    stage.value?.go(1)
  } else if (event.key === 'i') {
    toggleInfo()
  } else if (event.key === 'z') {
    stage.value?.toggleZoom()
  }
}

// ── Aktionen ───────────────────────────────────────────────────────────

async function downloadCurrent() {
  const photo = currentDetail.value ?? currentPhoto.value
  if (!photo || downloading.value) return
  downloading.value = true
  downloadError.value = null
  try {
    await store.download(photo)
  } catch (err) {
    downloadError.value = err.message
  } finally {
    downloading.value = false
  }
}

// Änderung aus dem Info-Bereich ({ field, set | add | remove }) in die Felder für PUT
// /photos/update übersetzen — immer gegen den neuesten Stand, daher erst in der Warteschlange
function fieldsFor(detail, change) {
  const { field } = change
  if (field === 'Title' || field === 'Description' || field === 'Copyright') return { [field]: change.set }
  if (field === 'Events') return { EventIDs: change.set ? [change.set] : [] }
  if (field === 'People' || field === 'Characters') {
    let ids = detail[field].map(x => x.ID)
    if (change.add) ids = ids.includes(change.add) ? ids : [...ids, change.add]
    if (change.remove) ids = ids.filter(x => x !== change.remove)
    return { [field === 'People' ? 'PeopleIDs' : 'CharacterIDs']: ids }
  }
  if (field === 'Tags') {
    let titles = detail.Tags.map(t => t.Title)
    if (change.add) titles = [...titles, change.add]
    if (change.remove) titles = titles.filter(t => t !== change.remove)
    return { Tags: titles }
  }
  return null
}

// Änderungen nacheinander speichern — schnelles Taggen mehrerer Personen überschreibt sich so nicht
let saveQueue = Promise.resolve()
function applyChange(change) {
  const id = currentId.value
  saveQueue = saveQueue.then(async () => {
    const detail = details[id]
    const fields = detail && fieldsFor(detail, change)
    if (!fields) return
    actionError.value = null
    try {
      details[id] = await store.update(id, fields)
    } catch (err) {
      if (id === currentId.value) actionError.value = err.message
    }
  })
}

async function untag() {
  busy.value = true
  actionError.value = null
  try {
    const photo = await store.untagMe(currentId.value)
    details[photo.ID] = photo
  } catch (err) {
    actionError.value = err.message
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (!confirm('Dieses Foto wirklich löschen? Es wird auch im Bunny-Speicher der Organisation gelöscht.')) return
  const id = currentId.value
  // Danach das nächste Foto zeigen (am Ende das vorherige)
  const neighbor = nextPhoto.value ?? prevPhoto.value
  busy.value = true
  actionError.value = null
  try {
    await store.remove(id)
    delete details[id]
    if (neighbor) show(neighbor)
    else emit('close')
  } catch (err) {
    actionError.value = err.message
  } finally {
    busy.value = false
  }
}
</script>
