<template>
  <div class="section section--PhotosPage">
    <div class="section_content">

      <!-- Wo bin ich? Organisationen › Organisation › Event -->
      <nav v-if="level !== 'orgs'" class="photos-breadcrumb" aria-label="Ordner">
        <ol>
          <li v-if="store.organizations.length > 1">
            <router-link :to="{ query: {} }">Alle Organisationen</router-link>
          </li>
          <li>
            <router-link v-if="level === 'photos'" :to="{ query: { organization: orgId } }">{{ currentOrg?.Title ?? '…' }}</router-link>
            <span v-else aria-current="page">{{ currentOrg?.Title ?? '…' }}</span>
          </li>
          <li v-if="level === 'photos'">
            <span aria-current="page">{{ eventTitle }}</span>
          </li>
        </ol>
      </nav>

      <!-- In einem Ordner: Suche, Ansicht, Hochladen -->
      <PhotoSearchBar v-if="level === 'photos'" v-model="search" :options="options">
        <template #actions>
          <AppViewToggle v-model="viewMode" :options="VIEW_MODES" />
          <AppIconButton
            variant="neutral"
            aria-label="Diashow in neuem Tab starten"
            title="Diashow in neuem Tab starten"
            :disabled="!store.total"
            @click="openSlideshow"
          >
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" /></svg>
          </AppIconButton>
          <!-- Kein Zahnrad hier: der Foto-Speicher gehört zur Organisation, nicht zum Event -->
          <AppButton
            v-if="currentOrg?.CanUpload && currentOrg?.Configured"
            variant="primary"
            @click="openUpload"
          >+ Hochladen</AppButton>
        </template>
      </PhotoSearchBar>

      <div v-else-if="settingsOrgIds.length" class="photos-toolbar">
        <div class="photos-toolbar_actions">
          <AppIconButton
            variant="neutral"
            aria-label="Foto-Speicher einrichten"
            title="Foto-Speicher (Bunny) einrichten"
            @click="openSettings"
          ><span class="icon-mask" :style="settingsIconStyle" /></AppIconButton>
        </div>
      </div>

      <div v-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="loadLevel">Erneut versuchen</AppButton>
      </div>

      <!-- Ebene 1: Organisationen -->
      <template v-else-if="level === 'orgs'">
        <div v-if="store.loading" class="section_infobox"><p>Lade Organisationen…</p></div>
        <div v-else-if="!store.organizations.length" class="section_infobox">
          <p>In keiner deiner Organisationen ist bisher ein Foto-Speicher eingerichtet.</p>
        </div>
        <ul v-else class="photos-folders">
          <li v-for="org in store.organizations" :key="org.ID">
            <PhotoFolder
              :to="{ query: { organization: org.ID } }"
              :title="org.Title"
              :subtitle="org.Configured ? photoCount(org.PhotoCount) : 'Noch nicht eingerichtet'"
              :color="orgAccentColor(org)"
              logo
              :logo-url="org.LogoURL"
              :muted="!org.Configured"
            />
          </li>
        </ul>
      </template>

      <!-- Ebene 2: Events der Organisation -->
      <template v-else-if="level === 'events'">
        <div v-if="currentOrg && !currentOrg.Configured" class="photos-setup">
          <p>
            <strong>{{ currentOrg.Title }}</strong> hat noch keinen Foto-Speicher.
            Fotos liegen im eigenen Bunny-Account der Organisation – richte ihn einmal ein, dann kann es losgehen.
          </p>
          <AppButton v-if="currentOrg.CanManageStorage" variant="primary" size="small" @click="openSettings">Einrichten</AppButton>
        </div>

        <div v-if="store.foldersLoading" class="section_infobox"><p>Lade Ordner…</p></div>
        <ul v-else-if="store.folders" class="photos-folders">
          <li v-for="ev in store.folders.events" :key="ev.ID">
            <PhotoFolder
              :to="{ query: { organization: orgId, event: ev.ID } }"
              :title="ev.Title"
              :subtitle="photoCount(ev.PhotoCount)"
              :color="folderColor"
              :label="eventYear(ev)"
            />
          </li>
          <li>
            <PhotoFolder
              :to="{ query: { organization: orgId, event: 'none' } }"
              title="Ohne Event"
              :subtitle="photoCount(store.folders.withoutEvent)"
              muted
            />
          </li>
        </ul>
      </template>

      <!-- Ebene 3: die Fotos eines Events bzw. ohne Event -->
      <template v-else>
        <div v-if="store.loading" class="section_infobox"><p>Lade Fotos…</p></div>
        <div v-else-if="store.items.length === 0" class="section_infobox">
          <p v-if="activeSearch">Keine Fotos zu „{{ activeSearch }}“.</p>
          <p v-else>Hier gibt es noch keine Fotos.<template v-if="currentOrg?.CanUpload && currentOrg?.Configured"> Lade die ersten über „+ Hochladen“ hoch.</template></p>
        </div>
        <template v-else>
          <p class="photos-count">{{ photoCount(store.total) }}</p>
          <PhotoList
            v-if="viewMode === 'list'"
            :photos="store.items"
            :downloading="downloading"
            @open="openPhoto"
            @download="download"
          />
          <PhotoGridLarge
            v-else-if="viewMode === 'large'"
            :photos="store.items"
            :downloading="downloading"
            @open="openPhoto"
            @download="download"
          />
          <PhotoGrid v-else :photos="store.items" @open="openPhoto" />
          <p v-if="downloadError" class="photos-error" role="alert">{{ downloadError }}</p>
          <div v-if="store.hasMore" ref="moreEl" class="photos-more">
            <AppButton variant="secondary" :disabled="store.loadingMore" @click="store.fetchMore()">
              {{ store.loadingMore ? 'Lade…' : 'Mehr laden' }}
            </AppButton>
          </div>
        </template>
      </template>
    </div>

    <PhotoUploadModal ref="uploadModal" @uploaded="onUploaded" />
    <PhotoDropOverlay :state="dropState" :target="`${currentOrg?.Title ?? ''} › ${eventTitle}`" />
    <PhotoViewer
      :photos="store.items"
      :photo-id="viewerId"
      :total="store.total"
      :has-more="store.hasMore"
      @change="openPhoto"
      @close="onViewerClosed"
      @load-more="store.fetchMore()"
    />
    <PhotoStorageSettingsModal ref="settingsModal" @saved="loadLevel" />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePhotosStore } from '@stores/photos'
