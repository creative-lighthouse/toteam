<template>
  <div class="marketing-map">
    <div ref="container" class="marketing-map_canvas" aria-label="Karte der verteilten Plakate" role="region" />
    <p v-if="failed" class="marketing-map_error">Die Karte konnte nicht geladen werden.</p>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { loadMapLibre, createMap } from '@utils/map'
import { pastelColorForId, pastelTextColorForId } from '@utils/colors'

/**
 * Alle Plakat-Einträge mit Koordinaten als Punkte, gefärbt nach Plakat-Größe
 * und etwas größer bei mehr Plakaten. Aus dem Ort ermittelte (ungefähre)
 * Positionen sind blass. Ein Klick zeigt die Einträge an der Stelle.
 */
const props = defineProps({
  // Einträge aus dem Marketing-Store (nur die mit Koordinaten werden gezeigt)
  entries: { type: Array, required: true },
  // store.mapConfig: { TilesURL, AssetsURL, MaxZoom, BBox }
  config: { type: Object, required: true },
})

const container = ref(null)
const failed = ref(false)
let map = null
let maplibregl = null
let popup = null

function toGeoJson(entries) {
  return {
    type: 'FeatureCollection',
    features: entries
      .filter(e => e.Latitude && e.Longitude)
      .map(e => ({
        type: 'Feature',
        geometry: { type: 'Point', coordinates: [parseFloat(e.Longitude), parseFloat(e.Latitude)] },
        properties: {
          id: e.ID,
          quantity: e.Quantity || 1,
          approx: e.CoordinatesSource === 'Address',
          fill: pastelColorForId(e.PosterSize?.ID),
          stroke: pastelTextColorForId(e.PosterSize?.ID),
        },
      })),
  }
}

// Auf alle Punkte zoomen — ohne Punkte auf den Bereich der Kartendaten
function fitToEntries(data, animate) {
  const coords = data.features.map(f => f.geometry.coordinates)
  const [minLon, minLat, maxLon, maxLat] = coords.length
    ? [
        Math.min(...coords.map(c => c[0])), Math.min(...coords.map(c => c[1])),
        Math.max(...coords.map(c => c[0])), Math.max(...coords.map(c => c[1])),
      ]
    : props.config.BBox
  map.fitBounds([[minLon, minLat], [maxLon, maxLat]], { padding: 50, maxZoom: 15, animate })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}

// Per DOM statt HTML-String, weil Ort und Notiz frei eingegebener Text sind
function popupContent(ids) {
  const root = document.createElement('div')
  root.className = 'marketing-map_popup'
  for (const id of ids) {
    const entry = props.entries.find(e => e.ID === id)
    if (!entry) continue
    const item = document.createElement('div')
    item.className = 'marketing-map_popup-item'

    const title = document.createElement('strong')
    title.textContent = entry.Location || 'Erfasste Position'
    item.append(title)

    const details = [`${entry.Quantity}× ${entry.PosterSize?.Title || 'ohne Größe'}`, formatDate(entry.DistributedAt), entry.Member?.Name]
    const meta = document.createElement('span')
    meta.textContent = details.filter(Boolean).join(' · ')
    item.append(meta)

    if (entry.CoordinatesSource === 'Address') {
      const hint = document.createElement('em')
      hint.textContent = 'Position aus dem Ort ermittelt (ungefähr)'
      item.append(hint)
    }
    root.append(item)
  }
  return root
}

onMounted(async () => {
  try {
    maplibregl = await loadMapLibre()
    // Komponente wurde während des Ladens schon wieder verlassen
    if (!container.value) return

    map = createMap(maplibregl, container.value, props.config)
    fitToEntries(toGeoJson(props.entries), false)

    map.on('load', () => {
      // Aktueller Stand — die Filter können sich während des Ladens geändert haben
      map.addSource('distributions', { type: 'geojson', data: toGeoJson(props.entries) })
      map.addLayer({
        id: 'distributions',
        type: 'circle',
        source: 'distributions',
        paint: {
          'circle-radius': ['min', 18, ['+', 6, ['*', 1.5, ['sqrt', ['get', 'quantity']]]]],
          'circle-color': ['get', 'fill'],
          'circle-stroke-color': ['get', 'stroke'],
          'circle-stroke-width': 2,
          'circle-opacity': ['case', ['get', 'approx'], 0.45, 0.9],
          'circle-stroke-opacity': ['case', ['get', 'approx'], 0.45, 1],
        },
        // Genaue GPS-Punkte liegen über den ungefähren
        layout: { 'circle-sort-key': ['case', ['get', 'approx'], 0, 1] },
      })

      map.on('click', 'distributions', e => {
        const ids = [...new Set(e.features.map(f => f.properties.id))]
        popup?.remove()
        popup = new maplibregl.Popup({ maxWidth: '280px' })
          .setLngLat(e.lngLat)
          .setDOMContent(popupContent(ids))
          .addTo(map)
      })
      map.on('mouseenter', 'distributions', () => { map.getCanvas().style.cursor = 'pointer' })
      map.on('mouseleave', 'distributions', () => { map.getCanvas().style.cursor = '' })
    })
  } catch (e) {
    console.error('Karte konnte nicht geladen werden', e)
    failed.value = true
  }
})

// Filter geändert: Punkte tauschen und neu einpassen
watch(() => props.entries, entries => {
  const source = map?.getSource('distributions')
  if (!source) return
  popup?.remove()
  const data = toGeoJson(entries)
  source.setData(data)
  fitToEntries(data, true)
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})
</script>
