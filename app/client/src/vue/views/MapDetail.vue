<template>
  <div class="section section--MapView">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Lageplan...</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler beim Laden: {{ error }}</p>
        <AppButton to="/map" variant="primary">← Zurück zur Übersicht</AppButton>
      </div>

      <div v-else-if="map" class="map-container">
        <div class="map-controls" :class="{ 'is-hidden': sidebarHidden }">
          <button class="map-controls_toggle" @click="toggleSidebar" aria-label="Sidebar umschalten">
            <span class="map-controls_toggle-icon">›</span>
          </button>

          <div class="map-controls_header">
            <h3>{{ map.title }}</h3>
            <button
              v-if="map.canEdit"
              class="map-edit-toggle"
              :class="{ 'is-active': isEditMode }"
              :title="isEditMode ? 'Bearbeitung beenden' : 'Lageplan bearbeiten'"
              @click="toggleEditMode"
            >
              <svg v-if="!isEditMode" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M11.013 1.427a1.75 1.75 0 012.474 0l1.086 1.086a1.75 1.75 0 010 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 01-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61zm1.414 1.06a.25.25 0 00-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 000-.354l-1.086-1.086zM11.189 6.25L9.75 4.81 3.551 11.01a.25.25 0 00-.065.108l-.569 1.99 1.99-.569a.25.25 0 00.108-.065L11.19 6.25z"/>
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M3.72 3.72a.75.75 0 011.06 0L8 6.94l3.22-3.22a.75.75 0 111.06 1.06L9.06 8l3.22 3.22a.75.75 0 11-1.06 1.06L8 9.06l-3.22 3.22a.75.75 0 01-1.06-1.06L6.94 8 3.72 4.78a.75.75 0 010-1.06z"/>
              </svg>
            </button>
          </div>

          <div class="map-controls_wrap">

            <!-- VIEW MODE -->
            <template v-if="!isEditMode">
              <div v-if="map.layers.length" class="map-controls_layers">
                <h4>Ebenen</h4>
                <div class="map-layers-list">
                  <div v-for="layer in map.layers" :key="layer.id" class="map-layer-item-wrapper">
                    <label class="map-layer-item">
                      <input
                        type="checkbox"
                        class="map-layer-toggle"
                        :checked="layer.active"
                        @change="toggleLayer(layer.id)"
                      />
                      <span class="map-layer-title">{{ layer.title }}</span>
                    </label>
                  </div>
                </div>
              </div>
              <div class="map-controls_info">
                <p v-if="map.shortText">{{ map.shortText }}</p>
              </div>
            </template>

            <!-- EDIT MODE -->
            <template v-else>
              <div class="map-controls_edit">

                <!-- Background image -->
                <div class="edit-bg-image">
                  <h4>Hintergrundbild</h4>
                  <div class="layer-image-preview">
                    <img v-if="bgPreviewUrl" :src="bgPreviewUrl" alt="Hintergrundbild" />
                    <div v-else class="layer-image-placeholder">Kein Bild</div>
                  </div>
                  <input
                    id="bgImageUpload"
                    type="file"
                    class="layer-image-input"
                    accept="image/*"
                    @change="onBgImageSelected"
                  />
                  <label for="bgImageUpload" class="button button--small">
                    {{ pendingBgImage ? '✓ Bild gewählt' : 'Bild ändern' }}
                  </label>
                </div>

                <!-- Layer cards -->
                <div
                  v-for="(editLayer, i) in editLayers"
                  :key="editLayer.id"
                  class="edit-layer-card"
                >
                  <div class="edit-layer-card_header" :style="{ backgroundColor: editLayer.color }">
                    <span>{{ editLayer.title || 'Ebene ' + (i + 1) }}</span>
                    <AppIconButton
                      variant="danger"
                      size="small"
                      :aria-label="`Ebene &quot;${editLayer.title}&quot; löschen`"
                      @click="deleteLayer(i)"
                    >
                      <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M11 1.75V3h2.25a.75.75 0 010 1.5H2.75a.75.75 0 010-1.5H5V1.75C5 .784 5.784 0 6.75 0h2.5C10.216 0 11 .784 11 1.75zM6.5 1.75v1.25h3V1.75a.25.25 0 00-.25-.25h-2.5a.25.25 0 00-.25.25zM4.997 6.5a.75.75 0 00-1.498.076l.492 7.5a.75.75 0 001.498-.076L4.997 6.5zm6.006.076a.75.75 0 10-1.498-.076l-.492 7.5a.75.75 0 001.498.076l.492-7.5z"/>
                      </svg>
                    </AppIconButton>
                  </div>
                  <div class="edit-layer-card_body">
                    <div class="edit-field">
                      <label :for="`lt-${editLayer.id}`">Titel</label>
                      <input
                        :id="`lt-${editLayer.id}`"
                        v-model="editLayer.title"
                        type="text"
                        class="form-control"
                      />
                    </div>
                    <div class="edit-field">
                      <label :for="`lc-${editLayer.id}`">Farbe</label>
                      <div class="color-picker-wrapper">
                        <input
                          :id="`lc-${editLayer.id}`"
                          type="color"
                          v-model="editLayer.color"
                          @input="onLayerColorChange(i)"
                        />
                        <input
                          type="text"
                          v-model="editLayer.color"
                          class="form-control"
                          @change="onLayerColorChange(i)"
                        />
                      </div>
                    </div>
                    <div class="edit-field">
                      <label>Ebenen-Bild</label>
                      <input
                        :id="`li-${editLayer.id}`"
                        type="file"
                        class="layer-image-input"
                        accept="image/*"
                        @change="onLayerImageSelected($event, editLayer.id, i)"
                      />
                      <label :for="`li-${editLayer.id}`" class="button button--small">
                        {{ pendingImages[editLayer.id] ? '✓ Bild gewählt' : 'Bild wählen' }}
                      </label>
                    </div>
                    <AppButton size="small" variant="secondary" @click="addPOIToLayer(i)">
                      + Marker hinzufügen
                    </AppButton>
                  </div>
                </div>

                <AppButton variant="primary" style="margin-top: 8px;" @click="addLayer">
                  + Neue Ebene
                </AppButton>
              </div>
            </template>

          </div>

          <div class="map-controls_actions">
            <template v-if="!isEditMode">
              <AppButton to="/map" variant="primary">← Alle Lagepläne</AppButton>
              <button class="button action_recenter" @click="resetView">
                <div
                  class="resetMapView_button icon--small"
                  style="mask-image: url('/_resources/app/client/icons/actions/action_recenter.svg');"
                ></div>
              </button>
            </template>
            <template v-else>
              <AppButton variant="danger" :disabled="saving" title="Lageplan löschen" @click="deleteMap">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="display:block">
                  <path d="M11 1.75V3h2.25a.75.75 0 010 1.5H2.75a.75.75 0 010-1.5H5V1.75C5 .784 5.784 0 6.75 0h2.5C10.216 0 11 .784 11 1.75zM6.5 1.75v1.25h3V1.75a.25.25 0 00-.25-.25h-2.5a.25.25 0 00-.25.25zM4.997 6.5a.75.75 0 00-1.498.076l.492 7.5a.75.75 0 001.498-.076L4.997 6.5zm6.006.076a.75.75 0 10-1.498-.076l-.492 7.5a.75.75 0 001.498.076l.492-7.5z"/>
                </svg>
              </AppButton>
              <AppButton variant="secondary" :disabled="saving" @click="cancelEdit">Abbrechen</AppButton>
              <AppButton variant="primary" :disabled="saving" @click="saveAll">
                {{ saving ? 'Wird gespeichert…' : 'Speichern' }}
              </AppButton>
            </template>
          </div>
        </div>

        <div class="map-renderer-wrapper" :class="{ 'sidebar-hidden': sidebarHidden }">
          <div ref="rendererEl" class="map-renderer">
            <canvas ref="canvasEl" id="mapCanvas"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPostForm } from '@utils/api'
