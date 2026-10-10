<template>
  <!-- Tab "Lager": Lagerpunkte als aufklappbarer Baum (Ort › Gebäude › Kiste …)
       mit allem, was darin lagert -->
  <div class="inventory-storage-tab">
    <AppSearchBar
      :model-value="store.filterSearch"
      placeholder="Lagerpunkt, Objekt oder Raum suchen…"
      @update:model-value="store.setSearchFilter"
    >
      <template #actions>
        <AppIconButton
          v-if="inventoryStore.manageableTypeOrgs.length"
          variant="neutral"
          aria-label="Lager-Arten verwalten"
          title="Lager-Arten verwalten"
          @click="emit('manage-types')"
        >
          <span class="icon-mask" :style="typesIconStyle" />
        </AppIconButton>
        <AppIconButton v-if="nfcSupported" variant="neutral" aria-label="NFC-Tag scannen" title="NFC-Tag scannen" @click="emit('scan-nfc')">
          <span class="icon-mask" :style="nfcIconStyle" />
        </AppIconButton>
        <AppButton v-if="canCreate" variant="primary" @click="formModal?.open()">+ Neu</AppButton>
      </template>
      <template v-if="store.locations.length" #filters>
        <AppButton size="small" variant="secondary" @click="expandAll">Alle aufklappen</AppButton>
        <AppButton size="small" variant="secondary" :disabled="!expandedIds.size" @click="expandedIds = new Set()">Alle zuklappen</AppButton>
      </template>
    </AppSearchBar>

    <div v-if="store.loading" class="section_infobox">
      <p>Lade Lager…</p>
    </div>

    <div v-else-if="store.error" class="section_infobox error">
      <p>Fehler: {{ store.error }}</p>
      <AppButton variant="primary" @click="store.fetchLocations(true)">Erneut versuchen</AppButton>
    </div>

    <div v-else-if="!store.locations.length" class="section_infobox">
      <p>Noch keine Lagerpunkte angelegt. Lagerpunkte sind Orte, Gebäude, Kisten oder Stellplätze – sie lassen sich ineinander verschachteln, und Objekte, Fahrzeuge und Räume können ihnen zugeordnet werden.</p>
    </div>

    <div v-else-if="store.matchingIds && !store.matchingIds.size" class="section_infobox">
      <p>Kein passender Lagerpunkt.</p>
    </div>

    <p v-else-if="canDrag && !store.matchingIds" class="inventory-storage-tab_hint">
      Am Griff ziehen: Lagerpunkte auf einen anderen Lagerpunkt, um sie hineinzulegen, oder an dessen oberen/unteren Rand, um sie davor oder dahinter einzusortieren. Objekte, Fahrzeuge und Räume – auch aus „Nicht einsortiert“ – einfach auf den Lagerpunkt ziehen.
    </p>

    <ul v-if="store.locations.length && !store.loading && !store.error && !(store.matchingIds && !store.matchingIds.size)" class="inventory-storage-tree inventory-storage-tree--root">
      <InventoryStorageNode
        v-for="location in store.rootLocations"
        :key="location.ID"
        :location="location"
        :expanded-ids="effectiveExpandedIds"
        @toggle="toggle"
        @view="location => detailModal?.open(location.ID)"
        @edit="location => formModal?.openForEdit(location)"
        @delete="remove"
        @open-item="item => emit('open-item', item)"
        @open-room="room => emit('open-room', room)"
      />
    </ul>

    <!-- Beim Ziehen eines verschachtelten Lagerpunkts: Ablage auf der obersten Ebene -->
    <div
      v-if="dragging?.kind === 'location' && dragging.entity.ParentID"
      class="inventory-storage-tab_root-drop"
      :class="{ 'inventory-storage-tab_root-drop--over': overZone === 'root' }"
      data-drop-zone="root"
    >
      Hierher ziehen: auf die oberste Ebene
    </div>

    <InventoryStorageUnassigned
      v-if="!store.loading && !store.error"
      @open-item="item => emit('open-item', item)"
      @open-room="room => emit('open-room', room)"
    />

    <InventoryStorageDetailModal
      ref="detailModal"
      :can-create="canCreate"
      @edit="location => formModal?.openForEdit(location)"
      @delete="remove"
      @add-inside="location => formModal?.open({ parentId: location.ID })"
      @open-item="item => emit('open-item', item)"
      @open-room="room => emit('open-room', room)"
    />
    <InventoryStorageFormModal ref="formModal" @saved="onSaved" />
  </div>
</template>

