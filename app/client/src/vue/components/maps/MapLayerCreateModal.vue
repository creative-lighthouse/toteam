<template>
  <!-- Neue Ebene eines Lageplans: Titel und Farbe. Das Anlegen selbst übernimmt die
       Seite (Funktion an open() übergeben) — Fehler werden hier im Modal angezeigt. -->
  <AppModal ref="modal" class="map-layer-create-modal" title="Neue Ebene" @close="close">
    <form :id="formId" class="modalform" @submit.prevent="submit">
      <label class="field">
        Titel *
        <input v-model="form.title" type="text" maxlength="255" placeholder="z.B. Lichtplan, Fluchtwege" required autofocus>
      </label>

      <div class="field">
        <label :for="`${formId}-color`">Farbe</label>
        <div class="color-picker-wrapper">
          <input :id="`${formId}-color`" v-model="form.layerColor" type="color">
          <input v-model="form.layerColor" type="text" aria-label="Farbe als Hex-Wert" autocomplete="off">
        </div>
        <span class="map-layer-create-modal_hint">Die Marker der Ebene bekommen diese Farbe.</span>
      </div>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" :form="formId" variant="primary" :disabled="saving || !form.title.trim()">
        {{ saving ? 'Wird angelegt…' : 'Anlegen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive } from 'vue'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const formId = `map-layer-create-${Math.random().toString(36).slice(2)}`
const HEX = /^#[0-9a-f]{6}$/i

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const form = reactive({ title: '', layerColor: '#999999' })
let onSubmit = null

/** @param {(values: { title: string, layerColor: string }) => Promise<void>} submitFn legt die Ebene an */
function open(submitFn) {
  onSubmit = submitFn
  Object.assign(form, { title: '', layerColor: '#999999' })
  error.value = null
  saving.value = false
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!form.title.trim() || saving.value) return
  if (!HEX.test(form.layerColor.trim())) {
    error.value = 'Bitte eine Farbe als Hex-Wert angeben, z.B. #e74c3c.'
    return
  }
  saving.value = true
  error.value = null
  try {
    await onSubmit({ title: form.title.trim(), layerColor: form.layerColor.trim() })
    close()
  } catch (err) {
    error.value = err.message || 'Ebene konnte nicht angelegt werden.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