import { usePageHeaderStore } from '@stores/pageHeader'
import { orgAccentColor } from '@utils/orgColor'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import PhotoFolder from '@components/photos/PhotoFolder.vue'
import AppViewToggle from '@components/ui/AppViewToggle.vue'
import PhotoGrid from '@components/photos/PhotoGrid.vue'
import PhotoGridLarge from '@components/photos/PhotoGridLarge.vue'
import PhotoList from '@components/photos/PhotoList.vue'
import PhotoSearchBar from '@components/photos/PhotoSearchBar.vue'
import PhotoUploadModal from '@components/photos/PhotoUploadModal.vue'
import PhotoDropOverlay from '@components/photos/PhotoDropOverlay.vue'
import { classifyDraggedItems } from '@utils/photoUpload'
import PhotoViewer from '@components/photos/PhotoViewer.vue'
import PhotoStorageSettingsModal from '@components/photos/PhotoStorageSettingsModal.vue'
import actionSettings from '../../../icons/actions/action_settings.svg'

const route = useRoute()
const router = useRouter()
const store = usePhotosStore()
const pageHeader = usePageHeaderStore()

const uploadModal = ref(null)
const settingsModal = ref(null)
const moreEl = ref(null)

const settingsIconStyle = { maskImage: `url("${actionSettings}")`, WebkitMaskImage: `url("${actionSettings}")` }

// ── Wo bin ich? (alles steht in der URL, damit Zurück und Links funktionieren) ──
// ?organization=ID            → Event-Ordner der Organisation
// ?organization=ID&event=ID   → Fotos des Events (event=none: ohne Event)
// ?photo=ID                   → Foto im Betrachter

// Wer nur eine Organisation hat, startet gleich in deren Ordnern
const orgId = computed(() => Number(route.query.organization)
  || (store.organizations.length === 1 ? store.organizations[0].ID : 0))
const eventKey = computed(() => {
  const value = route.query.event
  if (value === 'none') return 'none'
  return Number(value) || null
})
const level = computed(() => {
  if (orgId.value && eventKey.value) return 'photos'
  if (orgId.value) return 'events'
  return 'orgs'
})

const currentOrg = computed(() => store.organizations.find(o => o.ID === orgId.value)
  ?? (store.folders?.organization.ID === orgId.value ? store.folders.organization : null))
const options = computed(() => store.optionsByOrg[orgId.value] ?? null)
const folderColor = computed(() => orgAccentColor(currentOrg.value))

