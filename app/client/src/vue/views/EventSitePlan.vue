<template>
  <!-- Lageplan eines Events: der Plan der Organisation (nur lesend) plus eine Ebene mit
       den für das Event ausgeliehenen Objekten. Wer das Event verwalten darf, platziert
       die Objekte per Ziehen; Platz und Notiz gelten nur für dieses Event. -->
  <div class="section section--MapView section--EventSitePlanPage">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Lageplan…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
        <AppButton :to="{ name: 'EventDetail', params: { segment: route.params.segment } }" variant="primary">← Zum Event</AppButton>
      </div>

      <div v-else-if="map" class="map-container">
        <div class="map-controls" :class="{ 'is-hidden': sidebarHidden }">
          <button class="map-controls_toggle" aria-label="Sidebar umschalten" @click="toggleSidebar">
            <span class="map-controls_toggle-icon">›</span>
          </button>

          <div class="map-controls_header">
            <h3>{{ map.title }}</h3>
          </div>

          <div class="map-controls_wrap">
            <label v-if="plans.length > 1" class="event-site-plan_switch">
              <span>Lageplan</span>
              <select :value="map.id" class="form-control" @change="switchPlan($event.target.value)">
                <option v-for="plan in plans" :key="plan.ID" :value="plan.ID">{{ plan.Title }}</option>
              </select>
            </label>

            <div class="map-controls_layers">
              <h4>Ebenen</h4>
              <div class="map-layers-list">
                <div v-for="layer in layerToggles" :key="layer.id" class="map-layer-item-wrapper">
                  <label class="map-layer-item">
                    <input type="checkbox" class="map-layer-toggle" :checked="layer.active" @change="toggleLayer(layer)" />
                    <span class="map-layer-title">{{ layer.title }}</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="event-site-plan_items">
              <h4>Objekte des Events</h4>
              <p v-if="canManage && items.length" class="event-site-plan_hint">
                „Platzieren“ setzt das Objekt in die Mitte des sichtbaren Ausschnitts – zieh es dann an seinen Platz.
                Änderungen werden sofort gespeichert.
              </p>
              <p v-if="!items.length" class="event-site-plan_hint">
                Für dieses Event ist noch nichts ausgeliehen. Ausleihen beantragst du auf der Event-Seite unter „Hinzufügen“.
              </p>
              <ul v-else class="event-site-plan_list">
                <li
                  v-for="item in items"
                  :key="item.ItemID"
                  class="event-site-plan_item"
                  :class="{ 'event-site-plan_item--here': isHere(item) }"
                >
                  <button type="button" class="event-site-plan_item-main" @click="onItemClick(item)">
                    <span class="event-site-plan_number">{{ item.Number }}</span>
                    <span class="event-site-plan_item-text">
                      <span class="event-site-plan_item-title">
                        {{ item.Title }}
                        <span v-if="item.InventoryNumber" class="event-site-plan_item-inv">{{ item.InventoryNumber }}</span>
                      </span>
                      <span v-if="item.Note" class="event-site-plan_item-note">{{ item.Note }}</span>
                      <span class="event-site-plan_item-meta">
                        {{ placementLabel(item) }}<template v-if="item.RentalStatus !== 'approved' && item.RentalStatus !== 'handed_over'"> · {{ item.RentalStatusLabel || 'Keine Ausleihe mehr' }}</template>
                      </span>
                    </span>
                  </button>
                  <AppButton
                    v-if="canManage && !isHere(item)"
                    size="small"
                    variant="secondary"
                    :disabled="savingItemId === item.ItemID"
                    @click="place(item)"
                  >Platzieren</AppButton>
                </li>
              </ul>
              <p v-if="saveError" class="event-site-plan_error">{{ saveError }}</p>
            </div>
          </div>

          <div class="map-controls_actions">
            <AppButton :to="{ name: 'EventDetail', params: { segment: route.params.segment } }" variant="primary">← Zum Event</AppButton>
            <button class="button action_recenter" aria-label="Ansicht zurücksetzen" @click="resetView">
              <div
                class="resetMapView_button icon--small"
                style="mask-image: url('/_resources/app/client/icons/actions/action_recenter.svg');"
              ></div>
            </button>
          </div>
        </div>

        <div class="map-renderer-wrapper" :class="{ 'sidebar-hidden': sidebarHidden }">
          <div class="map-renderer">
            <canvas ref="canvasEl" id="mapCanvas"></canvas>
          </div>
        </div>
      </div>
    </div>

    <OrgEventItemEditModal
      v-if="event"
      ref="itemEditModal"
      :event-id="event.ID"
      :plans="plans"
      :readonly="!canManage"
      @saved="applyItems"
    />
    <RoomDetailModal ref="roomDetailModal" />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useOrgEventsStore } from '@stores/orgEvents'
import MapRenderer from '../../js/maprenderer.js'
import AppButton from '@components/ui/AppButton.vue'
import OrgEventItemEditModal from '@components/events/OrgEventItemEditModal.vue'
import RoomDetailModal from '@components/rooms/RoomDetailModal.vue'

// Farbe der Objekt-Marker — der Renderer zeichnet auf ein Canvas und braucht einen Hex-Wert
const ITEM_COLOR = '#e67e22'
const ITEM_LAYER_ID = 'event-items'

const route = useRoute()
const router = useRouter()
const pageHeader = usePageHeaderStore()
const store = useOrgEventsStore()

