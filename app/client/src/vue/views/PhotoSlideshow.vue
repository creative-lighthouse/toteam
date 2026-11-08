<!--
  Diashow eines Foto-Ordners (Event oder "Ohne Event"), gedacht für einen eigenen Tab —
  z.B. auf einem Bildschirm beim Event. Gestartet über den Play-Knopf in der Galerie.

  URL: /app/photos/slideshow?organization=ID&event=ID|none[&q=Suche]
       bzw. ?mine=1[&q=Suche] für alle Fotos, auf denen man markiert ist

  Bedienung: Leertaste Pause/weiter, ←/→ blättern, F Vollbild. Die Leisten verschwinden nach
  ein paar Sekunden ohne Mausbewegung. Tempo und Reihenfolge (chronologisch/zufällig) merkt
  sich der Browser. Solange die Diashow läuft, bleibt der Bildschirm an (Wake Lock).
-->
<template>
  <div
    ref="rootEl"
    class="photo-slideshow"
    :class="{ 'photo-slideshow--idle': idle && !menuOpen }"
    @mousemove="wake"
    @click="onStageClick"
    @touchstart.passive="wake"
  >
    <p v-if="error" class="photo-slideshow_message">{{ error }}</p>
    <p v-else-if="loading" class="photo-slideshow_message">Lade Fotos…</p>
    <p v-else-if="!photos.length" class="photo-slideshow_message">In diesem Ordner gibt es keine Fotos.</p>

    <!-- Überblenden: beide Bilder liegen während des Wechsels übereinander -->
    <Transition name="photo-slideshow-fade">
      <img
        v-if="current"
        :key="current.ID"
        :src="current.URL"
        :alt="current.Title || ''"
        class="photo-slideshow_image"
        :style="current.ThumbURL ? { backgroundImage: `url(&quot;${current.ThumbURL}&quot;)` } : null"
        @load="onImageLoaded"
        @error="onImageError"
      >
    </Transition>

    <div v-if="current" class="photo-slideshow_caption">
      <p class="photo-slideshow_caption-title">{{ current.Title || formatPhotoDateTime(current.TakenAt || current.Created) }}</p>
      <p class="photo-slideshow_caption-meta">
        <template v-if="current.Title">{{ formatPhotoDateTime(current.TakenAt || current.Created) }}</template>
        <template v-if="current.Title && current.Copyright"> · </template>
        <template v-if="current.Copyright">{{ formatCopyright(current.Copyright) }}</template>
      </p>
    </div>

    <div class="photo-slideshow_bar" @click.stop>
      <p class="photo-slideshow_title">
        {{ folderTitle }}
        <span v-if="photos.length" class="photo-slideshow_counter">{{ position + 1 }} / {{ total || photos.length }}</span>
      </p>

      <div class="photo-slideshow_controls">
        <button type="button" class="photo-slideshow_button" aria-label="Vorheriges Foto" title="Zurück (←)" :disabled="photos.length < 2" @click="step(-1)">‹</button>
        <button
          type="button"
          class="photo-slideshow_button photo-slideshow_button--play"
          :aria-label="playing ? 'Pausieren' : 'Abspielen'"
          :title="playing ? 'Pause (Leertaste)' : 'Abspielen (Leertaste)'"
          :disabled="!photos.length"
          @click="togglePlay"
        >
          <svg v-if="playing" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1" /><rect x="14" y="5" width="4" height="14" rx="1" /></svg>
          <svg v-else viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" /></svg>
        </button>
        <button type="button" class="photo-slideshow_button" aria-label="Nächstes Foto" title="Weiter (→)" :disabled="photos.length < 2" @click="step(1)">›</button>

        <label class="photo-slideshow_speed">
          <span>Tempo</span>
          <select v-model.number="interval" @focus="menuOpen = true" @blur="menuOpen = false">
            <option v-for="s in SPEEDS" :key="s" :value="s">{{ s }} s</option>
          </select>
        </label>

        <button
          type="button"
          class="photo-slideshow_button photo-slideshow_button--text"
          :aria-pressed="shuffle"
          title="Zufällige Reihenfolge"
          @click="shuffle = !shuffle"
        >Zufällig</button>

        <button
          type="button"
          class="photo-slideshow_button"
          :aria-label="fullscreen ? 'Vollbild beenden' : 'Vollbild'"
          :title="fullscreen ? 'Vollbild beenden (F)' : 'Vollbild (F)'"
          @click="toggleFullscreen"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <template v-if="fullscreen"><path d="M9 4v5H4" /><path d="M15 4v5h5" /><path d="M9 20v-5H4" /><path d="M15 20v-5h5" /></template>
            <template v-else><path d="M4 9V4h5" /><path d="M20 9V4h-5" /><path d="M4 15v5h5" /><path d="M20 15v5h-5" /></template>
          </svg>
        </button>
      </div>
    </div>

    <!-- Fortschritt bis zum nächsten Foto -->
    <div v-if="playing && current" :key="`${current.ID}-${interval}-${progressKey}`" class="photo-slideshow_progress" :style="{ animationDuration: `${interval}s` }" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { apiGet } from '@utils/api'
