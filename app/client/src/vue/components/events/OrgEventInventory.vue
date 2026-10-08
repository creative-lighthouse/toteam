<template>
  <!-- Inventar-Karte der Event-Seite: die Objekte aus den Ausleihen des Events mit
       ihrer Event-Notiz (z.B. DMX-Adresse) und dem Lageplan, auf dem sie stehen.
       Die Nummer ist dieselbe wie auf den Markern im Lageplan. -->
  <ul class="org-event-inventory">
    <li v-for="item in items" :key="item.ItemID" class="org-event-inventory_item">
      <span class="org-event-inventory_number">{{ item.Number }}</span>

      <div class="org-event-inventory_body">
        <p class="org-event-inventory_head">
          <span class="org-event-inventory_title">{{ item.Title }}</span>
          <span v-if="item.InventoryNumber" class="org-event-inventory_inv">{{ item.InventoryNumber }}</span>
        </p>
        <p v-if="item.Note" class="org-event-inventory_note">{{ item.Note }}</p>
        <p class="org-event-inventory_meta">
          <button
            v-if="item.RentalID"
            type="button"
            class="org-event-inventory_status"
            :class="`org-event-inventory_status--${item.RentalStatus}`"
            title="Ausleihe ansehen"
            @click="emit('open-rental', item.RentalID)"
          >{{ item.RentalStatusLabel }}</button>
          <span v-else class="org-event-inventory_status org-event-inventory_status--none">Keine Ausleihe mehr</span>
          <span>{{ placementLabel(item) }}</span>
        </p>
      </div>

      <AppIconButton
        v-if="editable"
        variant="ghost"
        :aria-label="`Notiz zu „${item.Title}“ bearbeiten`"
        title="Notiz bearbeiten"
        @click="emit('edit', item)"
      >
        <span class="icon-mask" :style="editIconStyle" aria-hidden="true" />
      </AppIconButton>
    </li>
  </ul>
</template>

<script setup>
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionEdit from '../../../../icons/actions/action_edit.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }

const props = defineProps({
  // items aus GET /maps/eventPlans/{id}
  items: { type: Array, required: true },
  // plans aus demselben Stand — für den Namen des Lageplans
  plans: { type: Array, default: () => [] },
  // Notiz bearbeiten nur im Bearbeiten-Modus
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'open-rental'])

function placementLabel(item) {
  if (!item.MapID) return 'nicht platziert'
  const plan = props.plans.find(p => p.ID === item.MapID)
  return plan ? `auf „${plan.Title}“` : 'platziert'
}
</script>
