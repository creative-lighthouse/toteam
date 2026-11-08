<!--
  Tags zu mehreren ausgewählten Fotos hinzufügen (Mehrfachauswahl der Galerie). Vorhandene
  Tags der Fotos bleiben; Fotos ohne Bearbeitungsrecht überspringt der Server.

    <PhotoBulkTagModal ref="bulkTagModal" @saved="…" />
    bulkTagModal.value.open({ ids: [1, 2], organizationId: 3, eventIds: [7] })
-->
<template>
  <AppModal ref="modal" class="photo-bulk-tag-modal" :title="title" @close="close">
    <form id="photo-bulk-tag-form" class="modalform" @submit.prevent="save">
      <p class="photo-bulk-tag-modal_hint">Die Tags kommen zu den vorhandenen dazu.</p>
      <PhotoTagFields v-model="tagging" :options="options" :event-ids="eventIds" :disabled="busy" />
      <p v-if="!options" class="photo-bulk-tag-modal_hint">Lade Auswahl…</p>
      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="photo-bulk-tag-form" variant="primary" :disabled="busy || !hasTagging(tagging)">
        {{ busy ? 'Speichert…' : 'Hinzufügen' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePhotosStore } from '@stores/photos'
import { emptyTagging, hasTagging, taggingFields } from '@utils/photoTagging'
import AppModal from '@components/ui/AppModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import PhotoTagFields from '@components/photos/PhotoTagFields.vue'

const emit = defineEmits(['saved'])
const store = usePhotosStore()

const modal = ref(null)
const ids = ref([])
const organizationId = ref(null)
const eventIds = ref([])
const tagging = ref(emptyTagging())
const busy = ref(false)
const error = ref(null)

const options = computed(() => store.optionsByOrg[organizationId.value] ?? null)
const title = computed(() => `${ids.value.length} ${ids.value.length === 1 ? 'Foto' : 'Fotos'} taggen`)

function open({ ids: photoIds, organizationId: orgId, eventIds: events = [] }) {
  ids.value = [...photoIds]
  organizationId.value = orgId
  eventIds.value = events
  tagging.value = emptyTagging()
  error.value = null
  store.fetchOptions(orgId).catch(err => { error.value = err.message })
  modal.value?.open()
}

function close() {
  if (busy.value) return
  modal.value?.close()
}

async function save() {
  if (busy.value || !hasTagging(tagging.value)) return
  busy.value = true
  error.value = null
  try {
    const result = await store.bulkTag(ids.value, taggingFields(tagging.value))
    busy.value = false
    modal.value?.close()
    emit('saved', result)
  } catch (err) {
    error.value = err.message
    busy.value = false
  }
}

defineExpose({ open, close })
</script>
