<template>
  <AppModal ref="modal" class="org-event-site-plan-create-modal" title="Lageplan anlegen" @close="close">
    <form id="org-event-site-plan-create-form" class="modalform" @submit.prevent="submit">
      <AppSegmentedToggle v-if="copySources.length" v-model="mode" :options="MODES" />

      <label v-if="mode === 'copy'" class="field">
        Vorlage
        <select v-model="sourceId" required @change="onSourceChange">
          <option :value="null" disabled>— Lageplan wählen —</option>
          <optgroup v-for="group in groups" :key="group.label" :label="group.label">
            <option v-for="plan in group.plans" :key="plan.ID" :value="plan.ID">{{ plan.Title }}</option>
          </optgroup>
        </select>
      </label>

      <label class="field">
        Titel *
        <input v-model="title" type="text" required placeholder="z.B. Gelände Halloweenhaus" />
      </label>

      <AppFileUpload
        v-if="mode === 'new'"
        v-model="backgroundImage"
        label="Hintergrundbild"
        accept="image/jpeg,image/png,image/webp"
        :max-size="10 * 1024 * 1024"
        button-label="Bild auswählen"
        change-label="Anderes Bild wählen"
        hint="JPG, PNG oder WebP, max. 10 MB — lässt sich auch später im Bearbeiten-Modus ändern"
      />

      <p class="org-event-site-plan-create-modal_hint">
        {{ mode === 'copy'
          ? 'Hintergrund, Ebenen und Marker werden übernommen. Die Kopie gehört nur zu diesem Event – Änderungen daran wirken sich nicht auf die Vorlage aus.'
          : 'Der Lageplan gehört nur zu diesem Event. Ebenen und Marker legst du danach im Bearbeiten-Modus an.' }}
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton
        type="submit"
        form="org-event-site-plan-create-form"
        variant="primary"
        :disabled="saving || !valid"
      >
        {{ saving ? 'Wird angelegt…' : 'Anlegen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useOrgEventsStore } from '@stores/orgEvents'
import { apiPostForm } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppFileUpload from '@components/ui/AppFileUpload.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
  // copySources aus GET /maps/eventPlans/{id}: Lagepläne der eigenen Organisationen
  copySources: { type: Array, default: () => [] },
})

// { mapId, ...neuer Stand } — die Event-Seite öffnet den neuen Plan im Bearbeiten-Modus
const emit = defineEmits(['created'])
const store = useOrgEventsStore()

const MODES = [
  { value: 'new', label: 'Neu' },
  { value: 'copy', label: 'Kopie eines Lageplans' },
]

const modal = ref(null)
const mode = ref('new')
const sourceId = ref(null)
const title = ref('')
const backgroundImage = ref(null)
const saving = ref(false)
const error = ref(null)

// Nach Organisation gruppiert; Pläne anderer Events mit dem Event im Namen
const groups = computed(() => {
  const byOrg = new Map()
  for (const plan of props.copySources) {
    const label = plan.OrganizationTitle || 'Ohne Organisation'
    if (!byOrg.has(label)) byOrg.set(label, { label, plans: [] })
    byOrg.get(label).plans.push({ ...plan, Title: plan.EventTitle ? `${plan.Title} (${plan.EventTitle})` : plan.Title })
  }
  return [...byOrg.values()].sort((a, b) => a.label.localeCompare(b.label, 'de'))
})

const valid = computed(() => !!title.value.trim() && (mode.value === 'new' || !!sourceId.value))

// Titel der Vorlage übernehmen, solange noch keiner eingetragen ist
function onSourceChange() {
  const source = props.copySources.find(p => p.ID === sourceId.value)
  if (source && !title.value.trim()) title.value = source.Title
}

function open() {
  mode.value = 'new'
  sourceId.value = null
  title.value = ''
  backgroundImage.value = null
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!valid.value) return
  saving.value = true
  error.value = null
  try {
    const result = await store.createEventPlan(props.eventId, {
      Title: title.value.trim(),
      SourceMapID: mode.value === 'copy' ? sourceId.value : null,
    })
    if (mode.value === 'new' && backgroundImage.value) {
      const formData = new FormData()
      formData.append('image', backgroundImage.value)
      const upload = await apiPostForm(`/maps/uploadbackgroundimage/${result.mapId}`, formData)
      if (upload && upload.success === false) {
        // Der Plan existiert schon — das Bild lässt sich im Bearbeiten-Modus nachreichen
        console.warn('Hintergrundbild-Upload fehlgeschlagen:', upload.error)
      }
    }
    emit('created', result)
    close()
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
