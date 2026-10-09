<template>
  <!-- Event im Feed teilen: kurzer Text (optional), wer postet und wer es sieht, ab wann —
       dasselbe Formular wie im Feed, nur mit ausgeklappten Einstellungen -->
  <AppModal ref="modal" class="org-event-feed-share-modal" title="Im Feed teilen" @close="close">
    <OrgEventCard v-if="event" :event="event" wide class="org-event-feed-share-modal_preview" />
    <p v-if="event && !event.IsPublic" class="org-event-feed-share-modal_hint">
      Dieses Event ist intern — es lässt sich nur intern für {{ event.OrganizationTitle }} teilen.
    </p>
    <FeedComposer v-if="event && ready" :key="composerKey" :event="event" inline @posted="onPosted" />
  </AppModal>
</template>

<script setup>
import { ref } from 'vue'
import { useAnnouncementsStore } from '@stores/announcements'
import AppModal from '@components/ui/AppModal.vue'
import FeedComposer from '@components/announcements/FeedComposer.vue'
import OrgEventCard from '@components/events/OrgEventCard.vue'

defineProps({
  // Event der Event-Seite (Summary bzw. öffentliche Daten)
  event: { type: Object, default: null },
})

const emit = defineEmits(['shared'])
const store = useAnnouncementsStore()

const modal = ref(null)
// Das Formular braucht die Auswahl an Organisationen aus dem Feed
const ready = ref(false)
// Bei jedem Öffnen ein frisches Formular
const composerKey = ref(0)

async function open() {
  composerKey.value++
  modal.value?.open()
  if (!store.feedOrganizations.length && !store.postAsOrganizations.length) {
    ready.value = false
    await store.fetchFeed()
  }
  ready.value = true
}

function close() {
  modal.value?.close()
}

function onPosted(post) {
  emit('shared', post)
  close()
}

defineExpose({ open, close })
</script>
