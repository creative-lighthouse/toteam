<template>
  <!-- Fotos-Karte auf der Event-Seite: die neuesten Fotos, Klick öffnet sie im Foto-Totem -->
  <div class="org-event-photos">
    <PhotoGrid :photos="photos" compact @open="openPhoto" />
    <AppButton variant="secondary" size="small" class="org-event-photos_all" :to="allLink">
      {{ total === 1 ? 'Im Foto-Totem ansehen' : `Alle ${total} Fotos ansehen` }}
    </AppButton>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@components/ui/AppButton.vue'
import PhotoGrid from '@components/photos/PhotoGrid.vue'

const props = defineProps({
  // Das Event ({ ID, OrganizationID })
  event: { type: Object, required: true },
  // Kurzform aus GET /photos/eventPhotos/{id}
  photos: { type: Array, required: true },
  total: { type: Number, required: true },
})

const router = useRouter()

const allLink = computed(() => ({
  name: 'Photos',
  query: { organization: props.event.OrganizationID, event: props.event.ID },
}))

function openPhoto(photo) {
  router.push({ ...allLink.value, query: { ...allLink.value.query, photo: photo.ID } })
}
</script>