<script setup>
import { ref, computed, watch, provide, onMounted } from 'vue'
import { usePointerDrag } from '@utils/pointerDrag'
import { useStorageStore } from '@stores/storage'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import InventoryStorageNode from '@components/inventory/InventoryStorageNode.vue'
import InventoryStorageFormModal from '@components/inventory/InventoryStorageFormModal.vue'
import InventoryStorageUnassigned from '@components/inventory/InventoryStorageUnassigned.vue'
import InventoryStorageDetailModal from '@components/inventory/InventoryStorageDetailModal.vue'
import { useRoomsStore } from '@stores/rooms'
import actionPages from '../../../../icons/actions/action_pages.svg'
import actionNfc from '../../../../icons/actions/action_nfc.svg'
import { isNfcSupported } from '@utils/nfc'

const emit = defineEmits(['manage-types', 'open-item', 'open-room', 'scan-nfc'])

const store = useStorageStore()
const inventoryStore = useInventoryStore()
const formModal = ref(null)
const detailModal = ref(null)

const typesIconStyle = { maskImage: `url("${actionPages}")`, WebkitMaskImage: `url("${actionPages}")` }
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }
const nfcSupported = isNfcSupported()

const canCreate = computed(() => store.creatableOrgs.length > 0 || store.canCreatePrivate)

// Aufgeklappte Lagerpunkte; bei einer Suche sind alle Treffer aufgeklappt
const expandedIds = ref(new Set())
const effectiveExpandedIds = computed(() => store.matchingIds ?? expandedIds.value)

