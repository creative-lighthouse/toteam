<!--
  Öffentlichen Teilen-Link anzeigen (QR-Code, Teilen/Kopieren, Deaktivieren).
  Die API-Anfrage kommt vom Aufrufer, dadurch für Objekte und Räume nutzbar:

    shareModal.value.open({
      title: 'LED-Scheinwerfer',
      heading: 'Objekt teilen',
      request: revoke => store.shareItem(item.ID, revoke),  // → { success, data: { url } }
    })
-->
<template>
  <AppModal ref="modal" class="share-link-modal" :title="heading" @close="close">
    <p class="share-link-modal_hint">
      Über diesen Link kann jede Person – auch ohne ToTeam-Konto – die Stammdaten, Bilder und die als „öffentlich“ markierten Felder von
      <strong>{{ title }}</strong> sehen. Angemeldete Mitglieder sehen alles und können es bearbeiten, ausleihen bzw. reservieren.
    </p>

    <div v-if="loading" class="share-link-modal_loading">Link wird erstellt…</div>
    <template v-else-if="url">
      <canvas ref="canvasEl" class="share-link-modal_canvas"></canvas>
      <p class="share-link-modal_url">{{ url }}</p>
    </template>

    <div v-if="error" class="app-modal_error">{{ error }}</div>

    <template v-if="url" #actions>
      <AppButton variant="danger" class="share-link-modal_revoke" :disabled="busy" @click="revoke">Link deaktivieren</AppButton>
      <AppButton variant="primary" :disabled="busy" @click="share">{{ shareLabel }}</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import QRCode from 'qrcode'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const emit = defineEmits(['changed'])

const modal = ref(null)
const canvasEl = ref(null)
const title = ref('')
const heading = ref('Teilen')
let request = null
const url = ref(null)
const loading = ref(false)
const busy = ref(false)
const error = ref(null)
const shareLabel = ref('Teilen')

/**
 * @param {{ title: string, heading?: string, request: (revoke: boolean) => Promise<object> }} options
 */
async function open(options) {
  title.value = options.title || ''
  heading.value = options.heading || 'Teilen'
  request = options.request
  url.value = null
  error.value = null
  shareLabel.value = navigator.share ? 'Teilen' : 'Link kopieren'
  modal.value?.open()

  loading.value = true
  try {
    const response = await request(false)
    if (!response.success) {
      error.value = response.error || 'Link konnte nicht erstellt werden.'
      return
    }
    url.value = response.data.url
    emit('changed', url.value)
  } finally {
    loading.value = false
  }

  await nextTick()
  QRCode.toCanvas(canvasEl.value, url.value, {
    width: 240,
    margin: 2,
    color: { dark: '#000000', light: '#ffffff' },
  })
}

function close() {
  modal.value?.close()
}

async function share() {
  if (navigator.share) {
    try {
      await navigator.share({ title: title.value, url: url.value })
    } catch {
      // Teilen abgebrochen
    }
    return
  }
  await navigator.clipboard.writeText(url.value)
  shareLabel.value = 'Link kopiert!'
  setTimeout(() => { shareLabel.value = 'Link kopieren' }, 2000)
}

async function revoke() {
  if (!confirm('Link deaktivieren? Bereits verteilte Links und QR-Codes funktionieren danach nicht mehr.')) return
  busy.value = true
  try {
    const response = await request(true)
    if (response.success) {
      emit('changed', null)
      close()
    } else {
      error.value = response.error || 'Link konnte nicht deaktiviert werden.'
    }
  } finally {
    busy.value = false
  }
}

defineExpose({ open, close })
</script>
