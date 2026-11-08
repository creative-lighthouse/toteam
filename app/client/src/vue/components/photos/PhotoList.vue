<!--
  Listenansicht der Foto-Galerie: ein Foto pro Zeile — kleines Vorschaubild, daneben Titel,
  Aufnahmezeit und die Tags (Personen, Charaktere, Schlagworte), ganz rechts der Download.

    <PhotoList :photos="photos" :downloading="setOfIds" @open="…" @download="…" />
-->
<template>
  <ul class="photo-list">
    <li v-for="photo in photos" :key="photo.ID" class="photo-list_row">
      <button
        type="button"
        class="photo-list_open photo-grid_button"
        :data-photo-id="photo.ID"
        :aria-label="photoLabel(photo)"
        @click="emit('open', photo)"
      >
        <span class="photo-list_thumb">
          <img v-if="photo.ThumbURL" :src="photo.ThumbURL" alt="" loading="lazy" decoding="async">
        </span>
        <span class="photo-list_text">
          <span class="photo-list_title">{{ photo.Title || formatPhotoDateTime(photo.TakenAt || photo.Created) }}</span>
          <span v-if="photo.Title || photo.Copyright" class="photo-list_meta">
            <template v-if="photo.Title">{{ formatPhotoDateTime(photo.TakenAt || photo.Created) }}</template>
            <template v-if="photo.Title && photo.Copyright"> · </template>
            <template v-if="photo.Copyright">{{ formatCopyright(photo.Copyright) }}</template>
          </span>

          <span v-if="hasTags(photo)" class="photo-list_tags">
            <span v-for="name in photo.PeopleNames" :key="`p-${name}`" class="photo-list_tag photo-list_tag--person">{{ name }}</span>
            <span v-for="name in photo.CharacterNames" :key="`c-${name}`" class="photo-list_tag photo-list_tag--character">{{ name }}</span>
            <span v-for="name in photo.RoomNames" :key="`r-${name}`" class="photo-list_tag photo-list_tag--room">{{ name }}</span>
            <span v-for="name in photo.TagNames" :key="`t-${name}`" class="photo-list_tag">{{ name }}</span>
          </span>
          <span v-else class="photo-list_meta photo-list_meta--empty">Noch nicht getaggt</span>
        </span>
      </button>

      <PhotoDownloadButton
        class="photo-list_download"
        :busy="downloading.has(photo.ID)"
        :label="`${photoLabel(photo)} herunterladen`"
        @click="emit('download', photo)"
      />
    </li>
  </ul>
</template>

<script setup>
import PhotoDownloadButton from '@components/photos/PhotoDownloadButton.vue'
import { formatCopyright, formatPhotoDateTime } from '@utils/photoUpload'

defineProps({
  // Kurzform aus der API inkl. PeopleNames, CharacterNames, RoomNames, TagNames
  photos: { type: Array, required: true },
  // IDs der Fotos, die gerade heruntergeladen werden
  downloading: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['open', 'download'])

function photoLabel(photo) {
  return photo.Title || `Foto vom ${formatPhotoDateTime(photo.TakenAt || photo.Created)}`
}

function hasTags(photo) {
  return !!(photo.PeopleNames?.length || photo.CharacterNames?.length || photo.RoomNames?.length || photo.TagNames?.length)
}
</script>
