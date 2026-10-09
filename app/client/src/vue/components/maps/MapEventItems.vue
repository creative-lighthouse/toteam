<template>
  <!-- Seitenleiste eines Event-Lageplans (MapDetail.vue): die für das Event ausgeliehenen
       Objekte. Plakette wie der Marker (Farbe nach Objektname, Nummer oder eigener Text). Platzieren nur im Bearbeiten-Modus. -->
  <div class="map-event-items">
    <h4>Ausgeliehenes Inventar</h4>
    <p v-if="editing && items.length" class="map-event-items_hint">
      „Platzieren“ setzt ein Objekt in die Mitte des sichtbaren Ausschnitts – zieh es dann an seinen Platz.
      Gespeichert wird mit „Speichern“.
    </p>
    <p v-if="!items.length" class="map-event-items_hint">
      Für dieses Event ist noch nichts ausgeliehen. Ausleihen beantragst du auf der Event-Seite unter „Hinzufügen“.
    </p>
    <ul v-else class="map-event-items_list">
      <li
        v-for="item in items"
        :key="item.ItemID"
        class="map-event-items_item"
        :class="{ 'map-event-items_item--here': isHere(item) }"
      >
        <button type="button" class="map-event-items_main" @click="emit('select', item)">
          <span class="map-event-items_number" :style="itemBadgeStyle(item)">{{ itemMarkerText(item) }}</span>
          <span class="map-event-items_text">
            <span class="map-event-items_title">
              {{ item.Title }}
              <span v-if="item.InventoryNumber" class="map-event-items_inv">{{ item.InventoryNumber }}</span>
            </span>
            <span v-if="item.Note" class="map-event-items_note">{{ item.Note }}</span>
            <span class="map-event-items_meta">
              {{ placementLabel(item) }}<template v-if="item.RentalStatus !== 'approved' && item.RentalStatus !== 'handed_over'"> · {{ item.RentalStatusLabel || 'Keine Ausleihe mehr' }}</template>
            </span>
          </span>
        </button>
        <AppButton v-if="editing && !isHere(item)" size="small" variant="secondary" @click="emit('place', item)">
          Platzieren
        </AppButton>
      </li>
    </ul>
  </div>
</template>

<script setup>
import AppButton from '@components/ui/AppButton.vue'
import { itemBadgeStyle, itemMarkerText } from '@utils/eventItems'

const props = defineProps({
  // items aus GET /maps/view/{id} (nur bei Lageplänen eines Events)
  items: { type: Array, required: true },
  // Lagepläne des Events [{ ID, Title }] — für "auf anderem Plan"
  plans: { type: Array, default: () => [] },
  // Liegt das Objekt (ggf. noch ungespeichert) auf diesem Plan?
  isHere: { type: Function, required: true },
  editing: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'place'])

function placementLabel(item) {
  if (props.isHere(item)) return 'auf diesem Plan'
  if (!item.MapID) return 'nicht platziert'
  const plan = props.plans.find(p => p.ID === item.MapID)
  return plan ? `auf „${plan.Title}“` : 'auf anderem Lageplan'
}
</script>
