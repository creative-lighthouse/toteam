<template>
  <!--
    Gericht für ein Event: entweder als eigener Vorschlag ("Ich bringe es mit" —
    die Mahlzeit legt danach die Essensplanung fest) oder, für Essensplaner, als
    Gericht, das die Organisation selbst stellt — optional gleich mit Mahlzeit.
  -->
  <AppModal ref="modal" class="food-event-suggest-modal" :title="modalTitle" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <AppSegmentedToggle
        v-if="canPlanSelected"
        v-model="form.mode"
        label="Wer bringt es mit?"
        :options="[
          { value: 'self', label: 'Ich' },
          { value: 'organization', label: 'Organisation' },
        ]"
      />

      <label class="field">
        Für welches Event? *
        <select v-model="form.eventId" required :disabled="eventLocked">
          <option v-for="event in availableEvents" :key="event.ID" :value="event.ID">
            {{ event.Title }}<template v-if="showOrg"> ({{ event.OrganizationTitle }})</template>
          </option>
        </select>
      </label>

      <label class="field">
        {{ isOrganization ? 'Welches Gericht? *' : 'Was könntest du mitbringen? *' }}
        <input v-model="form.title" type="text" placeholder="z.B. Nudelsalat" required>
      </label>

      <label class="field">
        Essenspräferenz
        <select v-model="form.preference">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">Vegetarisch</option>
          <option value="Vegan">Vegan</option>
        </select>
      </label>

      <label v-if="isOrganization" class="field">
        Mahlzeit
        <select v-model="form.mealId" :disabled="mealsLoading">
          <option :value="null">{{ mealsLoading ? 'Lade Mahlzeiten…' : '– später zuordnen –' }}</option>
          <optgroup v-for="day in mealDays" :key="day.date" :label="formatMealDayShort(day.date)">
            <option v-for="meal in day.meals" :key="meal.id" :value="meal.id">{{ meal.time }} {{ meal.title }}</option>
          </optgroup>
        </select>
      </label>

      <p class="field food-event-suggest-modal_hint">{{ hint }}</p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="submitting" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="submitting || !form.title.trim() || !form.eventId">
        {{ submitLabel }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { apiGet, apiPost } from '@utils/api'
import { formatMealDayShort } from '@utils/food'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'

const props = defineProps({
  // Events, für die man vorschlagen kann: [{ ID, Title, OrganizationID, OrganizationTitle }]
  events: { type: Array, default: () => [] },
  // Events, für die man als Essensplaner Gerichte der Organisation anlegen darf
  planEvents: { type: Array, default: () => [] },
})

const emit = defineEmits(['saved'])

const formId = `food-event-suggest-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const form = ref(emptyForm())
const eventLocked = ref(false)
const submitting = ref(false)
const error = ref(null)

const mealDays = ref([])
const mealsLoading = ref(false)

function emptyForm(eventId = null, mode = 'self') {
  return { mode, eventId, title: '', preference: 'None', mealId: null }
}

// Planer-Events sind eine Teilmenge; zusammengeführt, falls ein Planer-Event fehlt
const availableEvents = computed(() => {
  const byId = new Map(props.events.map(e => [e.ID, e]))
  for (const e of props.planEvents) if (!byId.has(e.ID)) byId.set(e.ID, e)
  return [...byId.values()]
})
const showOrg = computed(() => new Set(availableEvents.value.map(e => e.OrganizationID)).size > 1)

const canPlanSelected = computed(() => props.planEvents.some(e => e.ID === form.value.eventId))
const isOrganization = computed(() => form.value.mode === 'organization' && canPlanSelected.value)

const modalTitle = computed(() => (isOrganization.value ? 'Gericht hinzufügen' : 'Gericht vorschlagen'))
const submitLabel = computed(() => {
  if (submitting.value) return 'Wird gespeichert…'
  return isOrganization.value ? 'Hinzufügen' : 'Vorschlagen'
})
const hint = computed(() => {
  if (!isOrganization.value) {
    return 'Die Essensplanung ordnet deinen Vorschlag anschließend einer Mahlzeit zu. Du bekommst Bescheid, sobald das passiert ist.'
  }
  return form.value.mealId
    ? 'Die Organisation stellt das Gericht — es ist direkt für die gewählte Mahlzeit eingeplant.'
    : 'Die Organisation stellt das Gericht. Es landet bei den offenen Vorschlägen, bis du es einer Mahlzeit zuordnest.'
})

// Mahlzeiten des Events nur laden, wenn sie gebraucht werden
watch(() => [form.value.eventId, isOrganization.value], async ([eventId, org]) => {
  form.value.mealId = null
  mealDays.value = []
  if (!org || !eventId) return
  mealsLoading.value = true
  try {
    const response = await apiGet(`/food/planner/${eventId}`, false)
    if (form.value.eventId === eventId) mealDays.value = response?.days ?? []
  } catch {
    mealDays.value = []
  } finally {
    mealsLoading.value = false
  }
})

/**
 * @param {{ eventId?: number, mode?: 'self'|'organization', lockEvent?: boolean }} options
 */
function open({ eventId = null, mode = 'self', lockEvent = false } = {}) {
  form.value = emptyForm(eventId ?? availableEvents.value[0]?.ID ?? null, mode)
  eventLocked.value = lockEvent && !!eventId
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  submitting.value = true
  error.value = null
  try {
    const response = await apiPost(`/food/suggestEvent/${form.value.eventId}`, {
      title: form.value.title.trim(),
      preference: form.value.preference,
      asOrganization: isOrganization.value,
      mealId: isOrganization.value ? form.value.mealId : null,
    })
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gespeichert werden.'
      return
    }
    close()
    emit('saved', { asOrganization: isOrganization.value, food: response.data.food })
  } catch (err) {
    error.value = err.message || 'Gericht konnte nicht gespeichert werden.'
  } finally {
    submitting.value = false
  }
}

defineExpose({ open, close })
</script>
