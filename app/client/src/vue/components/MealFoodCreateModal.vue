<template>
  <AppModal ref="modal" class="meal-food-create-modal" title="Gericht hinzufügen" @close="close">
    <form id="meal-food-create-form" class="modalform" @submit.prevent="submit">
      <label class="field">
        Bezeichnung *
        <input
          id="meal-food-create-title"
          v-model="form.title"
          type="text"
          placeholder="z.B. Nudelsalat"
          required
        >
      </label>

      <AppToggle v-model="form.isOrderable" label="Bestellbar (Menge pro Person begrenzbar)" />

      <label v-if="form.isOrderable" class="field">
        Max. pro Person
        <input
          id="meal-food-create-max"
          v-model.number="form.maxQuantity"
          type="number"
          min="0"
          placeholder="0"
        >
        <small class="meal-food-create-modal_hint">0 = unbegrenzt</small>
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="meal-food-create-form" variant="primary" :disabled="saving || !form.title.trim()">
        {{ saving ? 'Speichern…' : 'Hinzufügen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiPost } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'
import AppToggle from '@components/AppToggle.vue'

const emit = defineEmits(['created'])

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const mealId = ref(null)

const defaultForm = () => ({ title: '', isOrderable: false, maxQuantity: 0 })
const form = ref(defaultForm())

function open(id) {
  mealId.value = id
  form.value = defaultForm()
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.value.title.trim() || saving.value) return
  saving.value = true
  error.value = null
  try {
    const response = await apiPost(`/food/mealProduct/${mealId.value}`, {
      title: form.value.title.trim(),
      isOrderable: form.value.isOrderable,
      maxQuantity: form.value.isOrderable ? (form.value.maxQuantity || 0) : 0,
    })
    if (response?.success && response.data?.product) {
      emit('created', response.data.product)
      close()
    } else {
      error.value = response?.error || 'Fehler beim Hinzufügen des Gerichts.'
    }
  } catch (err) {
    error.value = err.message || 'Fehler beim Hinzufügen des Gerichts.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
