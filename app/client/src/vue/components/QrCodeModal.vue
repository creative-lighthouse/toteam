<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="qrcode-modal" @cancel.prevent="close">
      <div class="qrcode-modal_content" @click.stop>
        <div class="qrcode-modal_header">
          <h2 class="hl2 qrcode-modal_title">Profil-QR-Code</h2>
          <button class="button--close" aria-label="Schließen" @click="close">✕</button>
        </div>
        <div class="qrcode-modal_body">
          <canvas ref="canvasEl" class="qrcode-modal_canvas"></canvas>
          <p class="qrcode-modal_url">{{ profileUrl }}</p>
          <button class="button" @click="share">{{ shareLabel }}</button>
        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  username: { type: String, required: true }
})

const dialogEl  = ref(null)
const canvasEl  = ref(null)
const shareLabel = ref('Teilen')

const profileUrl = `${window.location.origin}/app/profile/${encodeURIComponent(props.username)}`

async function open() {
  shareLabel.value = 'Teilen'
  dialogEl.value?.showModal()
  await nextTick()
  QRCode.toCanvas(canvasEl.value, profileUrl, {
    width: 240,
    margin: 2,
    color: { dark: '#000000', light: '#ffffff' },
  })
}

function close() {
  dialogEl.value?.close()
}

async function share() {
  if (navigator.share) {
    await navigator.share({ title: `Profil von ${props.username}`, url: profileUrl })
  } else {
    await navigator.clipboard.writeText(profileUrl)
    shareLabel.value = 'Link kopiert!'
    setTimeout(() => { shareLabel.value = 'Teilen' }, 2000)
  }
}

defineExpose({ open })
</script>
