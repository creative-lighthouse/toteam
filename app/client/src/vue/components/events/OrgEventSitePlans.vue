<template>
  <!-- Lagepläne-Karte der Event-Seite: jeder Plan führt auf die Lageplan-Seite des
       Events, auf der die ausgeliehenen Objekte platziert werden -->
  <div class="org-event-site-plans">
    <ul class="org-event-site-plans_list">
      <li v-for="plan in state.plans" :key="plan.ID" class="org-event-site-plans_plan">
        <router-link
          :to="{ name: 'EventSitePlan', params: { segment: event.URLSegment, mapId: plan.ID } }"
          class="org-event-site-plans_link"
        >
          <img v-if="plan.ThumbnailURL" :src="plan.ThumbnailURL" alt="" class="org-event-site-plans_thumb" loading="lazy">
          <span v-else class="org-event-site-plans_thumb org-event-site-plans_thumb--empty" aria-hidden="true" />
          <span class="org-event-site-plans_text">
            <span class="org-event-site-plans_title">{{ plan.Title }}</span>
            <span class="org-event-site-plans_meta">{{ placedLabel(plan.PlacedCount) }}</span>
          </span>
        </router-link>
        <AppIconButton
          v-if="editable"
          variant="ghost"
          :aria-label="`Lageplan „${plan.Title}“ vom Event lösen`"
          title="Vom Event lösen"
          :disabled="busy"
          @click="detach(plan)"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </li>
    </ul>

    <p v-if="error" class="org-event-site-plans_error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const props = defineProps({
  event: { type: Object, required: true },
  // Stand aus GET /maps/eventPlans/{id}: { plans, availablePlans, items, CanManage, CanRequestRental }
  state: { type: Object, required: true },
  // Lösen nur im Bearbeiten-Modus
  editable: { type: Boolean, default: false },
})

// Neuer Gesamtstand nach jeder Änderung — die Event-Seite hält ihn
const emit = defineEmits(['update'])

const store = useOrgEventsStore()
const busy = ref(false)
const error = ref(null)

function placedLabel(count) {
  if (!count) return 'Noch keine Objekte platziert'
  return count === 1 ? '1 Objekt platziert' : `${count} Objekte platziert`
}

async function detach(plan) {
  const hint = plan.PlacedCount ? ' Die Objekte darauf gelten danach als nicht platziert, ihre Notizen bleiben erhalten.' : ''
  if (!confirm(`Lageplan „${plan.Title}“ von diesem Event lösen?${hint}`)) return
  busy.value = true
  error.value = null
  try {
    emit('update', await store.detachEventPlan(props.event.ID, plan.ID))
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>
