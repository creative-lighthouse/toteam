<!--
  Bühne des Foto-Betrachters: zeigt das aktuelle Foto und hält die Nachbarn links/rechts
  bereit (dadurch sind sie schon geladen, wenn man hinwischt).

  Gesten (Pointer Events, also Finger, Stift und Maus gleich):
  - waagerecht wischen → vorheriges/nächstes Foto (am Ende mit Widerstand)
  - senkrecht wischen → schließen
  - zwei Finger / Mausrad → zoomen, gezoomt mit einem Finger verschieben
  - doppelt tippen → 2,5× an der Stelle zoomen bzw. zurück
  - einmal tippen → `tap` (der Betrachter blendet dann die Leisten ein/aus)

  `go(-1)`/`go(1)` blättert animiert (für Pfeiltasten und Knöpfe), `reset()` setzt alles zurück.
-->
<template>
  <div
    ref="stageEl"
    class="photo-viewer-stage"
    :class="{
      'photo-viewer-stage--animating': animating,
      'photo-viewer-stage--zoom-animating': zoomAnimating,
      'photo-viewer-stage--zoomed': scale > 1,
    }"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointercancel="onPointerUp"
    @wheel.prevent="onWheel"
  >
    <div
      v-for="slide in slides"
      :key="slide.photo.ID"
      class="photo-viewer-stage_slide"
      :style="slideStyle(slide.offset)"
      :aria-hidden="slide.offset !== 0"
    >
      <img
        v-if="slide.photo.URL"
        :src="slide.photo.URL"
        :alt="slide.offset === 0 ? (slide.photo.Title || '') : ''"
        class="photo-viewer-stage_image"
        :style="slide.offset === 0 ? imageStyle(slide.photo) : thumbStyle(slide.photo)"
        draggable="false"
        decoding="async"
      >
      <p v-else class="photo-viewer-stage_missing">Foto nicht verfügbar</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  // Kurzform aus der API (ID, URL, ThumbURL, Width, Height, Title) — prev/next dürfen fehlen
  prev: { type: Object, default: null },
  current: { type: Object, default: null },
  next: { type: Object, default: null },
})

const emit = defineEmits(['prev', 'next', 'close', 'tap'])

const MAX_SCALE = 4
const DOUBLE_TAP_ZOOM = 2.5
const SWIPE_DISTANCE = 0.18 // Anteil der Breite
const SWIPE_VELOCITY = 0.45 // px/ms
const CLOSE_DISTANCE = 110 // px
const GAP = 16 // Abstand zwischen den Fotos beim Wischen

const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
const DURATION = reducedMotion ? 0 : 220

const stageEl = ref(null)
const dx = ref(0)
const dy = ref(0)
const scale = ref(1)
const panX = ref(0)
const panY = ref(0)
const animating = ref(false)
const zoomAnimating = ref(false)

const slides = computed(() => [
  props.prev && { photo: props.prev, offset: -1 },
  props.current && { photo: props.current, offset: 0 },
  props.next && { photo: props.next, offset: 1 },
].filter(Boolean))

function slideStyle(offset) {
  const y = offset === 0 ? dy.value : 0
  return {
    transform: `translate3d(calc(${offset * 100}% + ${offset * GAP + dx.value}px), ${y}px, 0)`,
    opacity: offset === 0 && dy.value ? Math.max(0.3, 1 - Math.abs(dy.value) / 400) : 1,
  }
}

// Vorschaubild als Hintergrund, bis das große Bild geladen ist
function thumbStyle(photo) {
  return photo.ThumbURL ? { backgroundImage: `url("${photo.ThumbURL}")` } : null
}

function imageStyle(photo) {
  return {
    ...thumbStyle(photo),
    transform: `translate3d(${panX.value}px, ${panY.value}px, 0) scale(${scale.value})`,
  }
}

// Neues Foto: Zoom zurücksetzen
watch(() => props.current?.ID, () => {
  scale.value = 1
  panX.value = 0
  panY.value = 0
})

// ── Hilfen ─────────────────────────────────────────────────────────────

function stageRect() {
  return stageEl.value?.getBoundingClientRect() ?? { left: 0, top: 0, width: 1, height: 1 }
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value))
}