import MapRenderer from '../../js/maprenderer.js'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const route = useRoute()
const router = useRouter()
usePageHeaderStore().setHeader('Lageplan', '')

const map = ref(null)
const loading = ref(true)
const error = ref(null)
const sidebarHidden = ref(false)
const canvasEl = ref(null)
const rendererEl = ref(null)
let renderer = null

// Edit mode state
const isEditMode = ref(false)
const editLayers = ref([])
const pendingImages = ref({})
const pendingBgImage = ref(null)
const bgPreviewUrl = ref('')
const saving = ref(false)

async function loadMap() {
  try {
    const data = await apiGet(`/maps/view/${route.params.id}`, false)
    map.value = data.map
    usePageHeaderStore().setHeader(data.map.title, '')
    loading.value = false
    await nextTick()
    initRenderer()
  } catch (e) {
    error.value = e.message
    loading.value = false
  }
}

function initRenderer() {
  if (!canvasEl.value || !map.value) return

  renderer = new MapRenderer('mapCanvas', {
    backgroundImage: map.value.backgroundImage,
    coordinatesUpperLeft: map.value.coordinatesUpperLeft,
    coordinatesUpperRight: map.value.coordinatesUpperRight,
    coordinatesLowerLeft: map.value.coordinatesLowerLeft,
    coordinatesLowerRight: map.value.coordinatesLowerRight,
    layers: map.value.layers,
    editMode: false,
  })
  window.mapRenderer = renderer
}

function toggleLayer(layerId) {
  renderer?.toggleLayer(layerId)
}

function resetView() {
  renderer?.resetView()
}

function toggleSidebar() {
  sidebarHidden.value = !sidebarHidden.value
  setTimeout(() => {
    renderer?.resizeCanvas()
    renderer?.render()
  }, 350)
}

// ── Edit mode ──────────────────────────────────────────────────────────────

function toggleEditMode() {
  if (isEditMode.value) {
    cancelEdit()
  } else {
    enterEditMode()
  }
}

