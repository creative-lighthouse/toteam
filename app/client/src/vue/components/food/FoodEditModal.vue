<template>
  <!-- Gericht im Essensplaner bearbeiten oder löschen -->
  <AppModal ref="modal" class="food-edit-modal" title="Gericht bearbeiten" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <AppTextField v-model="form.title" label="Gericht *" placeholder="z.B. Nudelsalat" required />

      <label class="field">
        Essenspräferenz
        <select v-model="form.preference">
          <option value="None">Keine Angabe</option>
          <option value="Vegetarian">Vegetarisch</option>
          <option value="Vegan">Vegan</option>
        </select>
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppIconButton
        variant="danger"
        class="food-edit-modal_delete"
        aria-label="Gericht löschen"
        title="Löschen"
        :disabled="saving"
        @click="remove"
      >
        <span class="icon-mask" :style="trashIconStyle" />
      </AppIconButton>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !form.title.trim()">
        {{ saving ? 'Speichern…' : 'Speichern' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiPut, apiDelete } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppTextField from '@components/ui/AppTextField.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const emit = defineEmits(['saved', 'deleted'])

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

const formId = `food-edit-form-${Math.random().toString(36).slice(2)}`
const modal = ref(null)
const foodId = ref(null)
const form = ref({ title: '', preference: 'None' })
const saving = ref(false)
const error = ref(null)

/** @param {{ id: number, title: string, preference: string }} food */
function open(food) {
  foodId.value = food.id
  form.value = { title: food.title ?? '', preference: food.preference ?? 'None' }
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  saving.value = true
  error.value = null
  try {
    const response = await apiPut(`/food/plannerFood/${foodId.value}`, {
      title: form.value.title.trim(),
      preference: form.value.preference,
    })
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gespeichert werden.'
      return
    }
    close()
    emit('saved', response.data.food)
  } catch (err) {
    error.value = err.message || 'Gericht konnte nicht gespeichert werden.'
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirm(`„${form.value.title}“ wirklich löschen?`)) return
  saving.value = true
  error.value = null
  try {
    const response = await apiDelete(`/food/plannerFood/${foodId.value}`)
    if (!response?.success) {
      error.value = response?.error || 'Gericht konnte nicht gelöscht werden.'
      return
    }
    close()
    emit('deleted', foodId.value)
  } catch (err) {
    error.value = err.message || 'Gericht konnte nicht gelöscht werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