const map = ref(null)
const event = ref(null)
const plans = ref([])
const items = ref([])
const canManage = ref(false)
const loading = ref(true)
const error = ref(null)
const saveError = ref(null)
const savingItemId = ref(null)
const sidebarHidden = ref(false)
const canvasEl = ref(null)
const itemEditModal = ref(null)
const roomDetailModal = ref(null)
// Für die Checkboxen; der Renderer hält den eigentlichen Zustand
const layerToggles = ref([])

let renderer = null
// Bewusst nicht reaktiv: der Renderer verändert die Marker beim Ziehen direkt
let itemLayer = null

const mapId = computed(() => Number(route.params.mapId))

function isHere(item) {
  return item.MapID === map.value?.id && !!item.Position
}

function placementLabel(item) {
  if (isHere(item)) return 'hier platziert'
  if (!item.MapID) return 'nicht platziert'
  const plan = plans.value.find(p => p.ID === item.MapID)
  return plan ? `auf „${plan.Title}“` : 'auf anderem Lageplan'
}

function firstLine(text) {
  const line = (text || '').split('\n')[0].trim()
  return line.length > 40 ? line.slice(0, 39) + '…' : line
}

function itemPOIs() {
  return items.value.filter(isHere).map(item => ({
    id: `item-${item.ItemID}`,
    itemId: item.ItemID,
    title: `${item.Number}. ${item.Title}${item.Note ? ' – ' + firstLine(item.Note) : ''}`,
    description: item.Note || '',
    active: true,
    position: item.Position,
    markerColor: ITEM_COLOR,
    markerText: String(item.Number),
    type: 'item',
  }))
}

function applyItems(newItems) {
  items.value = newItems
  if (itemLayer) {
    itemLayer.pois = itemPOIs()
    renderer?.render()
  }
}

async function load() {
  loading.value = !map.value
  error.value = null
  try {
    const data = await store.fetchEventPlanView(route.params.segment, mapId.value)
    event.value = data.event
    plans.value = data.plans
    items.value = data.items
    canManage.value = !!data.CanManage
    pageHeader.setHeader(data.map.title, data.event.Title)

    itemLayer = {
      id: ITEM_LAYER_ID,
      title: 'Objekte des Events',
      active: true,
      imageUrl: '',
      layerColor: ITEM_COLOR,
      pois: [],
    }
    // Ebenen des Plans unter den Objekten des Events
    const layers = [...data.map.layers, itemLayer]
    map.value = { ...data.map, layers }
    itemLayer.pois = itemPOIs()
    layerToggles.value = layers.map(l => ({ id: l.id, title: l.title, active: l.active }))

    loading.value = false
    await nextTick()
    initRenderer(layers)
  } catch (e) {
    error.value = e.message
    loading.value = false
  }
}

function initRenderer(layers) {
  if (!canvasEl.value) return
  renderer = new MapRenderer('mapCanvas', {
    backgroundImage: map.value.backgroundImage,
    coordinatesUpperLeft: map.value.coordinatesUpperLeft,
    coordinatesUpperRight: map.value.coordinatesUpperRight,
    coordinatesLowerLeft: map.value.coordinatesLowerLeft,
    coordinatesLowerRight: map.value.coordinatesLowerRight,
    layers,
    editMode: false,
    canDragPOI: poi => canManage.value && poi.type === 'item',
    onPOIMoved: onItemMoved,
    onItemPOIClick: poiData => openItem(poiData.poi.itemId),
    onRoomPOIClick: poiData => roomDetailModal.value?.open(poiData.poi.roomId),
  })
}

function openItem(itemId) {
  const item = items.value.find(i => i.ItemID === itemId)
  if (item) itemEditModal.value?.open(item)
}

function onItemClick(item) {
  if (isHere(item) && renderer) {
    const [lat, lng] = item.Position.split(',').map(Number)
    renderer.panToPOI(lat, lng)
  }
  openItem(item.ItemID)
}

async function savePosition(itemId, position) {
  savingItemId.value = itemId
  saveError.value = null
  try {
    applyItems(await store.savePlacement(event.value.ID, itemId, { MapID: map.value.id, Position: position }))
  } catch (e) {
    saveError.value = e.message
    // Marker an die gespeicherte Stelle zurücksetzen
    applyItems(items.value)
  } finally {
    savingItemId.value = null
  }
}

function onItemMoved(poiData) {
  savePosition(poiData.poi.itemId, poiData.poi.position)
}

function place(item) {
  if (!renderer) return
  if (item.MapID && !confirm(`„${item.Title}“ ist ${placementLabel(item)} platziert. Auf diesen Lageplan verschieben?`)) return
  // Ausgeblendete Objekt-Ebene einblenden, sonst wäre der neue Marker unsichtbar
  if (!renderer.activeLayers.has(ITEM_LAYER_ID)) {
    toggleLayer(layerToggles.value.find(l => l.id === ITEM_LAYER_ID))
  }
  savePosition(item.ItemID, renderer.getViewCenterPosition())
}

function toggleLayer(layer) {
  if (!layer) return
  layer.active = !layer.active
  renderer?.toggleLayer(layer.id)
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

function switchPlan(id) {
  router.replace({ name: 'EventSitePlan', params: { segment: route.params.segment, mapId: id } })
}

function teardown() {
  document.querySelector('.map-poi-popup')?.remove()
  renderer = null
  itemLayer = null
}

watch(() => [route.params.segment, route.params.mapId], ([segment, id]) => {
  if (!segment || !id) return
  teardown()
  map.value = null
  load()
}, { immediate: true })

onUnmounted(teardown)
</script>
