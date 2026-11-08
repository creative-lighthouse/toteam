<!--
  Raster quadratischer Vorschaubilder. Klick (oder Enter) öffnet das Foto.

    <PhotoGrid :photos="photos" @open="openDetail" />
-->
<template>
  <ul class="photo-grid" :class="{ 'photo-grid--compact': compact }">
    <li v-for="photo in photos" :key="photo.ID" class="photo-grid_item">
      <button
        type="button"
        class="photo-grid_button"
        :data-photo-id="photo.ID"
        :aria-label="photo.Title || `Foto vom ${formatDate(photo.TakenAt || photo.Created)}`"
        @click="emit('open', photo)"
      >
        <img
          v-if="photo.ThumbURL"
          :src="photo.ThumbURL"
          alt=""
          class="photo-grid_image"
          loading="lazy"
          decoding="async"
        >
        <span v-else class="photo-grid_missing">Nicht verfügbar</span>
      </button>
    </li>
  </ul>
</template>

<script setup>
defineProps({
  // Kurzform aus der API: ID, Title, ThumbURL, TakenAt, Created
  photos: { type: Array, required: true },
  // Kleinere Kacheln (z.B. Karte auf der Event-Seite)
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])

function formatDate(value) {
  if (!value) return ''
  return new Date(value.replace(' ', 'T')).toLocaleDateString('de-DE')
}
</script>
