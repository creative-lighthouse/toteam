<template>
  <AppModal ref="modal" class="food-suggest-modal" title="Gericht vorschlagen" @close="close">
    <form id="food-suggest-form" class="modalform" @submit.prevent="submit">
      <label class="field">
        Name des Gerichts *
        <input
          id="food-suggest-title"
          v-model="form.title"
          type="text"
          placeholder="z.B. Nudelsalat"
          required
        >
      </label>

      <label class="field">
        Essenspräferenz
        <select id="food-suggest-pref" v-model="form.preference">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">Vegetarisch</option>
          <option value="Vegan">Vegan</option>
        </select>
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="submitting" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="food-suggest-form" variant="primary" :disabled="submitting || !form.title.trim()">
        {{ submitting ? 'Wird eingereicht…' : 'Vorschlagen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiPost } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

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
