<template>
  <div v-if="visible" class="agenda-section">
    <div class="section-feature-header">
      <h3 class="event-agenda_title">Tagesordnung</h3>
      <AppIconButton
        v-if="canManageContent"
        variant="primary"
        aria-label="Tagesordnung bearbeiten"
        @click="agendaModal?.open()"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </AppIconButton>
    </div>

    <div class="agenda-list">
      <div v-for="point in event.AgendaPoints" :key="point.ID" class="agenda-point">
        <span class="agenda-point_time">{{ point.RenderTime }}</span>
        <div class="agenda-point_content">
          <strong class="agenda-point_title">{{ point.Title }}</strong>
          <p v-if="point.Description" class="agenda-point_desc agenda-point_desc--pre">
            {{ point.Description }}
          </p>
        </div>
      </div>
    </div>

    <p v-if="!event.AgendaPoints || event.AgendaPoints.length === 0" class="event-section-empty">
      Noch keine Tagesordnungspunkte geplant.
    </p>

    <EventAgendaModal
      v-if="canManageContent"
      ref="agendaModal"
      :event="event"
      @show-status="$emit('show-status', $event)"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppIconButton from '@components/AppIconButton.vue'
import EventAgendaModal from './EventAgendaModal.vue'

const props = defineProps({
  event: { type: Object, required: true },
  canManageContent: { type: Boolean, default: false }
})

defineEmits(['show-status'])

const agendaModal = ref(null)

const visible = computed(() => {
  if (!props.event.EnableAgenda) return false
  if (props.canManageContent) return true
  return props.event.AgendaPoints && props.event.AgendaPoints.length > 0
})
</script>
