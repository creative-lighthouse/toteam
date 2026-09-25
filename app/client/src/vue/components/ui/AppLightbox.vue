<!--
  Vollbild-Bildbetrachter mit Wechsel zwischen den Bildern (Pfeile, Pfeiltasten,
  Wischen). Als eigenes <dialog> umgesetzt, weil er auch aus Modals heraus
  geöffnet wird: <dialog>s liegen in der obersten Ebene ("top layer") — eine an
  <body> angehängte Lightbox (z.B. GLightbox) würde dahinter verschwinden.

    <AppLightbox ref="lightbox" />
    lightbox.value.open(images, index)   // images: [{ URL, Name? }]
-->
<template>
  <Teleport to="body">
    <dialog
      ref="dialogEl"
      class="app-lightbox"
      aria-label="Bildansicht"
      @cancel.prevent="close"
      @click.self="close"
      @keydown.left.prevent="prev"
      @keydown.right.prevent="next"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
      <template v-if="current">
        <img :key="current.URL" :src="current.URL" :alt="current.Name || ''" class="app-lightbox_image" @click.stop>

        <button type="button" class="app-lightbox_close" aria-label="Schließen" @click="close">✕</button>

        <template v-if="images.length > 1">
          <button type="button" class="app-lightbox_nav app-lightbox_nav--prev" aria-label="Vorheriges Bild" @click.stop="prev">‹</button>
          <button type="button" class="app-lightbox_nav app-lightbox_nav--next" aria-label="Nächstes Bild" @click.stop="next">›</button>
          <p class="app-lightbox_counter">{{ index + 1 }} / {{ images.length }}</p>
        </template>
      </template>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'

const dialogEl = ref(null)
const images = ref([])
const index = ref(0)

const current = computed(() => images.value[index.value] ?? null)

function open(list, startAt = 0) {
  if (!list?.length) return
  images.value = list
  index.value = Math.min(Math.max(startAt, 0), list.length - 1)
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
}

// Blättern läuft im Kreis (nach dem letzten wieder das erste Bild)
function prev() {
  if (images.value.length < 2) return
  index.value = (index.value - 1 + images.value.length) % images.value.length
}

function next() {
  if (images.value.length < 2) return
  index.value = (index.value + 1) % images.value.length
}

// Wischen: horizontale Bewegung ab 50px wechselt das Bild
let touchStartX = null
function onTouchStart(event) {
  touchStartX = event.changedTouches[0]?.clientX ?? null
}
function onTouchEnd(event) {
  if (touchStartX === null) return
  const delta = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX
  touchStartX = null
  if (Math.abs(delta) < 50) return
  if (delta > 0) prev()
  else next()
}

defineExpose({ open, close })
</script>
