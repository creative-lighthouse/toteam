<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="image-crop-modal" @cancel.prevent="cancel">
      <div class="image-crop-modal_content" @click.stop>

        <div class="image-crop-modal_header">
          <h2 class="hl2 image-crop-modal_title">Profilbild zuschneiden</h2>
          <AppIconButton variant="ghost" aria-label="Abbrechen" @click="cancel">✕</AppIconButton>
        </div>

        <div class="image-crop-modal_body">
          <p class="image-crop-modal_hint">Ziehen zum Verschieben · Scrollen oder Schieberegler zum Zoomen</p>

          <!-- Der Kreis-Ausschnitt entspricht exakt dem finalen 180×180-Avatar, damit
               das Endergebnis schon beim Zuschneiden sichtbar ist. -->
          <canvas
            ref="canvasEl"
            class="image-crop-modal_canvas"
            width="280"
            height="280"
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
        </div>

        <div class="image-crop-modal_actions">
          <AppButton variant="secondary" :disabled="saving" @click="cancel">Abbrechen</AppButton>
          <AppButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? 'Wird gespeichert …' : 'Speichern' }}
          </AppButton>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { apiPostForm } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

// Muss zum `Fill(180, 180)` in MemberExtension::RenderProfileImage() passen.
const OUTPUT_SIZE = 180

const emit = defineEmits(['saved'])

const dialogEl  = ref(null)
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
    dialogEl.value?.showModal()
    nextTick(redraw)
  }
  img.src = objectUrl.value
}

function cancel() {
  dialogEl.value?.close()
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

function redraw() {
  const canvas = canvasEl.value
  const img    = editorImg.value
  if (!canvas || !img) return

  const ctx       = canvas.getContext('2d')
  const size      = canvas.width
  const shortSide = Math.min(img.width, img.height)
  const viewSize  = shortSide / zoom.value
  const halfView  = viewSize / 2

  const cx = Math.max(halfView, Math.min(img.width  - halfView, panX.value))
  const cy = Math.max(halfView, Math.min(img.height - halfView, panY.value))

  ctx.clearRect(0, 0, size, size)
  ctx.drawImage(img, cx - halfView, cy - halfView, viewSize, viewSize, 0, 0, size, size)
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
  const shortSide = Math.min(editorImg.value.width, editorImg.value.height)
  const viewSize  = shortSide / zoom.value
  const scale     = viewSize / canvasEl.value.width
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
    const shortSide = Math.min(editorImg.value.width, editorImg.value.height)
    const viewSize  = shortSide / zoom.value
    const scale     = viewSize / canvasEl.value.width
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
    const img       = editorImg.value
    const shortSide = Math.min(img.width, img.height)
    const viewSize  = shortSide / zoom.value
    const halfView  = viewSize / 2
    const cx = Math.max(halfView, Math.min(img.width  - halfView, panX.value))
    const cy = Math.max(halfView, Math.min(img.height - halfView, panY.value))

    const out = document.createElement('canvas')
    out.width = out.height = OUTPUT_SIZE
    out.getContext('2d').drawImage(img, cx - halfView, cy - halfView, viewSize, viewSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE)

    const blob = await new Promise(resolve => out.toBlob(resolve, 'image/jpeg', 0.92))
    const fd   = new FormData()
    fd.append('image', blob, 'profile.jpg')

    const result = await apiPostForm('/profile/uploadImage', fd)

    if (result.success && result.data?.Avatar) {
      emit('saved', result.data.Avatar)
      dialogEl.value?.close()
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