import { formatCopyright, formatPhotoDateTime } from '@utils/photoUpload'

const route = useRoute()

const SPEEDS = [3, 5, 8, 12, 20, 30]
const SPEED_KEY = 'toteam_slideshow_interval'
const SHUFFLE_KEY = 'toteam_slideshow_shuffle'
// Signierte Bild-Links laufen nach einigen Stunden ab — vorher die Liste neu holen
const REFRESH_AFTER = 3 * 3600 * 1000
const IDLE_AFTER = 3000

function readSetting(key, fallback) {
  try {
    return localStorage.getItem(key) ?? fallback
  } catch {
    return fallback
  }
}
function writeSetting(key, value) {
  try {
    localStorage.setItem(key, String(value))
  } catch {
    // egal
  }
}

const rootEl = ref(null)
const photos = ref([])
const total = ref(0)
const loading = ref(true)
const error = ref(null)
const folderTitle = ref('Diashow')
// Reihenfolge als Liste von Indizes in `photos` (chronologisch oder gemischt)
const order = ref([])
const position = ref(0)
const playing = ref(true)
const interval = ref(Number(route.query.interval) || Number(readSetting(SPEED_KEY, 5)) || 5)
const shuffle = ref(readSetting(SHUFFLE_KEY, '0') === '1')
const fullscreen = ref(false)
const idle = ref(false)
const menuOpen = ref(false)
const progressKey = ref(0)

const current = computed(() => photos.value[order.value[position.value]] ?? null)

// ── Laden ──────────────────────────────────────────────────────────────

function listQuery(offset) {
  const params = new URLSearchParams()
  for (const key of ['organization', 'event', 'q', 'mine']) {
    if (route.query[key]) params.set(key, route.query[key])
  }
  if (offset) params.set('offset', offset)
  return `/photos?${params}`
}

/** Alle Fotos des Ordners laden (seitenweise); die Diashow startet schon mit der ersten Seite */
async function loadPhotos() {
  const keepId = current.value?.ID ?? null
  const loaded = []
  let offset = 0
  let first = true
  for (;;) {
    const response = await apiGet(listQuery(offset), false)
    const page = response?.photos ?? []
    loaded.push(...page)
    total.value = response?.total ?? loaded.length
    if (first && !photos.value.length) {
      // Sofort loslegen, der Rest kommt im Hintergrund dazu
      photos.value = [...loaded]
      buildOrder()
      loading.value = false
      first = false
    }
    if (!response?.hasMore || !page.length) break
    offset = loaded.length
  }
  photos.value = loaded
  buildOrder(keepId)
  loading.value = false
}

async function loadFolderTitle() {
  if (route.query.mine) {
    folderTitle.value = 'Meine Fotos'
    document.title = 'Diashow: Meine Fotos'
    return
  }
  const orgId = Number(route.query.organization)
  if (!orgId) return
  try {
    const response = await apiGet(`/photos/folders/${orgId}`, false)
    const data = response?.data
    if (!data) return
    const event = route.query.event === 'none'
      ? null
      : data.events.find(e => e.ID === Number(route.query.event))
    folderTitle.value = event ? event.Title : `${data.organization.Title} – Ohne Event`
    document.title = `Diashow: ${folderTitle.value}`
  } catch {
    // Titel ist nur Beiwerk
  }
}

// ── Reihenfolge ────────────────────────────────────────────────────────

