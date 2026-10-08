<template>
  <AppModal ref="modal" class="org-event-item-edit-modal" :title="title" @close="close">
    <form v-if="item" id="org-event-item-edit-form" class="modalform" @submit.prevent="submit">
      <p class="org-event-item-edit-modal_meta">
        <span v-if="item.InventoryNumber">{{ item.InventoryNumber }}</span>
        <span v-if="item.Type">{{ item.Type }}</span>
        <span>{{ item.RentalStatusLabel || 'Keine Ausleihe mehr' }}</span>
        <span>{{ placementLabel }}</span>
      </p>

      <label v-if="!readonly" class="field">
        Notiz für dieses Event
        <textarea
          v-model="note"
          rows="4"
          maxlength="2000"
          placeholder="z.B. DMX-Universum 1, Adresse 101, Modus 16 Kanäle"
        />
      </label>
      <p v-else-if="item.Note" class="org-event-item-edit-modal_note">{{ item.Note }}</p>
      <p v-else class="org-event-item-edit-modal_hint">Keine Notiz für dieses Event.</p>

      <p v-if="!readonly" class="org-event-item-edit-modal_hint">
        Die Notiz gilt nur für dieses Event – das Objekt im Inventar bleibt unverändert.
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <template v-if="readonly">
        <AppButton variant="primary" @click="close">Schließen</AppButton>
      </template>
      <template v-else>
        <AppButton v-if="item?.MapID" variant="secondary" :disabled="saving" @click="unplace">Vom Lageplan nehmen</AppButton>
        <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
        <AppButton type="submit" form="org-event-item-edit-form" variant="primary" :disabled="saving">
          {{ saving ? 'Speichern…' : 'Speichern' }}
        </AppButton>
      </template>
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
  // Lagepläne des Events — für den Namen des Plans, auf dem das Objekt steht
  plans: { type: Array, default: () => [] },
  // Ohne Rechte nur ansehen
  readonly: { type: Boolean, default: false },
})

// Aktualisierte Objektliste des Events
const emit = defineEmits(['saved'])
const store = useOrgEventsStore()

const modal = ref(null)
const item = ref(null)
const note = ref('')
const saving = ref(false)
const error = ref(null)

const title = computed(() => (item.value ? `${item.value.Number}. ${item.value.Title}` : 'Objekt'))

const placementLabel = computed(() => {
  if (!item.value?.MapID) return 'nicht platziert'
  const plan = props.plans.find(p => p.ID === item.value.MapID)
  return plan ? `auf „${plan.Title}“` : 'platziert'
})

/** item: Eintrag aus items von GET /maps/eventPlans bzw. /maps/eventPlanView */
function open(eventItem) {
  item.value = eventItem
  note.value = eventItem.Note || ''
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function save(data) {
  saving.value = true
  error.value = null
  try {
    emit('saved', await store.savePlacement(props.eventId, item.value.ItemID, data))
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

function submit() {
  save({ Note: note.value })
}

function unplace() {
  save({ Note: note.value, MapID: null })
}

defineExpose({ open, close })
</script>
