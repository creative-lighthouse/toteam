<!--
  Große quadratische Kacheln der Foto-Galerie: höchstens vier nebeneinander, darunter
  Aufnahmedatum, Uhrzeit und Copyright, oben rechts auf jeder Kachel ein Download-Knopf.

    <PhotoGridLarge :photos="photos" :downloading="setOfIds" @open="…" @download="…" />

  Mit `selected` (Set der IDs) gibt es oben links ein Auswahlkästchen (sichtbar beim
  Darüberfahren, auf Touch-Geräten und sobald etwas ausgewählt ist) — nur bei Fotos, für die
  `can-select` true liefert; `select` meldet (photo, { shift }).
-->
<template>
  <ul class="photo-grid-large" :class="{ 'photo-grid-large--selecting': selected?.size }">
    <li
      v-for="photo in photos"
      :key="photo.ID"
      class="photo-grid-large_item"
      :class="{ 'photo-grid-large_item--selected': selected?.has(photo.ID) }"
    >
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
        <PhotoSelectCheckbox
          v-if="selected && canSelect(photo)"
          class="photo-grid-large_select"
          overlay
          :checked="selected.has(photo.ID)"
          :label="`${photoLabel(photo)} auswählen`"
          @toggle="emit('select', photo, $event)"
        />
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
import PhotoSelectCheckbox from '@components/photos/PhotoSelectCheckbox.vue'
import { formatCopyright, formatPhotoDateTime } from '@utils/photoUpload'

defineProps({
  // Kurzform aus der API
  photos: { type: Array, required: true },
  // IDs der Fotos, die gerade heruntergeladen werden
  downloading: { type: Set, default: () => new Set() },
  // Mehrfachauswahl: IDs der ausgewählten Fotos (null = ohne Auswahl)
  selected: { type: Set, default: null },
  canSelect: { type: Function, default: () => true },
})

const emit = defineEmits(['open', 'download', 'select'])

function photoLabel(photo) {
  return photo.Title || `Foto vom ${formatPhotoDateTime(photo.TakenAt || photo.Created)}`
}
</script>