function buildOrder(keepId = current.value?.ID ?? null) {
  const indices = photos.value.map((_, i) => i)
  if (shuffle.value) {
    for (let i = indices.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [indices[i], indices[j]] = [indices[j], indices[i]]
    }
  }
  order.value = indices
  // Beim Neuordnen beim aktuellen Foto bleiben
  const keep = keepId === null ? -1 : indices.findIndex(i => photos.value[i].ID === keepId)
  position.value = keep >= 0 ? keep : 0
}

watch(shuffle, (value) => {
  writeSetting(SHUFFLE_KEY, value ? '1' : '0')
  buildOrder()
})

watch(interval, (value) => {
  writeSetting(SPEED_KEY, value)
  schedule()
})

// ── Abspielen ──────────────────────────────────────────────────────────

let timer = null

function schedule() {
  clearTimeout(timer)
  progressKey.value++
  if (!playing.value || photos.value.length < 2) return
  timer = setTimeout(() => step(1), interval.value * 1000)
}

function step(direction) {
  const count = order.value.length
  if (!count) return
  const next = position.value + direction
  if (next >= count && shuffle.value) {
    // Neue Runde neu mischen
    buildOrder(null)
    position.value = 0
  } else {
    position.value = (next + count) % count
  }
  preloadNext()
  schedule()
}

function togglePlay() {
  playing.value = !playing.value
  schedule()
}

// Das übernächste Bild vorladen, damit das Überblenden nicht auf das Laden warten muss
function preloadNext() {
  const count = order.value.length
  if (count < 2) return
  const next = photos.value[order.value[(position.value + 1) % count]]
  if (next?.URL) new Image().src = next.URL
}

function onImageLoaded() {
  preloadNext()
}

// Abgelaufener Link (oder Netzwerkfehler): Liste mit frischen Links holen, weitermachen
let refreshing = false
async function onImageError() {
  if (refreshing) return
  refreshing = true
  try {
    await loadPhotos()
  } catch {
    step(1)
  } finally {
    refreshing = false
  }
}

// ── Leisten ausblenden, Tastatur, Vollbild ─────────────────────────────

let idleTimer = null
function wake() {
  idle.value = false
  clearTimeout(idleTimer)
  idleTimer = setTimeout(() => { idle.value = true }, IDLE_AFTER)
}

// Klick aufs Bild: Pause/weiter (wie bei Video-Playern)
function onStageClick() {
  wake()
  if (photos.value.length) togglePlay()
}

function onKeydown(event) {
  if (event.target.closest?.('select, input, textarea')) return
  wake()
  if (event.key === ' ') {
    event.preventDefault()
    togglePlay()
  } else if (event.key === 'ArrowRight') {
    step(1)
  } else if (event.key === 'ArrowLeft') {
    step(-1)
  } else if (event.key === 'f' || event.key === 'F') {
    toggleFullscreen()
  }
}

function toggleFullscreen() {
  if (document.fullscreenElement) document.exitFullscreen?.()
  else rootEl.value?.requestFullscreen?.().catch(() => {})
}

function onFullscreenChange() {
  fullscreen.value = !!document.fullscreenElement
}

// Bildschirm anlassen, solange die Diashow läuft (wird beim Tab-Wechsel vom Browser
// freigegeben und beim Zurückkommen neu angefordert)
let wakeLock = null
async function requestWakeLock() {
  if (!playing.value || document.visibilityState !== 'visible') return
  try {
    wakeLock = await navigator.wakeLock?.request('screen')
  } catch {
    wakeLock = null
  }
}
function releaseWakeLock() {
  wakeLock?.release?.().catch(() => {})
  wakeLock = null
}
watch(playing, (value) => (value ? requestWakeLock() : releaseWakeLock()))

function onVisibilityChange() {
  if (document.visibilityState === 'visible') requestWakeLock()
}

let refreshTimer = null

onMounted(async () => {
  document.title = 'Diashow'
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('fullscreenchange', onFullscreenChange)
  document.addEventListener('visibilitychange', onVisibilityChange)
  wake()
  loadFolderTitle()
  try {
    await loadPhotos()
    schedule()
    requestWakeLock()
  } catch (err) {
    error.value = err.message || 'Fotos konnten nicht geladen werden'
    loading.value = false
  }
  refreshTimer = setInterval(() => loadPhotos().catch(() => {}), REFRESH_AFTER)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  clearTimeout(idleTimer)
  clearInterval(refreshTimer)
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  releaseWakeLock()
})
</script>