const eventTitle = computed(() => {
  if (eventKey.value === 'none') return 'Ohne Event'
  return options.value?.events.find(e => e.ID === eventKey.value)?.Title
    ?? store.folders?.events.find(e => e.ID === eventKey.value)?.Title
    ?? 'Event'
})

const settingsOrgIds = computed(() => (level.value === 'orgs'
  ? store.organizations.filter(o => o.CanManageStorage).map(o => o.ID)
  : (currentOrg.value?.CanManageStorage ? [currentOrg.value.ID] : [])))

// ── Suche innerhalb eines Ordners (?q=, siehe PhotoSearchBar) ─────────
const search = ref(String(route.query.q ?? ''))
const activeSearch = computed(() => String(route.query.q ?? '').trim())

// Erst nach einer kurzen Tipp-Pause suchen
let searchTimer = null
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    const query = { ...route.query }
    if (value.trim()) query.q = value.trim()
    else delete query.q
    if ((query.q ?? '') !== (route.query.q ?? '')) router.replace({ query })
  }, 300)
})

// Anderer Ordner (z.B. über den Pfad oben): Suche leeren
watch(() => route.query.q, (q) => {
  if ((q ?? '') !== search.value.trim()) search.value = String(q ?? '')
})

// ── Ansicht: Liste, kleine oder große Kacheln (pro Gerät gemerkt) ─────
const VIEW_KEY = 'toteam_photo_view'
const VIEW_MODES = [
  { value: 'list', label: 'Listenansicht', icon: 'list' },
  { value: 'small', label: 'Kleine Kacheln', icon: 'grid-small' },
  { value: 'large', label: 'Große Kacheln', icon: 'grid' },
]
function readViewMode() {
  try {
    const stored = localStorage.getItem(VIEW_KEY)
    if (VIEW_MODES.some(m => m.value === stored)) return stored
  } catch {
    // Speicher nicht verfügbar
  }
  return 'small'
}
const viewMode = ref(readViewMode())
watch(viewMode, (mode) => {
  try {
    localStorage.setItem(VIEW_KEY, mode)
  } catch {
    // egal
  }
})

// ── Download aus Liste und großen Kacheln ──────────────────────────────
const downloading = ref(new Set())
const downloadError = ref(null)

async function download(photo) {
  if (downloading.value.has(photo.ID)) return
  downloading.value = new Set([...downloading.value, photo.ID])
  downloadError.value = null
  try {
    await store.download(photo)
  } catch (err) {
    downloadError.value = err.message
  } finally {
    const next = new Set(downloading.value)
    next.delete(photo.ID)
    downloading.value = next
  }
}

// ── Laden je Ebene ─────────────────────────────────────────────────────

function photoCount(n) {
  return `${n} ${n === 1 ? 'Foto' : 'Fotos'}`
}

function eventYear(ev) {
  return ev.DateStart ? ev.DateStart.slice(0, 4) : ''
}

let lastFolderKey = null
async function loadLevel() {
  if (!store.organizations.length || level.value === 'orgs') await store.fetchOrganizations()
  if (level.value === 'events') {
    await store.fetchFolders(orgId.value)
  } else if (level.value === 'photos') {
    // Anderer Ordner: nicht kurz die Fotos des vorherigen zeigen
    const folderKey = `${orgId.value}/${eventKey.value}`
    if (folderKey !== lastFolderKey) {
      store.items = []
      lastFolderKey = folderKey
    }
    store.fetchOptions(orgId.value).catch(() => {})
    if (!store.folders || store.folders.organization.ID !== orgId.value) store.fetchFolders(orgId.value)
    await store.fetchList({
      organization: orgId.value,
      event: eventKey.value,
      q: activeSearch.value,
    })
  }
}

watch(
  () => [level.value, orgId.value, eventKey.value, activeSearch.value],
  loadLevel,
  { immediate: true },
)

// Kopfzeile passend zur Ebene
watch([level, currentOrg, eventTitle], () => {
  if (level.value === 'orgs') pageHeader.setHeader('Fotos', 'Die Bilder deiner Organisationen.')
  else if (level.value === 'events') pageHeader.setHeader('Fotos', currentOrg.value?.Title ?? '')
  else pageHeader.setHeader(eventTitle.value, currentOrg.value?.Title ?? 'Fotos')
}, { immediate: true })

// ── Aktionen ───────────────────────────────────────────────────────────

function openUpload(files = []) {
  uploadModal.value?.open({
    organizationId: orgId.value,
    eventId: eventKey.value === 'none' ? 0 : eventKey.value,
    fixed: true,
    files,
  })
}

