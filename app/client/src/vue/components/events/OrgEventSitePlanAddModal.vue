<template>
  <AppModal ref="modal" class="org-event-site-plan-add-modal" title="Lageplan hinzufügen" @close="close">
    <form id="org-event-site-plan-add-form" class="modalform" @submit.prevent="submit">
      <template v-if="availablePlans.length">
        <label class="field">
          Lageplan
          <select v-model="mapId" required>
            <option :value="null" disabled>— Lageplan wählen —</option>
            <optgroup v-for="group in groups" :key="group.label" :label="group.label">
              <option v-for="plan in group.plans" :key="plan.ID" :value="plan.ID">{{ plan.Title }}</option>
            </optgroup>
          </select>
        </label>
        <p class="org-event-site-plan-add-modal_hint">
          Der Lageplan selbst bleibt unverändert. Was du für dieses Event darauf platzierst, gilt nur für dieses Event.
          <template v-if="selectedPlan && !selectedPlan.IsEventOrg">
            Der Plan gehört zu {{ selectedPlan.OrganizationTitle }} – über das Event sehen ihn alle Mitglieder der Event-Organisation.
          </template>
        </p>
      </template>
      <p v-else class="org-event-site-plan-add-modal_hint">
        In deinen Organisationen gibt es keine weiteren Lagepläne.
        <router-link :to="{ name: 'Map' }">Im Bereich Lagepläne</router-link> kannst du einen neuen anlegen.
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton
        v-if="availablePlans.length"
        type="submit"
        form="org-event-site-plan-add-form"
        variant="primary"
        :disabled="saving || !mapId"
      >
        {{ saving ? 'Speichern…' : 'Hinzufügen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
  // availablePlans aus GET /maps/eventPlans/{id}
  availablePlans: { type: Array, default: () => [] },
})

// Neuer Gesamtstand nach dem Hinzufügen
const emit = defineEmits(['saved'])
const store = useOrgEventsStore()

const modal = ref(null)
const mapId = ref(null)

// Pläne der Event-Organisation zuerst, dann nach Organisation
const groups = computed(() => {
  const byOrg = new Map()
  for (const plan of props.availablePlans) {
    const label = plan.OrganizationTitle || 'Ohne Organisation'
    if (!byOrg.has(label)) byOrg.set(label, { label, own: plan.IsEventOrg, plans: [] })
    byOrg.get(label).plans.push(plan)
  }
  return [...byOrg.values()].sort((a, b) => (b.own - a.own) || a.label.localeCompare(b.label, 'de'))
})

const selectedPlan = computed(() => props.availablePlans.find(p => p.ID === mapId.value) ?? null)
const saving = ref(false)
const error = ref(null)

function open() {
  mapId.value = props.availablePlans.length === 1 ? props.availablePlans[0].ID : null
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!mapId.value) return
  saving.value = true
  error.value = null
  try {
    emit('saved', await store.attachEventPlan(props.eventId, mapId.value))
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
