<template>
  <!-- "Nicht einsortiert": je Organisation bzw. Person ein aufklappbarer Kasten mit
       sichtbaren Objekten, Fahrzeugen und Räumen ohne Lagerort. Von hier aus lassen
       sie sich in Lagerpunkte ziehen; Ablegen hier nimmt sie wieder aus dem Lager. -->
  <section v-if="groups.length || dropActive" class="inventory-storage-unassigned">
    <h3 class="hl3 inventory-storage-unassigned_title">Nicht einsortiert</h3>

    <div
      v-if="dropActive && !groups.length"
      class="inventory-storage-unassigned_box inventory-storage-unassigned_box--empty"
      :class="{ 'inventory-storage-unassigned_box--over': overUnassigned }"
      data-drop-zone="unassigned"
    >
      Hierher ziehen: aus dem Lager nehmen
    </div>

    <div
      v-for="group in groups"
      :key="group.Key"
      class="inventory-storage-unassigned_box"
      :class="{ 'inventory-storage-unassigned_box--over': dropActive && overUnassigned }"
      data-drop-zone="unassigned"
    >
      <button type="button" class="inventory-storage-unassigned_header" :aria-expanded="isOpen(group)" @click="toggle(group.Key)">
        <span class="inventory-storage-node_chevron" :class="{ 'inventory-storage-unassigned_chevron--open': isOpen(group) }" aria-hidden="true" />
        <img v-if="group.Image" :src="group.Image" :alt="group.Title" class="inventory-storage-unassigned_image">
        <span class="inventory-storage-unassigned_name">{{ group.Title }}</span>
        <span class="inventory-storage-node_count">{{ group.Items.length + group.Rooms.length }}</span>
      </button>
      <div v-if="isOpen(group)" class="inventory-storage-unassigned_body">
        <InventoryStorageContents
          :items="group.Items"
          :rooms="group.Rooms"
          @open-item="i => emit('open-item', i)"
          @open-room="r => emit('open-room', r)"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { useStorageStore } from '@stores/storage'
import InventoryStorageContents from '@components/inventory/InventoryStorageContents.vue'

const emit = defineEmits(['open-item', 'open-room'])

const store = useStorageStore()
const drag = inject('storageDrag')

// Ablegen hier nur für Objekte/Räume, die gerade in einem Lagerpunkt liegen
const dropActive = computed(() => {
  const dragged = drag.dragging.value
  return !!dragged && dragged.kind !== 'location'
})
const overUnassigned = computed(() => drag.dropTarget.value?.unassigned === true)

// Suche wie im Baum: Name oder Inventarnummer
const groups = computed(() => {
  const q = store.filterSearch.trim().toLowerCase()
  if (!q) return store.unassigned
  const match = e => e.Title?.toLowerCase().includes(q) || e.InventoryNumber?.toLowerCase().includes(q)
  return store.unassigned
    .map(group => ({ ...group, Items: group.Items.filter(match), Rooms: group.Rooms.filter(match) }))
    .filter(group => group.Items.length || group.Rooms.length)
})

// Zugeklappt, damit lange Listen den Baum nicht verdrängen; bei einer Suche offen
const openKeys = ref(new Set())
const isOpen = group => !!store.filterSearch.trim() || openKeys.value.has(group.Key)

function toggle(key) {
  const next = new Set(openKeys.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  openKeys.value = next
}
</script>
