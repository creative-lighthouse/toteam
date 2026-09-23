<template>
  <div v-if="hasHistory || canManageContent" class="event-manage-actions">
    <AppIconButton v-if="hasHistory" variant="neutral" aria-label="Verlauf anzeigen" title="Verlauf anzeigen" @click="historyModal?.open()">
      <span class="icon-mask" :style="historyIconStyle" />
    </AppIconButton>
    <AppIconButton v-if="canManageContent" variant="primary" aria-label="Termin bearbeiten" title="Termin bearbeiten" @click="$emit('edit-appointment', event)">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </AppIconButton>

    <HistoryModal
      v-if="hasHistory"
      ref="historyModal"
      :endpoint="`/calendar/appointmentHistory/${event.ID}`"
      created-label="hat den Termin erstellt"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppIconButton from '@components/AppIconButton.vue'
import HistoryModal from '@components/HistoryModal.vue'
import actionHistory from '../../../../icons/actions/action_history.svg'

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, required: true }
})

defineEmits(['edit-appointment'])

const historyModal = ref(null)
// Terminfindungs-Optionen laufen mit negativer ID als Pseudo-Events durch den Kalender
// und haben keinen eigenen Verlauf
const hasHistory = computed(() => props.event.ID > 0)
const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
</script>
