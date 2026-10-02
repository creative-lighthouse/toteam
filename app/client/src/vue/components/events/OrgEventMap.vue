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

<script>
// Das pmtiles-Protokoll darf in MapLibre nur einmal registriert werden
let protocolRegistered = false
</script>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

/**
 * Karte zum Veranstaltungsort auf der Event-Seite. Kacheln, Schriften und Symbole
 * kommen vom eigenen Server (public/tiles/, siehe MapTilesSettings) — es gehen
 * keine Daten an Dritte, daher ohne Zwei-Klick-Lösung. MapLibre ist groß und wird
 * erst hier nachgeladen.
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

// Bedienelemente auf Deutsch
const LOCALE = {
  'NavigationControl.ZoomIn': 'Vergrößern',
  'NavigationControl.ZoomOut': 'Verkleinern',
  'AttributionControl.ToggleAttribution': 'Quellenangaben ein-/ausblenden',
  'CooperativeGesturesHandler.WindowsHelpText': 'Strg + Scrollen zum Zoomen der Karte',
  'CooperativeGesturesHandler.MacHelpText': '⌘ + Scrollen zum Zoomen der Karte',
  'CooperativeGesturesHandler.MobileHelpText': 'Mit zwei Fingern die Karte bewegen',
}

onMounted(async () => {
  try {
    const [maplibregl, { default: workerUrl }, { Protocol }, { layers, namedFlavor }] = await Promise.all([
      import('maplibre-gl'),
      // MapLibre sucht seinen Worker sonst neben maplibre-gl.mjs — den gibt es im Build nicht
      import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
      import('pmtiles'),
      import('@protomaps/basemaps'),
      import('maplibre-gl/dist/maplibre-gl.css'),
    ])
    // Komponente wurde während des Ladens schon wieder verlassen
    if (!container.value) return

    if (!protocolRegistered) {
      maplibregl.setWorkerUrl(workerUrl)
      maplibregl.addProtocol('pmtiles', new Protocol().tile)
      protocolRegistered = true
    }

    // MapLibre braucht absolute URLs für Schriften und Symbole
    const base = window.location.origin + props.config.AssetsURL
    const center = [props.longitude, props.latitude]
    map = new maplibregl.Map({
      container: container.value,
      style: {
        version: 8,
        glyphs: `${base}/fonts/{fontstack}/{range}.pbf`,
        sprite: `${base}/sprites/v4/light`,
        sources: {
          protomaps: {
            type: 'vector',
            url: `pmtiles://${window.location.origin}${props.config.TilesURL}`,
            attribution: '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap</a>',
          },
        },
        layers: layers('protomaps', namedFlavor('light'), { lang: 'de' }),
      },
      center,
      zoom: Math.min(15, props.config.MaxZoom || 15),
      // Über die Kartendaten hinaus vergrößert MapLibre die letzte Stufe einfach weiter
      maxZoom: 18,
      // Scrollen bewegt die Seite, nicht die Karte
      cooperativeGestures: true,
      attributionControl: { compact: true },
      locale: LOCALE,
    })
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')

    const color = getComputedStyle(document.documentElement).getPropertyValue('--ColorPrimary').trim() || '#3f567c'
    new maplibregl.Marker({ color }).setLngLat(center).addTo(map)

    map.on('error', e => console.error('Karte:', e.error ?? e))
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
