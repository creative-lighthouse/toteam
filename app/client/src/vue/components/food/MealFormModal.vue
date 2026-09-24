<!--
  Mahlzeit anlegen, bearbeiten und (optional) löschen — genutzt im Termin-Dialog
  und auf der Mahlzeit-Detailseite.

    <MealFormModal ref="mealModal" :appointment-id="event.ID" deletable @saved="…" @deleted="…" />
    mealModal.value.open()                         // neue Mahlzeit für appointmentId
    mealModal.value.open({ id, title, time, description, acceptsContributions })  // bearbeiten

  Speichert über den Events-Store (hält den Kalender synchron) und meldet das
  Ergebnis im selben Format zurück: saved({ id, title, time, description, acceptsContributions }).
-->
<template>
  <AppModal ref="modal" class="meal-form-modal" :title="isEdit ? 'Mahlzeit bearbeiten' : 'Mahlzeit hinzufügen'" @close="close">
    <form id="meal-form" class="modalform" @submit.prevent="submit">
      <label class="field">
        Titel *
        <input v-model="form.title" type="text" placeholder="z.B. Mittagessen" maxlength="255" required>
      </label>

      <label class="field">
        Uhrzeit *
        <input v-model="form.time" type="time" required>
      </label>

      <label class="field">
        Beschreibung
        <textarea v-model="form.description" rows="3" placeholder="Beschreibung der Mahlzeit…"></textarea>
      </label>

      <AppToggle v-model="form.acceptsContributions" label="Mitglieder dürfen Gerichte vorschlagen" />

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton v-if="isEdit && deletable" variant="danger" :disabled="saving" @click="remove">Löschen</AppButton>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="meal-form" variant="primary" :disabled="saving || !form.title.trim() || !form.time">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'

const props = defineProps({
  // Termin, zu dem neue Mahlzeiten angelegt werden (nur fürs Anlegen nötig)
  appointmentId: { type: Number, default: null },
  // Zeigt beim Bearbeiten einen Löschen-Button
  deletable: { type: Boolean, default: false },
})

const emit = defineEmits(['saved', 'deleted'])
const eventsStore = useEventsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

// Wird direkt über das Argument von open() gesetzt statt über einen Prop:
// ein Prop, der von einer Klick-Handler-Funktion im selben Tick gesetzt wird,
// ist im Kind beim synchronen open()-Aufruf noch nicht aktualisiert.
const mealId = ref(null)
const isEdit = computed(() => mealId.value !== null)

const defaultForm = () => ({ title: '', time: '', description: '', acceptsContributions: false })
const form = ref(defaultForm())

function open(meal = null) {
  mealId.value = meal?.id ?? null
  form.value = meal
    ? {
        title: meal.title ?? '',
        time: meal.time ?? '',
        description: meal.description ?? '',
        acceptsContributions: !!meal.acceptsContributions,
      }
    : defaultForm()
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.value.title.trim() || !form.value.time || saving.value) return
  saving.value = true
  error.value = null
  try {
    const data = { ...form.value, title: form.value.title.trim() }
    const result = isEdit.value
      ? await eventsStore.updateMeal(mealId.value, data)
      : await eventsStore.addMeal(props.appointmentId, data)
    emit('saved', {
      id: result.ID,
      title: result.Title,
      time: result.RenderTime,
      description: result.Description ?? '',
      acceptsContributions: !!result.AcceptsContributions,
      isNew: !isEdit.value,
    })
    close()
  } catch (err) {
    error.value = err.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!isEdit.value || saving.value) return
  if (!confirm('Diese Mahlzeit wirklich löschen?')) return
  saving.value = true
  error.value = null
  try {
    await eventsStore.deleteMeal(mealId.value)
    emit('deleted', mealId.value)
    close()
  } catch (err) {
    error.value = err.message || 'Fehler beim Löschen'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
