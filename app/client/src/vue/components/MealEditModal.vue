<template>
  <AppModal ref="modal" class="meal-edit-modal" title="Mahlzeit bearbeiten" @close="close">
    <form id="meal-edit-form" class="modalform" @submit.prevent="submit">
      <label class="field">
        Titel *
        <input
          id="meal-edit-title"
          v-model="form.title"
          type="text"
          placeholder="z.B. Mittagessen"
          maxlength="255"
          required
        >
      </label>

      <label class="field">
        Uhrzeit *
        <input id="meal-edit-time" v-model="form.time" type="time" required>
      </label>

      <label class="field">
        Beschreibung
        <textarea
          id="meal-edit-description"
          v-model="form.description"
          rows="4"
          placeholder="Beschreibung der Mahlzeit…"
        ></textarea>
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="meal-edit-form" variant="primary" :disabled="saving || !form.title.trim() || !form.time">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiPut } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const emit = defineEmits(['saved'])

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const mealId = ref(null)

const form = ref({ title: '', time: '', description: '' })

function open(meal) {
  mealId.value = meal.id
  form.value = {
    title: meal.title,
    time: meal.time,
    description: meal.description ?? '',
  }
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
    const title = form.value.title.trim()
    const time = form.value.time
    const description = form.value.description
    await apiPut(`/food/mealUpdate/${mealId.value}`, { title, time, description })
    emit('saved', { title, time, description })
    close()
  } catch (err) {
    console.error('Error saving meal:', err)
    error.value = 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
