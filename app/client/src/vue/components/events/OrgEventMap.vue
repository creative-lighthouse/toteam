<template>
  <div class="org-event-map">
    <div class="org-event-map_frame">
      <div ref="container" class="org-event-map_canvas" :aria-label="`Karte: ${title}`" role="region" />
      <!-- Liegt unten links auf der Karte; unten rechts sitzt die Quellenangabe von MapLibre -->
      <a class="org-event-map_link" :href="osmLink" target="_blank" rel="noopener">In OpenStreetMap öffnen</a>
    </div>
    <p v-if="failed" class="org-event-map_error">Die Karte konnte nicht geladen werden.</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { loadMapLibre, createMap } from '@utils/map'

/**
 * Karte zum Veranstaltungsort auf der Event-Seite (selbst gehostete Kacheln,
 * siehe utils/map.js).
 */
const props = defineProps({
  latitude: { type: Number, required: true },
  longitude: { type: Number, required: true },
  // event.Map aus der API: { TilesURL, AssetsURL, MaxZoom }
  config: { type: Object, required: true },
  title: { type: String, default: '' },
})

const container = ref(null)
const failed = ref(false)
let map = null

// Externer Link — erst beim Klick geht etwas an OpenStreetMap
const osmLink = computed(() =>
  `https://www.openstreetmap.org/?mlat=${props.latitude}&mlon=${props.longitude}#map=17/${props.latitude}/${props.longitude}`
)

onMounted(async () => {
  try {
    const maplibregl = await loadMapLibre()
    // Komponente wurde während des Ladens schon wieder verlassen
    if (!container.value) return

    const center = [props.longitude, props.latitude]
    map = createMap(maplibregl, container.value, props.config, {
      center,
      zoom: Math.min(15, props.config.MaxZoom || 15),
    })

    const color = getComputedStyle(document.documentElement).getPropertyValue('--ColorPrimary').trim() || '#3f567c'
    new maplibregl.Marker({ color }).setLngLat(center).addTo(map)
  } catch (e) {
    console.error('Karte konnte nicht geladen werden', e)
    failed.value = true
  }
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>
