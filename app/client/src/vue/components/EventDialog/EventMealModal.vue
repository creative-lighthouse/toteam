<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="event-meal-modal" @cancel.prevent="close">
      <div class="event-meal-modal_content" @click.stop>

        <div class="event-meal-modal_header">
          <h2>{{ isEdit ? 'Mahlzeit bearbeiten' : 'Mahlzeit hinzufügen' }}</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <form id="event-meal-form" class="event-meal-modal_body" @submit.prevent="submit">
          <div class="form-field">
            <label class="form-label" for="event-meal-title">Titel *</label>
            <input
              id="event-meal-title"
              v-model="form.title"
              type="text"
              class="input"
              placeholder="z.B. Mittagessen"
              maxlength="255"
              required
            >
          </div>

          <div class="form-field">
            <label class="form-label" for="event-meal-time">Uhrzeit *</label>
            <input id="event-meal-time" v-model="form.time" type="time" class="input" required>
          </div>

          <label class="form-checkbox">
            <input type="checkbox" v-model="form.acceptsContributions">
            Mitglieder dürfen Gerichte vorschlagen
          </label>

          <div v-if="error" class="event-meal-modal_error">{{ error }}</div>
        </form>

        <div class="event-meal-modal_actions">
          <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
          <AppButton type="submit" form="event-meal-form" variant="primary" :disabled="saving || !form.title || !form.time">
            {{ saving ? 'Speichern…' : 'Speichern' }}
          </AppButton>
          <AppButton v-if="isEdit" variant="danger" :disabled="saving" @click="remove">Löschen</AppButton>
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'

const props = defineProps({
  eventId: { type: Number, required: true },
})

const emit = defineEmits(['show-status'])
const eventsStore = useEventsStore()

const dialogEl = ref(null)
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
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
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