// Größe, in der das Foto (object-fit: contain) ungezoomt angezeigt wird
function displayedSize() {
  const { width, height } = stageRect()
  const p = props.current
  if (!p?.Width || !p?.Height) return { width, height }
  const ratio = p.Width / p.Height
  return width / height > ratio
    ? { width: height * ratio, height }
    : { width, height: width / ratio }
}

// Gezoomt nur so weit verschieben, dass kein leerer Rand ins Bild kommt
function clampPan() {
  const { width, height } = stageRect()
  const shown = displayedSize()
  const maxX = Math.max(0, (shown.width * scale.value - width) / 2)
  const maxY = Math.max(0, (shown.height * scale.value - height) / 2)
  panX.value = clamp(panX.value, -maxX, maxX)
  panY.value = clamp(panY.value, -maxY, maxY)
}

// Auf `nextScale` zoomen, sodass der Bildpunkt unter (x, y) dort stehen bleibt
function zoomAround(nextScale, x, y, from = { scale: scale.value, panX: panX.value, panY: panY.value }) {
  const rect = stageRect()
  const cx = rect.left + rect.width / 2
  const cy = rect.top + rect.height / 2
  const s = clamp(nextScale, 1, MAX_SCALE)
  const px = (x - cx - from.panX) / from.scale
  const py = (y - cy - from.panY) / from.scale
  scale.value = s
  panX.value = x - cx - px * s
  panY.value = y - cy - py * s
  if (s <= 1.01) resetZoom()
  else clampPan()
}

function resetZoom() {
  scale.value = 1
  panX.value = 0
  panY.value = 0
}

let zoomTimer = null
function animateZoom(fn) {
  zoomAnimating.value = !reducedMotion
  fn()
  clearTimeout(zoomTimer)
  zoomTimer = setTimeout(() => { zoomAnimating.value = false }, DURATION)
}

// ── Blättern ───────────────────────────────────────────────────────────

let commitTimer = null
/** -1 = nächstes Foto (Bild wandert nach links), 1 = vorheriges */
function go(direction) {
  if (animating.value && commitTimer) return
  const target = direction < 0 ? props.next : props.prev
  if (!target) {
    settle()
    return
  }
  animating.value = DURATION > 0
  dx.value = direction * (stageRect().width + GAP)
  dy.value = 0
  commitTimer = setTimeout(() => {
    commitTimer = null
    // Gleichzeitig zurücksetzen und wechseln: Der Nachbar steht dann ohne Sprung in der Mitte
    animating.value = false
    dx.value = 0
    emit(direction < 0 ? 'next' : 'prev')
  }, DURATION)
}

// Zurück in die Ruhelage
function settle() {
  animating.value = DURATION > 0
  dx.value = 0
  dy.value = 0
  clearTimeout(commitTimer)
  commitTimer = setTimeout(() => {
    commitTimer = null
    animating.value = false
  }, DURATION)
}

// ── Gesten ─────────────────────────────────────────────────────────────

const pointers = new Map()
let gesture = null
let lastTap = null
let tapTimer = null

function distance(a, b) {
  return Math.hypot(a.x - b.x, a.y - b.y)
}

function midpoint(a, b) {
  return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }
}

function startSingle(point, moved = false) {
  gesture = {
    type: scale.value > 1 ? 'pan' : 'swipe',
    startX: point.x,
    startY: point.y,
    startTime: performance.now(),
    startPanX: panX.value,
    startPanY: panY.value,
    axis: null,
    moved,
  }
}

