<!--
  Große quadratische Kacheln der Foto-Galerie: höchstens vier nebeneinander, darunter
  Aufnahmedatum, Uhrzeit und Copyright, oben rechts auf jeder Kachel ein Download-Knopf.

    <PhotoGridLarge :photos="photos" :downloading="setOfIds" @open="…" @download="…" />
-->
<template>
  <ul class="photo-grid-large">
    <li v-for="photo in photos" :key="photo.ID" class="photo-grid-large_item">
      <div class="photo-grid-large_frame">
        <button
          type="button"
          class="photo-grid-large_open photo-grid_button"
          :data-photo-id="photo.ID"
          :aria-label="photoLabel(photo)"
          @click="emit('open', photo)"
        >
          <img v-if="photo.ThumbURL" :src="photo.ThumbURL" alt="" class="photo-grid-large_image" loading="lazy" decoding="async">
          <span v-else class="photo-grid-large_missing">Nicht verfügbar</span>
        </button>
        <PhotoDownloadButton
          class="photo-grid-large_download"
          overlay
          :busy="downloading.has(photo.ID)"
          :label="`${photoLabel(photo)} herunterladen`"
          @click="emit('download', photo)"
        />
      </div>
      <p class="photo-grid-large_date">
        <time :datetime="photo.TakenAt || photo.Created">{{ formatPhotoDateTime(photo.TakenAt || photo.Created) }}</time>
      </p>
      <p v-if="photo.Copyright" class="photo-grid-large_copyright">{{ formatCopyright(photo.Copyright) }}</p>
      <p v-if="photo.Title" class="photo-grid-large_title">{{ photo.Title }}</p>
    </li>
  </ul>
</template>

<script setup>
import PhotoDownloadButton from '@components/photos/PhotoDownloadButton.vue'
import { formatCopyright, formatPhotoDateTime } from '@utils/photoUpload'

defineProps({
  // Kurzform aus der API
  photos: { type: Array, required: true },
  // IDs der Fotos, die gerade heruntergeladen werden
  downloading: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['open', 'download'])

function photoLabel(photo) {
  return photo.Title || `Foto vom ${formatPhotoDateTime(photo.TakenAt || photo.Created)}`
}
</script>