// ── Fotos auf die Seite ziehen (nur in einem Ordner mit Upload-Recht) ──
// Die Ereignisse hängen am Fenster, damit die ganze Fläche zählt. Statt dragenter/dragleave
// zu zählen (feuern bei jedem Kindelement, und bei abgelehnten Dateien fehlt das Ende) blendet
// ein kurzer Timer die Überlagerung aus, sobald keine dragover-Ereignisse mehr kommen.
const canDrop = computed(() => level.value === 'photos' && !!currentOrg.value?.CanUpload && !!currentOrg.value?.Configured)
const dropState = ref(null)
let dragHideTimer = null

function draggingFiles(event) {
  return [...(event.dataTransfer?.types ?? [])].includes('Files')
}

function onDragOver(event) {
  if (!canDrop.value || viewerId.value || !draggingFiles(event)) return
  event.preventDefault()
  if (!dropState.value) dropState.value = classifyDraggedItems(event.dataTransfer)
  event.dataTransfer.dropEffect = dropState.value === 'invalid' ? 'none' : 'copy'
  clearTimeout(dragHideTimer)
  dragHideTimer = setTimeout(() => { dropState.value = null }, 250)
}

function onDrop(event) {
  if (!dropState.value) return
  event.preventDefault()
  const invalid = dropState.value === 'invalid'
  clearTimeout(dragHideTimer)
  dropState.value = null
  const files = [...(event.dataTransfer?.files ?? [])]
  if (!files.length || invalid) return
  // Läuft schon ein Upload-Dialog, kommen die Dateien dazu; sonst öffnet er sich mit ihnen.
  // Die Formatprüfung macht der Dialog (unpassende Dateien werden mit Hinweis übersprungen).
  if (uploadModal.value?.isOpen()) uploadModal.value.addFiles(files)
  else openUpload(files)
}

onMounted(() => {
  // dragenter wie dragover: die Überlagerung erscheint sofort
  window.addEventListener('dragenter', onDragOver)
  window.addEventListener('dragover', onDragOver)
  window.addEventListener('drop', onDrop)
})

// Diashow des aktuellen Ordners (inkl. Suche) im neuen Tab
function openSlideshow() {
  const query = { organization: orgId.value, event: eventKey.value }
  if (activeSearch.value) query.q = activeSearch.value
  const { href } = router.resolve({ name: 'PhotoSlideshow', query })
  window.open(href, '_blank', 'noopener')
}

function onUploaded() {
  store.refresh()
  store.fetchFolders(orgId.value)
}

function openSettings() {
  settingsModal.value?.open(settingsOrgIds.value.length === 1 ? settingsOrgIds.value[0] : null)
}

// ── Betrachter (?photo=) ───────────────────────────────────────────────
const viewerId = computed(() => Number(route.query.photo) || null)

function openPhoto(photoOrId) {
  const id = typeof photoOrId === 'object' ? photoOrId.ID : photoOrId
  router.replace({ query: { ...route.query, photo: id } })
}

// Nach dem Schließen zum zuletzt angesehenen Foto (Fokus und Scrollposition; alle Ansichten
// tragen data-photo-id am Öffnen-Knopf)
async function onViewerClosed() {
  const lastId = viewerId.value
  const { photo: _photo, ...query } = route.query
  await router.replace({ query })
  // Event eines Fotos geändert: Ordnerinhalt und Zähler stimmen nicht mehr
  if (store.listStale) {
    store.fetchFolders(orgId.value)
    await store.refresh()
  }
  await nextTick()
  const el = document.querySelector(`.photo-grid_button[data-photo-id="${lastId}"]`)
  el?.focus({ preventScroll: true })
  el?.scrollIntoView({ block: 'nearest' })
}

// "Mehr laden" automatisch, sobald der Knopf ins Bild scrollt
let observer = null
watch(moreEl, (el) => {
  observer?.disconnect()
  if (!el || typeof IntersectionObserver === 'undefined') return
  observer = new IntersectionObserver((entries) => {
    if (entries.some(e => e.isIntersecting)) store.fetchMore()
  }, { rootMargin: '400px' })
  observer.observe(el)
})
onBeforeUnmount(() => {
  window.removeEventListener('dragenter', onDragOver)
  window.removeEventListener('dragover', onDragOver)
  window.removeEventListener('drop', onDrop)
  clearTimeout(dragHideTimer)
  observer?.disconnect()
  clearTimeout(searchTimer)
})
</script>
