<template>
  <!-- Umgebungskarte hinter einem Lageplan (MapDetail.vue): eine nach Norden ausgerichtete
       MapLibre-Karte, die per CSS-Matrix so gedreht, skaliert und geschert wird, dass sie
       genau unter dem (unverändert angezeigten) Plan liegt. Rein dekorativ — alle Bedienung
       bleibt beim Lageplan-Renderer, der nach jedem Zeichnen sync() aufruft. -->
  <div class="map-basemap" aria-hidden="true">
    <!-- Größe und Matrix setzt sync() direkt am Element (keine reaktive :style-Bindung,
         die die Matrix beim nächsten Vue-Update überschreiben würde) -->
    <div ref="mapEl" class="map-basemap_map" />
    <span class="map-basemap_attribution">© OpenStreetMap</span>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { loadMapLibre, createMap } from '@utils/map'

const props = defineProps({
  // Kartenkonfiguration aus der API ({ TilesURL, AssetsURL, MaxZoom, BBox })
  config: { type: Object, required: true },
})

// Erdumfang am Äquator in Metern; MapLibre rechnet mit 512px-Kacheln
const EARTH_CIRCUMFERENCE = 40075016.686
const TILE_SIZE = 512
const MAX_ZOOM = 24

const mapEl = ref(null)
let map = null
// Zuletzt gemeldete Ansicht — beim Laden der Karte (und wenn sie vorher noch fehlte) nachholen
let lastView = null
let size = 0

/**
 * An die Ansicht des Lageplans anpassen. view: getViewState() des MapRenderer —
 * { width, height, corners: [{ geo: {lat,lng}, screen: {x,y} } × UL/UR/LL], center, metersPerPixel }
 */
function sync(view) {
  if (!view) return
  lastView = view
  if (!map) return

  // Kartenfläche groß genug, dass sie auch gedreht/geschert den ganzen Canvas abdeckt
  const needed = Math.ceil(Math.hypot(view.width, view.height) * 1.6)
  if (Math.abs(needed - size) > 50) {
    size = needed
    mapEl.value.style.width = `${size}px`
    mapEl.value.style.height = `${size}px`
    map.resize()
  }

  // Norden oben, Mittelpunkt und Maßstab wie im Lageplan
  const lat = view.center.lat
  const metersPerPixel = view.metersPerPixel || 1
  const zoom = Math.log2((EARTH_CIRCUMFERENCE * Math.cos(lat * Math.PI / 180)) / (TILE_SIZE * metersPerPixel))
  map.jumpTo({ center: [view.center.lng, lat], zoom: Math.min(MAX_ZOOM, Math.max(0, zoom)), bearing: 0, pitch: 0 })

  // Affine Abbildung Kartenpixel → Canvaspixel aus den drei Ecken UL, UR, LL
  const [p0, p1, p2] = view.corners.map(c => map.project([c.geo.lng, c.geo.lat]))
  const [s0, s1, s2] = view.corners.map(c => c.screen)
  const ux = p1.x - p0.x, uy = p1.y - p0.y
  const vx = p2.x - p0.x, vy = p2.y - p0.y
  const det = ux * vy - vx * uy
  if (Math.abs(det) < 1e-9) return
  // M · [u v] = [S1−S0  S2−S0]  →  M = [S1−S0  S2−S0] · [u v]⁻¹
  const sx1 = s1.x - s0.x, sy1 = s1.y - s0.y
  const sx2 = s2.x - s0.x, sy2 = s2.y - s0.y
  const a = (sx1 * vy - sx2 * uy) / det
  const c = (sx2 * ux - sx1 * vx) / det
  const b = (sy1 * vy - sy2 * uy) / det
  const d = (sy2 * ux - sy1 * vx) / det
  const e = s0.x - (a * p0.x + c * p0.y)
  const f = s0.y - (b * p0.x + d * p0.y)
  mapEl.value.style.transform = `matrix(${a}, ${b}, ${c}, ${d}, ${e}, ${f})`
}

onMounted(async () => {
  const maplibregl = await loadMapLibre()
  if (!mapEl.value) return
  map = createMap(maplibregl, mapEl.value, props.config, {
    navigation: false,
    interactive: false,
    attributionControl: false,
    cooperativeGestures: false,
    maxZoom: MAX_ZOOM,
    // Kein Ausblenden der Karte, während MapLibre neue Kacheln lädt
    fadeDuration: 0,
  })
  map.once('load', () => {
    if (lastView) sync(lastView)
  })
  if (lastView) sync(lastView)
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})

defineExpose({ sync })
</script>
