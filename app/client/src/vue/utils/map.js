/**
 * Gemeinsame Grundlage für MapLibre-Karten aus den selbst gehosteten Kacheln
 * (public/tiles/, siehe MapTilesSettings) — es gehen keine Daten an Dritte,
 * daher ohne Zwei-Klick-Lösung. MapLibre ist groß und wird erst bei Bedarf geladen.
 */

// Das pmtiles-Protokoll darf in MapLibre nur einmal registriert werden
let protocolRegistered = false

// Bedienelemente auf Deutsch
const LOCALE = {
  'NavigationControl.ZoomIn': 'Vergrößern',
  'NavigationControl.ZoomOut': 'Verkleinern',
  'AttributionControl.ToggleAttribution': 'Quellenangaben ein-/ausblenden',
  'CooperativeGesturesHandler.WindowsHelpText': 'Strg + Scrollen zum Zoomen der Karte',
  'CooperativeGesturesHandler.MacHelpText': '⌘ + Scrollen zum Zoomen der Karte',
  'CooperativeGesturesHandler.MobileHelpText': 'Mit zwei Fingern die Karte bewegen',
}

let basemaps = null

/** MapLibre samt Kartenstil nachladen; liefert das maplibregl-Modul. */
export async function loadMapLibre() {
  const [maplibregl, { default: workerUrl }, { Protocol }, styles] = await Promise.all([
    import('maplibre-gl'),
    // MapLibre sucht seinen Worker sonst neben maplibre-gl.mjs — den gibt es im Build nicht
    import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
    import('pmtiles'),
    import('@protomaps/basemaps'),
    import('maplibre-gl/dist/maplibre-gl.css'),
  ])
  basemaps = styles
  if (!protocolRegistered) {
    maplibregl.setWorkerUrl(workerUrl)
    maplibregl.addProtocol('pmtiles', new Protocol().tile)
    protocolRegistered = true
  }
  return maplibregl
}

/**
 * Karte mit unserem Grundstil erzeugen. `config` ist die Kartenkonfiguration
 * aus der API ({ TilesURL, AssetsURL, MaxZoom }), `options` geht an maplibregl.Map.
 */
export function createMap(maplibregl, container, config, options = {}) {
  const { layers, namedFlavor } = basemaps
  // MapLibre braucht absolute URLs für Schriften und Symbole
  const base = window.location.origin + config.AssetsURL
  const map = new maplibregl.Map({
    container,
    style: {
      version: 8,
      glyphs: `${base}/fonts/{fontstack}/{range}.pbf`,
      sprite: `${base}/sprites/v4/light`,
      sources: {
        protomaps: {
          type: 'vector',
          url: `pmtiles://${window.location.origin}${config.TilesURL}`,
          attribution: '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap</a>',
        },
      },
      layers: layers('protomaps', namedFlavor('light'), { lang: 'de' }),
    },
    // Über die Kartendaten hinaus vergrößert MapLibre die letzte Stufe einfach weiter
    maxZoom: 18,
    // Scrollen bewegt die Seite, nicht die Karte
    cooperativeGestures: true,
    attributionControl: { compact: true },
    locale: LOCALE,
    ...options,
  })
  map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
  map.on('error', e => console.error('Karte:', e.error ?? e))
  return map
}