function toggle(id) {
  const next = new Set(expandedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedIds.value = next
}

function expandAll() {
  expandedIds.value = new Set(store.locations.map(l => l.ID))
}

function onSaved(location) {
  // Den neuen/geänderten Lagerpunkt sichtbar machen: übergeordnete aufklappen
  const next = new Set(expandedIds.value)
  let parentId = location.ParentID
  while (parentId && !next.has(parentId)) {
    next.add(parentId)
    parentId = store.byId.get(parentId)?.ParentID
  }
  expandedIds.value = next
}

// ── Drag & Drop: sortieren, hinein- und herausziehen ──────────────────────────
// Ziel ist der Kopf eines Lagerpunkts: oberes Viertel = davor, unteres = dahinter, Mitte = hinein
const EDGE = 0.25

// Gibt es überhaupt etwas, das der Nutzer ziehen darf?
const canDrag = computed(() =>
  store.locations.some(l => l.CanEdit || l.Items?.some(i => i.CanEdit) || l.Rooms?.some(r => r.CanEdit))
  || store.unassigned.some(g => g.Items.some(i => i.CanEdit) || g.Rooms.some(r => r.CanEdit))
)
// Bei aktiver Suche ist der Baum gefiltert — dann nicht ziehen
const dragEnabled = computed(() => !store.matchingIds)

const { start, dragging, overZone, overRatio } = usePointerDrag({
  onDrop: (payload, zone, ratio) => (payload.kind === 'location' ? drop(payload.entity, zone, ratio) : place(payload, zone)),
  // Das Hauptmenü ist unten fest — dort ebenfalls mitscrollen
  bottomInset: () => 90,
})

/**
 * Objekt/Fahrzeug/Raum: { targetId, position: 'inside' } auf einem Lagerpunkt,
 * { unassigned: true } über "Nicht einsortiert", sonst null
 */
function resolvePlaceTarget(payload, zone) {
  if (zone === 'unassigned') return { unassigned: true }
  if (!zone?.startsWith('loc:')) return null
  return { targetId: parseInt(zone.slice(4)), position: 'inside' }
}

/** { targetId, position: 'before' | 'inside' | 'after' } oder null, wenn das Ziel ungültig ist */
function resolveTarget(location, zone, ratio) {
  if (!location || !zone?.startsWith('loc:')) return null
  const targetId = parseInt(zone.slice(4))
  // Nicht in sich selbst oder in einen eigenen Unter-Lagerpunkt
  if (targetId === location.ID || store.descendantIds(location.ID).has(targetId)) return null
  const position = ratio < EDGE ? 'before' : ratio > 1 - EDGE ? 'after' : 'inside'
  return { targetId, position }
}

const dropTarget = computed(() => {
  const payload = dragging.value
  if (!payload) return null
  return payload.kind === 'location'
    ? resolveTarget(payload.entity, overZone.value, overRatio.value)
    : resolvePlaceTarget(payload, overZone.value)
})

// Für InventoryStorageNode/-Contents/-Unassigned. Payload: { kind: 'location'|'item'|'vehicle'|'room', entity }
provide('storageDrag', {
  start: (event, payload) => {
    if (dragEnabled.value) start(event, payload)
  },
  enabled: dragEnabled,
  dragging,
  dropTarget,
})

// Über einem zugeklappten Lagerpunkt kurz verweilen ("hinein") klappt ihn auf
let expandTimer = null
watch(dropTarget, (target) => {
  clearTimeout(expandTimer)
  if (target?.position === 'inside' && !expandedIds.value.has(target.targetId)) {
    expandTimer = setTimeout(() => toggle(target.targetId), 700)
  }
})

async function drop(location, zone, ratio) {
  clearTimeout(expandTimer)
  let parentId
  let siblings
  if (zone === 'root') {
    parentId = null
    siblings = [...store.rootLocations.filter(l => l.ID !== location.ID), location]
  } else {
    const target = resolveTarget(location, zone, ratio)
    if (!target) return
    if (target.position === 'inside') {
      parentId = target.targetId
      siblings = [...store.childrenOf(parentId).filter(l => l.ID !== location.ID), location]
    } else {
      const targetLocation = store.byId.get(target.targetId)
      // Übergeordneter des Ziels (nicht sichtbare Eltern zählen als oberste Ebene)
      parentId = targetLocation.ParentID && store.byId.has(targetLocation.ParentID) ? targetLocation.ParentID : null
      siblings = (parentId ? store.childrenOf(parentId) : store.rootLocations).filter(l => l.ID !== location.ID)
      const index = siblings.findIndex(l => l.ID === target.targetId) + (target.position === 'after' ? 1 : 0)
      siblings.splice(index, 0, location)
    }
  }

  const currentParent = location.ParentID && store.byId.has(location.ParentID) ? location.ParentID : null
  const orderedIds = siblings.map(l => l.ID)
  const currentOrder = (currentParent ? store.childrenOf(currentParent) : store.rootLocations).map(l => l.ID)
  if (parentId === currentParent && orderedIds.join() === currentOrder.join()) return

  if (parentId && !expandedIds.value.has(parentId)) toggle(parentId)
  const response = await store.moveLocation(location.ID, parentId, orderedIds)
  if (!response.success) alert('Fehler: ' + (response.error || 'Lagerpunkt konnte nicht verschoben werden.'))
}

const roomsStore = useRoomsStore()

/** Objekt/Fahrzeug/Raum in einen Lagerpunkt legen bzw. zurück nach "Nicht einsortiert" */
async function place(payload, zone) {
  clearTimeout(expandTimer)
  const target = resolvePlaceTarget(payload, zone)
  if (!target) return
  const listKey = payload.kind === 'room' ? 'Rooms' : 'Items'
  const current = store.locations.find(l => l[listKey]?.some(e => e.ID === payload.entity.ID)) ?? null
  const locationId = target.unassigned ? null : target.targetId
  if ((current?.ID ?? null) === locationId) return

  const { kind, ...entity } = payload.entity
  if (locationId && !expandedIds.value.has(locationId)) toggle(locationId)
  const response = await store.placeEntity(payload.kind, entity, locationId)
  if (!response.success) {
    alert('Fehler: ' + (response.error || 'Konnte nicht einsortiert werden.'))
    return
  }
  // Lagerort steht auch in Objekt- und Raumliste — dort im Hintergrund aktualisieren
  if (payload.kind === 'room') {
    if (roomsStore.rooms.length) roomsStore.fetchRooms(true)
  } else if (inventoryStore.items.length) {
    inventoryStore.fetchItems(true)
  }
}

async function remove(location) {
  const parentTitle = location.ParentID ? store.byId.get(location.ParentID)?.Title : null
  const where = parentTitle ? `in „${parentTitle}“` : 'auf die oberste Ebene bzw. ist keinem Lagerpunkt mehr zugeordnet'
  if (!confirm(`Lagerpunkt „${location.Title}“ wirklich löschen?\n\nWas darin lagert, rückt eine Ebene nach oben (${where}).`)) return
  const response = await store.deleteLocation(location.ID)
  if (!response.success) alert('Fehler: ' + (response.error || 'Lagerpunkt konnte nicht gelöscht werden.'))
  else detailModal.value?.close()
}

onMounted(() => {
  store.fetchLocations()
})

/** Detail eines Lagerpunkts von außen öffnen (z.B. später nach einem NFC-Scan) */
function openLocation(id) {
  detailModal.value?.open(id)
}

defineExpose({ openLocation })
</script>
