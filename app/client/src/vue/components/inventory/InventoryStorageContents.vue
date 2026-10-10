<template>
  <!-- Objekte, Fahrzeuge und Räume in einem Lagerpunkt bzw. in "Nicht einsortiert".
       Ein Klick öffnet das Detail; wer sie bearbeiten darf, zieht sie am Griff in einen Lagerpunkt. -->
  <ul v-if="entries.length" class="inventory-storage-contents">
    <li
      v-for="entry in entries"
      :key="`${entry.kind}-${entry.ID}`"
      class="inventory-storage-contents_entry"
      :class="{ 'inventory-storage-contents_entry--dragging': isDragged(entry) }"
      @pointerdown="draggable && entry.CanEdit && drag?.start($event, { kind: entry.kind, entity: entry })"
    >
      <span
        v-if="draggable && entry.CanEdit && drag?.enabled.value"
        class="inventory-storage-contents_handle"
        data-drag-handle
        title="Ziehen zum Einsortieren"
        aria-hidden="true"
      />
      <component
        :is="isClickable(entry) ? 'button' : 'div'"
        :type="isClickable(entry) ? 'button' : undefined"
        class="inventory-storage-contents_button"
        :class="{ 'inventory-storage-contents_button--static': !isClickable(entry) }"
        @click="isClickable(entry) && emit(entry.kind === 'room' ? 'open-room' : 'open-item', entry)"
      >
        <img v-if="entry.Thumbnail" :src="entry.Thumbnail" :alt="entry.Title" class="inventory-storage-contents_thumb" loading="lazy">
        <span v-else class="inventory-storage-contents_thumb inventory-storage-contents_thumb--icon" aria-hidden="true">
          <span class="icon-mask" :style="kindIconStyle(entry.kind)" />
        </span>
        <span v-if="entry.InventoryNumber" class="inventory-storage-contents_number">{{ entry.InventoryNumber }}</span>
        <span class="inventory-storage-contents_title">{{ entry.Title }}</span>
        <span v-if="entry.Status && entry.Status !== 'available'" class="inventory-status-badge" :class="`inventory-status-badge--${entry.Status}`">{{ entry.StatusLabel }}</span>
      </component>
    </li>
  </ul>
</template>

<script setup>
import { computed, inject } from 'vue'
import actionInventory from '../../../../icons/actions/action_inventory.svg'
import actionCar from '../../../../icons/actions/action_car.svg'
import actionRoom from '../../../../icons/actions/action_room.svg'

const props = defineProps({
  items: { type: Array, default: () => [] },
  rooms: { type: Array, default: () => [] },
  // Ziehen erlauben (im Lager-Baum ja, im Detail-Modal nicht)
  draggable: { type: Boolean, default: true },
  // Welche Einträge anklickbar sind (z.B. öffentliche Seite: nur mit eigenem Teilen-Link)
  isClickable: { type: Function, default: () => true },
})

const emit = defineEmits(['open-item', 'open-room'])

// Drag & Drop aus dem InventoryStorageTab: start, dragging, enabled
const drag = inject('storageDrag', null)

const maskStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })
const KIND_ICONS = { item: maskStyle(actionInventory), vehicle: maskStyle(actionCar), room: maskStyle(actionRoom) }
const kindIconStyle = kind => KIND_ICONS[kind] ?? KIND_ICONS.item

// Objekte/Fahrzeuge (nach Nummer), dann Räume
const entries = computed(() => [
  ...props.items
    .map(i => ({ ...i, kind: i.Kind || 'item' }))
    .sort((a, b) => (a.InventoryNumber || a.Title || '').localeCompare(b.InventoryNumber || b.Title || '', 'de', { numeric: true })),
  ...props.rooms.map(r => ({ ...r, kind: 'room' })),
])

function isDragged(entry) {
  const dragged = drag?.dragging.value
  return !!dragged && dragged.kind !== 'location' && dragged.entity.ID === entry.ID && (dragged.kind === 'room') === (entry.kind === 'room')
}
</script>
