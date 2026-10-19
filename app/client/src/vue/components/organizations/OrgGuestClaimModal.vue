<template>
  <!-- Beim Beitritt (bzw. Annehmen einer Einladung): "Bist du einer dieser Gäste?" — die
       Auswahl wird nur vorgemerkt, ein Admin bestätigt die Übernahme in "Organisation verwalten".
       Nutzung: const guestId = await claimModal.value.ask(org)
       → Gast-ID, null (keiner davon) oder undefined (abgebrochen). Ohne Gäste sofort null. -->
  <AppModal ref="modal" class="org-guest-claim-modal" title="Bist du schon als Gast dabei?" @close="finish(undefined)">
    <div class="modalform">
      <p class="field org-guest-claim-modal_hint">
        In „{{ orgTitle }}“ gibt es Gäste ohne eigenen Account. Bist du eine dieser Personen?
        Dann übernimmst du ihre Zusagen und Daten, sobald ein Admin der Organisation das bestätigt.
      </p>

      <div class="field org-guest-claim-modal_options" role="radiogroup" aria-label="Gast auswählen">
        <label v-for="guest in guests" :key="guest.ID" class="org-guest-claim-modal_option">
          <input v-model="selected" type="radio" name="org-guest-claim" :value="guest.ID">
          <span>{{ guest.Label }}</span>
        </label>
        <label class="org-guest-claim-modal_option org-guest-claim-modal_option--none">
          <input v-model="selected" type="radio" name="org-guest-claim" :value="null">
          <span>Nein, ich bin keiner davon</span>
        </label>
      </div>
    </div>

    <template #actions>
      <AppButton variant="secondary" @click="finish(undefined)">Abbrechen</AppButton>
      <AppButton variant="primary" @click="finish(selected)">Weiter</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { apiGet } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const modal = ref(null)
const orgTitle = ref('')
const guests = ref([])
const selected = ref(null)
let resolver = null

/** @param {{ ID: number, Title: string }} org */
async function ask(org) {
  try {
    const response = await apiGet(`/organizations/guests/${org.ID}`, false)
    guests.value = response.guests || []
  } catch {
    guests.value = []
  }
  if (!guests.value.length) return null

  orgTitle.value = org.Title
  selected.value = null
  modal.value?.open()
  return new Promise(resolve => {
    resolver = resolve
  })
}

function finish(value) {
  const resolve = resolver
  resolver = null
  modal.value?.close()
  resolve?.(value)
}

defineExpose({ ask })
</script>
