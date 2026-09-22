<template>
  <AppModal ref="modal" class="suggest-food-modal" title="Gericht vorschlagen" @close="close">
    <form id="suggest-food-form" @submit.prevent="submit">
      <div class="form-field">
        <label class="form-label" for="suggest-food-title">Name des Gerichts *</label>
        <input
          id="suggest-food-title"
          v-model="form.title"
          type="text"
          class="input"
          placeholder="z.B. Nudelsalat"
          required
        >
      </div>

      <div class="form-field">
        <label class="form-label" for="suggest-food-pref">Essenspräferenz</label>
        <select id="suggest-food-pref" v-model="form.preference" class="input">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">🥗 Vegetarisch</option>
          <option value="Vegan">🌱 Vegan</option>
        </select>
      </div>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="submitting" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="suggest-food-form" variant="primary" :disabled="submitting || !form.title.trim()">
        {{ submitting ? 'Wird eingereicht…' : 'Vorschlagen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiPost } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const emit = defineEmits(['suggested'])

const modal = ref(null)
const submitting = ref(false)
const error = ref(null)
const mealId = ref(null)

const form = ref({ title: '', preference: 'None' })

function open(id) {
  mealId.value = id
  form.value = { title: '', preference: 'None' }
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.value.title.trim() || submitting.value) return
  submitting.value = true
  error.value = null
  try {
    const title = form.value.title.trim()
    const preference = form.value.preference
    const result = await apiPost(`/food/suggest/${mealId.value}`, { title, preference })
    emit('suggested', { mealId: mealId.value, food: result.data.food })
    close()
  } catch (err) {
    console.error('Error suggesting food:', err)
    error.value = 'Fehler beim Vorschlagen'
  } finally {
    submitting.value = false
  }
}

defineExpose({ open, close })
</script>
