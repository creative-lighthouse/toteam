<!--
  Liest NFC-Tags, solange das Modal offen ist (Web NFC — nur Chrome auf Android;
  Auslöser mit isNfcSupported() aus @utils/nfc ausblenden). Meldet den gelesenen
  Link per `scanned` (null, wenn der Tag keinen Link enthält); der Aufrufer
  entscheidet, was passiert — bei Erfolg `close()`, sonst `showError(text)`,
  dann bleibt das Modal offen und scannt weiter.

    <ScanNFCModal ref="scanModal" @scanned="onScanned" />
    scanModal.value.open()   // aus einem Klick heraus (Browser fragt nach Erlaubnis)
-->
<template>
  <AppModal ref="modal" class="scan-nfc-modal" title="NFC-Tag scannen" @close="close">
    <div class="scan-nfc-modal_status" :class="`scan-nfc-modal_status--${status}`" role="status">
      <span class="icon-mask scan-nfc-modal_icon" :style="nfcIconStyle" />
      <p>{{ statusText }}</p>
    </div>

    <template #actions>
      <AppButton variant="secondary" @click="close">Abbrechen</AppButton>
      <AppButton v-if="!scanner.active.value" variant="primary" @click="start">Scan starten</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useNfcScanner } from '@utils/nfc'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import actionNfc from '../../../../icons/actions/action_nfc.svg'

const emit = defineEmits(['scanned'])

const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }

const modal = ref(null)
const message = ref('')
const busy = ref(false)

const scanner = useNfcScanner(url => {
  message.value = ''
  busy.value = true
  emit('scanned', url)
})

// idle | scanning | busy | error
const status = computed(() => {
  if (busy.value) return 'busy'
  if (message.value || scanner.error.value) return 'error'
  return scanner.active.value ? 'scanning' : 'idle'
})

const statusText = computed(() => {
  switch (status.value) {
    case 'busy':     return 'Tag erkannt – wird geöffnet …'
    case 'error':    return (message.value || scanner.error.value) + (scanner.active.value ? ' Halte einen anderen Tag ans Handy.' : '')
    case 'scanning': return 'Halte einen ToTeam-Tag an die Rückseite deines Handys.'
    default:         return 'Tippe auf „Scan starten“, um NFC-Tags zu lesen.'
  }
})

function start() {
  message.value = ''
  scanner.start()
}

function open() {
  message.value = ''
  busy.value = false
  modal.value?.open()
  // Direkt aus dem öffnenden Klick heraus starten — der Browser verlangt eine Nutzeraktion
  scanner.start()
}

function close() {
  scanner.stop()
  busy.value = false
  modal.value?.close()
}

/** Fehler anzeigen und weiter scannen (z.B. kein ToTeam-Link oder kein Zugriff) */
function showError(text) {
  busy.value = false
  message.value = text
}

defineExpose({ open, close, showError })
</script>