function onPointerDown(event) {
  if (event.pointerType === 'mouse' && event.button !== 0) return
  if (commitTimer && animating.value) return
  stageEl.value?.setPointerCapture?.(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (pointers.size === 1) {
    startSingle({ x: event.clientX, y: event.clientY })
  } else if (pointers.size === 2) {
    const [a, b] = [...pointers.values()]
    dx.value = 0
    dy.value = 0
    gesture = {
      type: 'pinch',
      startDist: distance(a, b) || 1,
      from: { scale: scale.value, panX: panX.value, panY: panY.value },
      startMid: midpoint(a, b),
    }
  }
}

function onPointerMove(event) {
  if (!pointers.has(event.pointerId) || !gesture) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (gesture.type === 'pinch') {
    if (pointers.size < 2) return
    const [a, b] = [...pointers.values()]
    const mid = midpoint(a, b)
    const s = gesture.from.scale * distance(a, b) / gesture.startDist
    // Zoom um den Startpunkt, dann um die Bewegung der Finger verschieben
    zoomAround(s, gesture.startMid.x, gesture.startMid.y, gesture.from)
    if (scale.value > 1) {
      panX.value += mid.x - gesture.startMid.x
      panY.value += mid.y - gesture.startMid.y
      clampPan()
    }
    return
  }

  const moveX = event.clientX - gesture.startX
  const moveY = event.clientY - gesture.startY
  if (!gesture.moved && Math.hypot(moveX, moveY) > 8) gesture.moved = true
  if (!gesture.moved) return

  if (gesture.type === 'pan') {
    panX.value = gesture.startPanX + moveX
    panY.value = gesture.startPanY + moveY
    clampPan()
    return
  }

  if (!gesture.axis) gesture.axis = Math.abs(moveX) >= Math.abs(moveY) ? 'x' : 'y'
  if (gesture.axis === 'x') {
    // Am Anfang/Ende der Liste gibt es nach dort nichts — mit Widerstand ziehen
    const blocked = (moveX > 0 && !props.prev) || (moveX < 0 && !props.next)
    dx.value = blocked ? moveX / 3 : moveX
  } else {
    dy.value = moveY
  }
}

function onPointerUp(event) {
  if (!pointers.has(event.pointerId)) return
  pointers.delete(event.pointerId)
  if (!gesture) return

  if (gesture.type === 'pinch') {
    if (pointers.size === 1) {
      // Ein Finger bleibt liegen: damit weiter verschieben
      startSingle([...pointers.values()][0], true)
    } else if (pointers.size === 0) {
      gesture = null
    }
    return
  }
  if (pointers.size > 0) return

  const g = gesture
  gesture = null

  if (!g.moved) {
    onTap(event)
    return
  }
  if (g.type === 'pan') return

  if (g.axis === 'x') {
    const width = stageRect().width
    const velocity = dx.value / Math.max(1, performance.now() - g.startTime)
    if ((dx.value < -width * SWIPE_DISTANCE || velocity < -SWIPE_VELOCITY) && props.next) return go(-1)
    if ((dx.value > width * SWIPE_DISTANCE || velocity > SWIPE_VELOCITY) && props.prev) return go(1)
  } else if (g.axis === 'y' && Math.abs(dy.value) > CLOSE_DISTANCE) {
    emit('close')
    return
  }
  settle()
}

// Einfach tippen → Leisten umschalten (verzögert, um Doppeltippen abzuwarten)
function onTap(event) {
  const now = performance.now()
  const point = { x: event.clientX, y: event.clientY }
  if (lastTap && now - lastTap.time < 300 && distance(point, lastTap) < 30) {
    clearTimeout(tapTimer)
    lastTap = null
    animateZoom(() => {
      if (scale.value > 1) resetZoom()
      else zoomAround(DOUBLE_TAP_ZOOM, point.x, point.y)
    })
    return
  }
  lastTap = { ...point, time: now }
  clearTimeout(tapTimer)
  tapTimer = setTimeout(() => {
    lastTap = null
    emit('tap')
  }, 300)
}

function onWheel(event) {
  const factor = Math.exp(-event.deltaY * (event.ctrlKey ? 0.01 : 0.002))
  zoomAround(scale.value * factor, event.clientX, event.clientY)
}

/** Für die Tastatur: Zoom an-/ausschalten (um die Mitte) */
function toggleZoom() {
  const rect = stageRect()
  animateZoom(() => {
    if (scale.value > 1) resetZoom()
    else zoomAround(DOUBLE_TAP_ZOOM, rect.left + rect.width / 2, rect.top + rect.height / 2)
  })
}

/** Alles zurück in die Ruhelage (z.B. beim Schließen) */
function reset() {
  clearTimeout(commitTimer)
  commitTimer = null
  animating.value = false
  dx.value = 0
  dy.value = 0
  pointers.clear()
  gesture = null
  resetZoom()
}

onBeforeUnmount(() => {
  clearTimeout(commitTimer)
  clearTimeout(tapTimer)
  clearTimeout(zoomTimer)
})

defineExpose({ go, toggleZoom, reset })
</script>
