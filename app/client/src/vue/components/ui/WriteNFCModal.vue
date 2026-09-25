<!--
  Schreibt einen Link auf einen NFC-Tag (Web NFC, z.B. um Geräte zu taggen).
  Web NFC gibt es nur in Chrome auf Android — iOS erlaubt das Schreiben aus dem
  Browser nicht. Aufrufer blenden den Auslöser daher mit `isNfcWriteSupported()`
  aus. Schreiben muss durch einen Klick ausgelöst werden (Browser-Vorgabe).

    <WriteNFCModal ref="nfcModal" />
    nfcModal.value.open({ title: 'LED-Scheinwerfer', url: 'https://…' })
-->
<template>
  <AppModal ref="modal" class="write-nfc-modal" title="NFC-Tag beschreiben" @close="close">
    <p class="write-nfc-modal_intro">
      Schreibt den Link zu <strong>{{ title }}</strong> auf einen NFC-Tag. Wer den Tag später mit dem Handy antippt, landet direkt auf der Seite.
    </p>
    <p class="write-nfc-modal_url">{{ url }}</p>

    <div class="write-nfc-modal_status" :class="`write-nfc-modal_status--${state}`" role="status">
      <span class="icon-mask write-nfc-modal_icon" :style="nfcIconStyle" />
      <p>{{ statusText }}</p>
    </div>

    <template #actions>
      <AppButton variant="secondary" @click="close">{{ state === 'success' ? 'Fertig' : 'Abbrechen' }}</AppButton>
      <AppButton v-if="state !== 'writing'" variant="primary" @click="write">
        {{ state === 'success' ? 'Weiteren Tag beschreiben' : (state === 'error' ? 'Erneut versuchen' : 'Schreiben starten') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script>
/** Ob der Browser NFC-Tags beschreiben kann (Web NFC, derzeit nur Chrome auf Android) */
export function isNfcWriteSupported() {
  return typeof window !== 'undefined' && 'NDEFReader' in window && window.isSecureContext
}
</script>

<script setup>
import { ref, computed } from 'vue'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import actionNfc from '../../../../icons/actions/action_nfc.svg'

const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }

const modal = ref(null)
const title = ref('')
const url = ref('')
// idle | writing | success | error
const state = ref('idle')
const errorText = ref('')
let abortController = null

const statusText = computed(() => {
  switch (state.value) {
    case 'writing': return 'Halte den NFC-Tag jetzt an die Rückseite deines Handys …'
    case 'success': return 'Fertig! Der Link wurde auf den Tag geschrieben.'
    case 'error':   return errorText.value
    default:        return 'Tippe auf „Schreiben starten“ und halte dann den Tag an dein Handy.'
  }
})

function open(options) {
  title.value = options.title || ''
  url.value = options.url
  state.value = 'idle'
  errorText.value = ''
  modal.value?.open()
}

function stopWriting() {
  abortController?.abort()
  abortController = null
}

function close() {
  stopWriting()
  modal.value?.close()
}

function describeError(err) {
  switch (err?.name) {
    case 'NotAllowedError':   return 'NFC-Zugriff wurde nicht erlaubt. Bitte die Berechtigung erteilen und NFC in den Einstellungen einschalten.'
    case 'NotSupportedError': return 'Dein Gerät unterstützt das Beschreiben von NFC-Tags nicht.'
    case 'NotReadableError':  return 'Der Tag konnte nicht gelesen werden. Bitte noch einmal ruhig ans Handy halten.'
    case 'NetworkError':      return 'Die Verbindung zum Tag ist abgebrochen. Bitte erneut versuchen.'
    default:                  return 'Der Tag konnte nicht beschrieben werden' + (err?.message ? `: ${err.message}` : '.')
  }
}

async function write() {
  stopWriting()
  abortController = new AbortController()
  state.value = 'writing'
  try {
    // eslint-disable-next-line no-undef
    const reader = new NDEFReader()
    await reader.write(
      { records: [{ recordType: 'url', data: url.value }] },
      { signal: abortController.signal, overwrite: true }
    )
    state.value = 'success'
  } catch (err) {
    // Abbruch durch Schließen des Modals ist kein Fehler
    if (err?.name === 'AbortError') return
    errorText.value = describeError(err)
    state.value = 'error'
  } finally {
    abortController = null
  }
}

defineExpose({ open, close })
</script>