function enterEditMode() {
  editLayers.value = map.value.layers.map(l => ({
    id: l.id,
    title: l.title,
    description: l.description || '',
    color: l.layerColor || '#999999',
  }))
  pendingImages.value = {}
  pendingBgImage.value = null
  bgPreviewUrl.value = map.value.backgroundImage || ''
  isEditMode.value = true
  if (renderer) {
    renderer.isEditMode = true
    renderer.render()
  }
}

async function deleteMap() {
  if (!confirm(`Lageplan "${map.value.title}" wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.`)) return
  try {
    await apiPost(`/maps/deletemap/${map.value.id}`, {})
    router.push('/map')
  } catch (e) {
    alert('Fehler beim Löschen: ' + e.message)
  }
}

async function cancelEdit() {
  isEditMode.value = false
  editLayers.value = []
  pendingImages.value = {}
  pendingBgImage.value = null
  bgPreviewUrl.value = ''
  await loadMap()
}

function onBgImageSelected(event) {
  const file = event.target.files[0]
  if (!file) return
  pendingBgImage.value = file
  bgPreviewUrl.value = URL.createObjectURL(file)

  const img = new Image()
  img.onload = () => {
    if (renderer) {
      renderer.mapImage = img
      renderer.resizeCanvas()
      renderer.render()
    }
  }
  img.src = bgPreviewUrl.value
}

async function deleteLayer(layerIndex) {
  const layerTitle = editLayers.value[layerIndex]?.title || 'diese Ebene'
  if (!confirm(`Ebene "${layerTitle}" wirklich löschen?`)) return
  const layerId = editLayers.value[layerIndex].id
  try {
    await apiPost(`/maps/deletelayer/${layerId}`, {})
    editLayers.value.splice(layerIndex, 1)
    map.value.layers.splice(layerIndex, 1) // renderer.layers is the same reference
    renderer?.render()
  } catch (e) {
    alert('Fehler beim Löschen der Ebene: ' + e.message)
  }
}

function onLayerColorChange(layerIndex) {
  if (!renderer?.layers?.[layerIndex]) return
  const color = editLayers.value[layerIndex].color
  renderer.layers[layerIndex].layerColor = color
  renderer.layers[layerIndex].pois?.forEach(p => { p.markerColor = color })
  renderer.render()
}

function onLayerImageSelected(event, layerId, layerIndex) {
  const file = event.target.files[0]
  if (!file) return
  pendingImages.value = { ...pendingImages.value, [layerId]: file }

  const img = new Image()
  img.onload = () => {
    if (renderer) {
      renderer.layerImages.set(layerId, img)
      renderer.render()
    }
  }
  img.src = URL.createObjectURL(file)
}

function addPOIToLayer(layerIndex) {
  const title = prompt('Titel des neuen Markers:')
  if (!title) return
  renderer?.addNewPOIToLayer(title, editLayers.value[layerIndex]?.color, layerIndex)
}

async function addLayer() {
  const title = prompt('Titel der neuen Ebene:')
  if (!title) return
  try {
    const result = await apiPost(`/maps/createlayer/${map.value.id}`, { title })
    const newLayer = result.data.layer
    // Add to map and renderer live
    map.value.layers.push(newLayer)
    renderer?.layers.push(newLayer)
    editLayers.value.push({
      id: newLayer.id,
      title: newLayer.title,
      description: '',
      color: newLayer.layerColor || '#999999',
    })
    renderer?.render()
  } catch (e) {
    alert('Fehler beim Erstellen der Ebene: ' + e.message)
  }
}

async function saveAll() {
  saving.value = true
  try {
    if (pendingBgImage.value) {
      const formData = new FormData()
      formData.append('image', pendingBgImage.value)
      await apiPostForm(`/maps/uploadbackgroundimage/${map.value.id}`, formData)
    }

    for (let i = 0; i < editLayers.value.length; i++) {
      const editLayer = editLayers.value[i]
      const rendererLayer = renderer?.layers?.[i]

      if (pendingImages.value[editLayer.id]) {
        const formData = new FormData()
        formData.append('image', pendingImages.value[editLayer.id])
        await apiPostForm(`/maps/uploadlayerimage/${editLayer.id}`, formData)
      }

      const pois = (rendererLayer?.pois || []).map(p => ({
        id: p.id,
        title: p.title,
        description: p.description || '',
        active: p.active !== false,
        position: p.position,
        markerColor: p.markerColor,
        markerText: p.markerText,
        isNew: p.isNew || false,
      }))

      await apiPost(`/maps/savelayer/${editLayer.id}`, {
        title: editLayer.title,
        description: editLayer.description,
        layerColor: editLayer.color,
        pois,
      })
    }

    isEditMode.value = false
    editLayers.value = []
    pendingImages.value = {}
    await loadMap()
  } catch (e) {
    alert('Fehler beim Speichern: ' + e.message)
  } finally {
    saving.value = false
  }
}

onMounted(loadMap)

onUnmounted(() => {
  window.mapRenderer = null
})
</script>
