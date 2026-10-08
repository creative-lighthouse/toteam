<template>
  <AppModal ref="modal" class="org-event-item-edit-modal" :title="title" @close="close">
    <form v-if="item" id="org-event-item-edit-form" class="modalform" @submit.prevent="submit">
      <!-- Bilder wie im Inventar-Detail: klein, Klick öffnet die Lightbox -->
      <ul v-if="item.Images?.length" class="org-event-item-edit-modal_images">
        <li v-for="(img, index) in item.Images" :key="img.ID">
          <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(item.Images, index)">
            <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
          </a>
        </li>
      </ul>

      <p class="org-event-item-edit-modal_meta">
        <span v-if="item.InventoryNumber">{{ item.InventoryNumber }}</span>
        <span v-if="item.Type">{{ item.Type }}</span>
        <span>{{ item.RentalStatusLabel || 'Keine Ausleihe mehr' }}</span>
        <span>{{ placementLabel }}</span>
      </p>

      <label v-if="!readonly" class="field org-event-item-edit-modal_marker">
        Text im Marker
        <span class="org-event-item-edit-modal_marker-row">
          <span class="org-event-item-edit-modal_marker-preview" :style="previewStyle">{{ markerText.trim() || item.Number }}</span>
          <input v-model="markerText" type="text" maxlength="4" :placeholder="String(item.Number)" />
        </span>
      </label>

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
        Marker-Text (max. 4 Zeichen, leer = laufende Nummer) und Notiz gelten nur für dieses Event – das Objekt im Inventar bleibt unverändert.
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
  <AppLightbox ref="lightbox" />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import { itemBadgeStyle, itemMarkerText } from '@utils/eventItems'

const props = defineProps({
  eventId: { type: Number, required: true },
  // Lagepläne des Events — für den Namen des Plans, auf dem das Objekt steht
  plans: { type: Array, default: () => [] },
  // Ohne Rechte nur ansehen
  readonly: { type: Boolean, default: false },
})

// Aktualisierte Objektliste des Events; als zweites Argument { itemId, unplaced }
const emit = defineEmits(['saved'])
const store = useOrgEventsStore()

const modal = ref(null)
const lightbox = ref(null)
const item = ref(null)
const note = ref('')
const saving = ref(false)
const error = ref(null)

const markerText = ref('')

const title = computed(() => (item.value ? `${itemMarkerText(item.value)}: ${item.value.Title}` : 'Objekt'))

// Vorschau des Markers mit dem eingegebenen Text
const previewStyle = computed(() => (item.value ? itemBadgeStyle(item.value) : null))

const placementLabel = computed(() => {
  if (!item.value?.MapID) return 'nicht platziert'
  const plan = props.plans.find(p => p.ID === item.value.MapID)
  return plan ? `auf „${plan.Title}“` : 'platziert'
})

/** item: Eintrag aus items von GET /maps/eventPlans bzw. /maps/eventPlanView */
function open(eventItem) {
  item.value = eventItem
  note.value = eventItem.Note || ''
  markerText.value = eventItem.MarkerText || ''
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
    const items = await store.savePlacement(props.eventId, item.value.ItemID, data)
    emit('saved', items, { itemId: item.value.ItemID, unplaced: 'MapID' in data && !data.MapID })
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

function submit() {
  save({ Note: note.value, MarkerText: markerText.value.trim() })
}

function unplace() {
  save({ Note: note.value, MarkerText: markerText.value.trim(), MapID: null })
}

defineExpose({ open, close })
</script>
