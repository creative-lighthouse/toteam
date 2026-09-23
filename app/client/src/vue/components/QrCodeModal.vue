<template>
  <AppModal ref="modal" class="qrcode-modal" title="Profil-QR-Code" @close="close">
    <canvas ref="canvasEl" class="qrcode-modal_canvas"></canvas>
    <p class="qrcode-modal_url">{{ profileUrl }}</p>
    <AppButton variant="primary" @click="share">{{ shareLabel }}</AppButton>
  </AppModal>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import QRCode from 'qrcode'
import AppButton from '@components/AppButton.vue'
import AppModal from '@components/AppModal.vue'

const props = defineProps({
  username: { type: String, required: true }
})

const modal     = ref(null)
const canvasEl  = ref(null)
const shareLabel = ref('Teilen')

const profileUrl = `${window.location.origin}/app/profile/${encodeURIComponent(props.username)}`

async function open() {
  shareLabel.value = 'Teilen'
  modal.value?.open()
  await nextTick()
  QRCode.toCanvas(canvasEl.value, profileUrl, {
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
    await navigator.share({ title: `Profil von ${props.username}`, url: profileUrl })
  } else {
    await navigator.clipboard.writeText(profileUrl)
    shareLabel.value = 'Link kopiert!'
    setTimeout(() => { shareLabel.value = 'Teilen' }, 2000)
  }
}

defineExpose({ open })
</script>
