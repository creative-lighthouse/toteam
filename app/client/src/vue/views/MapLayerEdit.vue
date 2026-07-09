<template>
  <div class="section section--MapView">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Ebene...</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler: {{ error }}</p>
        <AppButton :to="`/map/${mapId}`" variant="primary">← Zurück</AppButton>
      </div>

      <div v-else-if="layer && map" class="map-container">
        <div class="map-controls" :class="{ 'is-hidden': sidebarHidden }">
          <button class="map-controls_toggle" @click="toggleSidebar" aria-label="Sidebar umschalten">
            <span class="map-controls_toggle-icon">›</span>
          </button>

          <div class="map-controls_header" :style="headerStyle">
            <h3>{{ layerForm.title }}</h3>
            <button class="header_save button" @click="saveLayer" :disabled="saving" title="Speichern">
              <div class="headerSave_button icon--small" style="mask-image: url('/_resources/app/client/icons/actions/action_save.svg');"></div>
            </button>
          </div>

          <div class="map-controls_wrap">
            <div class="map-controls_edit-fields">
              <div class="edit-field">
                <label for="layerTitle">Titel</label>
                <input id="layerTitle" v-model="layerForm.title" type="text" class="form-control" />
              </div>
              <div class="edit-field">
                <label for="layerDescription">Beschreibung</label>
                <textarea id="layerDescription" v-model="layerForm.description" class="form-control" rows="3"></textarea>
              </div>
              <div class="edit-field">
                <label for="layerColor">Farbe</label>
                <div class="color-picker-wrapper">
                  <input id="layerColor" type="color" v-model="layerForm.color" @input="onColorChange" />
                  <input type="text" v-model="layerForm.color" class="form-control" @change="onColorChange" />
                </div>
              </div>
            </div>

            <div class="map-controls_layer-upload">
              <h4>Ebenen-Bild</h4>
              <div class="layer-image-preview">
                <img v-if="layerPreviewUrl" :src="layerPreviewUrl" alt="Ebenen-Bild" />
                <div v-else class="layer-image-placeholder">Kein Bild hochgeladen</div>
              </div>
              <div class="layer-upload-controls">
                <input
                  type="file"
                  id="layerImageUpload"
                  class="layer-image-input"
                  accept="image/*"
                  @change="onImageSelected"
                />
                <label for="layerImageUpload" class="button">Bild auswählen</label>
              </div>
            </div>

            <div class="map-controls_pois">
              <h4>Marker</h4>
              <p class="map-controls_help">Marker auf der Karte anklicken zum Bearbeiten, per Drag&nbsp;&amp;&nbsp;Drop verschieben.</p>
              <AppButton variant="primary" @click="addPOI">+ Marker hinzufügen</AppButton>
            </div>
          </div>

          <div class="map-controls_actions">
            <AppButton :to="`/map/${mapId}`" variant="primary">← Zurück</AppButton>
            <button class="button action_recenter" @click="resetView">
              <div
                class="resetMapView_button icon--small"
                style="mask-image: url('/_resources/app/client/icons/actions/action_recenter.svg');"
              ></div>
            </button>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPostForm } from '@utils/api'
import MapRenderer from '../../js/maprenderer.js'
import AppButton from '@components/AppButton.vue'

const route = useRoute()
const router = useRouter()
usePageHeaderStore().setHeader('Ebene bearbeiten', '')

const mapId = computed(() => route.params.mapId)
const layerId = computed(() => parseInt(route.params.layerId))

const map = ref(null)
const layer = ref(null)
const loading = ref(true)
const error = ref(null)
const saving = ref(false)
const sidebarHidden = ref(false)
const canvasEl = ref(null)
const rendererEl = ref(null)
let renderer = null

const layerForm = ref({ title: '', description: '', color: '#999999' })
const layerPreviewUrl = ref('')
let pendingImageFile = null

const headerStyle = computed(() => {
  const c = layerForm.value.color || '#999999'
  const r = parseInt(c.slice(1, 3), 16)
  const g = parseInt(c.slice(3, 5), 16)
  const b = parseInt(c.slice(5, 7), 16)
  const light = (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.5
  return { backgroundColor: c, color: light ? '#000' : '#fff' }
})

async function loadLayer() {
  try {
    const data = await apiGet(`/maps/view/${mapId.value}`, false)
    map.value = data.map
    layer.value = data.map.layers.find(l => l.id === layerId.value)
    if (!layer.value) {
      error.value = 'Ebene nicht gefunden'
      return
    }
    if (!data.map.canEdit) {
      error.value = 'Keine Berechtigung'
      return
    }
    layerForm.value = {
      title: layer.value.title,
      description: layer.value.description || '',
      color: layer.value.layerColor || '#999999',
    }
    layerPreviewUrl.value = layer.value.imageUrl || ''
    usePageHeaderStore().setHeader(layer.value.title, '')
    loading.value = false
    await nextTick()
    initRenderer()
  } catch (e) {
    error.value = e.message
    loading.value = false
  }
}

function initRenderer() {
  if (!canvasEl.value || !map.value || !layer.value) return

  renderer = new MapRenderer('mapCanvas', {
    backgroundImage: map.value.backgroundImage,
    coordinatesUpperLeft: map.value.coordinatesUpperLeft,
    coordinatesUpperRight: map.value.coordinatesUpperRight,
    coordinatesLowerLeft: map.value.coordinatesLowerLeft,
    coordinatesLowerRight: map.value.coordinatesLowerRight,
    layers: [layer.value],
    editMode: true,
  })
  window.mapRenderer = renderer
}

function onColorChange() {
  if (!renderer) return
  const color = layerForm.value.color
  if (renderer.layers[0]) {
    renderer.layers[0].layerColor = color
    renderer.layers[0].pois?.forEach(p => { p.markerColor = color })
  }
  renderer.render()
}

function onImageSelected(e) {
  const file = e.target.files[0]
  if (!file) return
  pendingImageFile = file
  layerPreviewUrl.value = URL.createObjectURL(file)

  const img = new Image()
  img.onload = () => {
    if (renderer?.layers[0]) {
      renderer.layerImages.set(renderer.layers[0].id, img)
      renderer.render()
    }
  }
  img.src = layerPreviewUrl.value
}

function addPOI() {
  const title = prompt('Titel des neuen Markers:')
  if (!title) return
  renderer?.addNewPOI(title, layerForm.value.color)
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

async function saveLayer() {
  saving.value = true
  try {
    if (pendingImageFile) {
      const formData = new FormData()
      formData.append('image', pendingImageFile)
      await apiPostForm(`/maps/uploadlayerimage/${layer.value.id}`, formData)
      pendingImageFile = null
    }

    const pois = renderer?.layers?.[0]?.pois || layer.value.pois || []
    await apiPost(`/maps/savelayer/${layer.value.id}`, {
      title: layerForm.value.title,
      description: layerForm.value.description,
      layerColor: layerForm.value.color,
      pois: pois.map(p => ({
        id: p.id,
        title: p.title,
        description: p.description || '',
        active: p.active !== false,
        position: p.position,
        markerColor: p.markerColor,
        markerText: p.markerText,
        isNew: p.isNew || false,
      })),
    })

    router.push(`/map/${mapId.value}`)
  } catch (e) {
    alert('Fehler beim Speichern: ' + e.message)
    saving.value = false
  }
}

onMounted(loadLayer)

onUnmounted(() => {
  window.mapRenderer = null
})
</script>
