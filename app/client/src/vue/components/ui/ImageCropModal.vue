<template>
  <AppModal ref="modal" class="image-crop-modal" :title="title" @close="cancel">
    <p class="image-crop-modal_hint">Ziehen zum Verschieben · Scrollen oder Schieberegler zum Zoomen</p>

    <!-- Der Ausschnitt entspricht exakt dem finalen Bild (Größe + Form), damit
         das Endergebnis schon beim Zuschneiden sichtbar ist. -->
    <canvas
      ref="canvasEl"
      class="image-crop-modal_canvas"
      :class="`image-crop-modal_canvas--${shape}`"
      :width="canvasWidth"
      :height="canvasHeight"
      :style="{ aspectRatio }"
      @wheel.prevent="onWheel"
      @mousedown.prevent="onMouseDown"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @mouseleave="onMouseUp"
      @touchstart.prevent="onTouchStart"
      @touchmove.prevent="onTouchMove"
      @touchend="onTouchEnd"
    ></canvas>

    <div class="zoom-controls">
      <AppIconButton variant="neutral" aria-label="Verkleinern" @click="adjustZoom(-0.15)">−</AppIconButton>
      <input
        type="range"
        class="zoom-slider"
        min="1"
        :max="maxZoom"
        step="0.01"
        :value="zoom"
        @input="onZoomSlider"
      >
      <AppIconButton variant="neutral" aria-label="Vergrößern" @click="adjustZoom(0.15)">+</AppIconButton>
    </div>

    <p v-if="error" class="status-text status-text--error">{{ error }}</p>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="cancel">Abbrechen</AppButton>
      <AppButton variant="primary" :disabled="saving" @click="save">
        {{ saving ? 'Wird gespeichert …' : (uploadUrl ? 'Speichern' : 'Übernehmen') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { apiPostForm } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'

// Generischer Zuschnitt-Editor: wird für das eigene Profilbild (Kreis,
// RenderProfileImage), Organisations-Logos (abgerundetes Quadrat,
// Organization::RenderLogo) und Event-Bilder (16:9-Rechteck) verwendet — beide skalieren serverseitig auf
// dasselbe Zielformat, daher ist auch hier ein fester Ziel-Output sinnvoll.
// Ohne `uploadUrl` wird nicht hochgeladen, sondern das zugeschnittene JPEG als
// Blob per `cropped` zurückgegeben (z.B. für ein Event, das noch gar nicht existiert).
const props = defineProps({
  title: { type: String, default: 'Profilbild zuschneiden' },
  uploadUrl: { type: String, default: null },
  // Feldname, unter dem die neue Bild-URL in der Erfolgsantwort steht (z.B. 'Avatar' oder 'LogoURL').
  responseField: { type: String, default: 'Avatar' },
  shape: { type: String, default: 'circle' }, // 'circle' | 'square' | 'rect'
  // Breite des fertigen Bildes in px; die Höhe ergibt sich aus aspectRatio
  outputSize: { type: Number, default: 180 },
  // Seitenverhältnis Breite/Höhe, z.B. 16 / 9 für Event-Bilder (shape "rect")
  aspectRatio: { type: Number, default: 1 },
})

// Zeichenfläche: gleiche Fläche wie das bisherige 280×280, im gewünschten Seitenverhältnis
const canvasWidth  = Math.round(280 * Math.sqrt(props.aspectRatio))
const canvasHeight = Math.round(canvasWidth / props.aspectRatio)

const emit = defineEmits(['saved', 'cropped'])

const modal      = ref(null)
const canvasEl  = ref(null)
const editorImg = ref(null)
const objectUrl = ref(null)
const zoom      = ref(1)
const maxZoom   = ref(8)
const panX      = ref(0)
const panY      = ref(0)
const error     = ref(null)
const saving    = ref(false)

// drag
const isDragging    = ref(false)
const dragStartX    = ref(0)
const dragStartY    = ref(0)
const dragStartPanX = ref(0)
const dragStartPanY = ref(0)

// pinch
const lastPinchDist = ref(null)

function open(file) {
  error.value = null
  cleanupImage()
  objectUrl.value = URL.createObjectURL(file)

  const img = new Image()
  img.onload = () => {
    editorImg.value = img
    zoom.value      = 1
    panX.value      = img.width  / 2
    panY.value      = img.height / 2
    modal.value?.open()
    nextTick(redraw)
  }
  img.src = objectUrl.value
}

function cancel() {
  modal.value?.close()
  cleanupImage()
}

function cleanupImage() {
  if (objectUrl.value) {
    URL.revokeObjectURL(objectUrl.value)
    objectUrl.value = null
  }
  editorImg.value = null
  zoom.value      = 1
}

/**
 * Sichtbarer Ausschnitt im Originalbild: bei Zoom 1 das größte Rechteck im
 * gewünschten Seitenverhältnis, das ins Bild passt; Mittelpunkt auf das Bild begrenzt.
 */
function viewRect(img) {
  const width  = Math.min(img.width, img.height * props.aspectRatio) / zoom.value
  const height = width / props.aspectRatio
  const cx = Math.max(width / 2,  Math.min(img.width  - width / 2,  panX.value))
  const cy = Math.max(height / 2, Math.min(img.height - height / 2, panY.value))
  return { x: cx - width / 2, y: cy - height / 2, width, height }
}

function redraw() {
  const canvas = canvasEl.value
  const img    = editorImg.value
  if (!canvas || !img) return

  const ctx  = canvas.getContext('2d')
  const view = viewRect(img)
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  ctx.drawImage(img, view.x, view.y, view.width, view.height, 0, 0, canvas.width, canvas.height)
}

// Bildschirm-Pixel beim Ziehen → Pixel im Originalbild
function dragScale() {
  return viewRect(editorImg.value).width / canvasEl.value.clientWidth
}

function adjustZoom(delta) {
  zoom.value = Math.max(1, Math.min(maxZoom.value, zoom.value + delta * zoom.value))
  redraw()
}

function onZoomSlider(e) {
  zoom.value = parseFloat(e.target.value)
  redraw()
}

function onWheel(e) {
  adjustZoom(e.deltaY < 0 ? 0.1 : -0.1)
}

function onMouseDown(e) {
  isDragging.value    = true
  dragStartX.value    = e.clientX
  dragStartY.value    = e.clientY
  dragStartPanX.value = panX.value
  dragStartPanY.value = panY.value
}

function onMouseMove(e) {
  if (!isDragging.value || !editorImg.value) return
  const scale = dragScale()
  panX.value = dragStartPanX.value - (e.clientX - dragStartX.value) * scale
  panY.value = dragStartPanY.value - (e.clientY - dragStartY.value) * scale
  redraw()
}

function onMouseUp() {
  isDragging.value = false
}

function pinchDist(e) {
  const dx = e.touches[0].clientX - e.touches[1].clientX
  const dy = e.touches[0].clientY - e.touches[1].clientY
  return Math.sqrt(dx * dx + dy * dy)
}

function onTouchStart(e) {
  if (e.touches.length === 2) {
    lastPinchDist.value = pinchDist(e)
    isDragging.value    = false
  } else {
    isDragging.value    = true
    dragStartX.value    = e.touches[0].clientX
    dragStartY.value    = e.touches[0].clientY
    dragStartPanX.value = panX.value
    dragStartPanY.value = panY.value
  }
}

function onTouchMove(e) {
  if (e.touches.length === 2 && lastPinchDist.value !== null) {
    const dist  = pinchDist(e)
    const ratio = dist / lastPinchDist.value
    zoom.value  = Math.max(1, Math.min(maxZoom.value, zoom.value * ratio))
    lastPinchDist.value = dist
    redraw()
  } else if (e.touches.length === 1 && isDragging.value && editorImg.value) {
    const scale = dragScale()
    panX.value = dragStartPanX.value - (e.touches[0].clientX - dragStartX.value) * scale
    panY.value = dragStartPanY.value - (e.touches[0].clientY - dragStartY.value) * scale
    redraw()
  }
}

function onTouchEnd(e) {
  if (e.touches.length < 2) lastPinchDist.value = null
  if (e.touches.length === 0) isDragging.value   = false
}

async function save() {
  if (!editorImg.value) return
  saving.value = true
  error.value  = null

  try {
    const img  = editorImg.value
    const view = viewRect(img)

    const out = document.createElement('canvas')
    out.width  = props.outputSize
    out.height = Math.round(props.outputSize / props.aspectRatio)
    out.getContext('2d').drawImage(img, view.x, view.y, view.width, view.height, 0, 0, out.width, out.height)

    const blob = await new Promise(resolve => out.toBlob(resolve, 'image/jpeg', 0.92))

    if (!props.uploadUrl) {
      emit('cropped', blob)
      modal.value?.close()
      cleanupImage()
      return
    }

    const fd   = new FormData()
    fd.append('image', blob, 'image.jpg')

    const result = await apiPostForm(props.uploadUrl, fd)
    const savedUrl = result.data?.[props.responseField]

    if (result.success && savedUrl) {
      emit('saved', savedUrl)
      modal.value?.close()
      cleanupImage()
    } else {
      error.value = result.error ?? 'Bild konnte nicht gespeichert werden.'
    }
  } catch (err) {
    console.error('Bild-Upload fehlgeschlagen:', err)
    error.value = 'Hochladen fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
