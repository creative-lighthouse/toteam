<template>
  <AppModal ref="modal" class="event-meal-modal" :title="isEdit ? 'Mahlzeit bearbeiten' : 'Mahlzeit hinzufügen'" @close="close">
    <form id="event-meal-form" class="modalform" @submit.prevent="submit">
      <label class="field">
        Titel *
        <input v-model="form.title" type="text" placeholder="z.B. Mittagessen" maxlength="255" required>
      </label>

      <label class="field">
        Uhrzeit *
        <input v-model="form.time" type="time" required>
      </label>

      <AppToggle v-model="form.acceptsContributions" label="Mitglieder dürfen Gerichte vorschlagen" />

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="event-meal-form" variant="primary" :disabled="saving || !form.title || !form.time">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
      <AppButton v-if="isEdit" variant="danger" :disabled="saving" @click="remove">Löschen</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'
import AppToggle from '@components/AppToggle.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
})

const emit = defineEmits(['show-status'])
const eventsStore = useEventsStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)

// Wird direkt über das Argument von open() gesetzt statt über einen Prop:
// ein Prop, der von einer Klick-Handler-Funktion im selben Tick gesetzt wird,
// ist im Kind beim synchronen open()-Aufruf noch nicht aktualisiert (Vue
// patched Props erst beim nächsten Render), sonst greifen beim ersten Öffnen
// noch alte Werte.
const currentMeal = ref(null)
const isEdit = computed(() => !!currentMeal.value)

const defaultForm = () => ({ title: '', time: '', acceptsContributions: false })
const form = ref(defaultForm())

function open(meal = null) {
  currentMeal.value = meal
  form.value = meal
    ? { title: meal.Title, time: meal.RenderTime ?? '', acceptsContributions: !!meal.AcceptsContributions }
    : defaultForm()
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.value.title || !form.value.time || saving.value) return
  saving.value = true
  error.value = null
  try {
    if (isEdit.value) {
      await eventsStore.updateMeal(currentMeal.value.ID, form.value.title, form.value.time, form.value.acceptsContributions)
      emit('show-status', { text: 'Mahlzeit aktualisiert', type: 'success' })
    } else {
      await eventsStore.addMeal(props.eventId, form.value.title, form.value.time, form.value.acceptsContributions)
      emit('show-status', { text: 'Mahlzeit hinzugefügt', type: 'success' })
    }
    close()
  } catch (err) {
    console.error('Error saving meal:', err)
    error.value = 'Fehler beim Speichern'
    emit('show-status', { text: 'Fehler beim Speichern', type: 'error' })
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!currentMeal.value || saving.value) return
  if (!confirm('Diese Mahlzeit wirklich löschen?')) return
  saving.value = true
  try {
    await eventsStore.deleteMeal(currentMeal.value.ID)
    emit('show-status', { text: 'Mahlzeit gelöscht', type: 'success' })
    close()
  } catch (err) {
    console.error('Error deleting meal:', err)
    error.value = 'Fehler beim Löschen'
    emit('show-status', { text: 'Fehler beim Löschen', type: 'error' })
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
