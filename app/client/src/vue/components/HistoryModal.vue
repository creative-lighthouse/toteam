<template>
  <AppModal ref="modal" class="history-modal" :title="title" @close="close">
    <div v-if="entries.length" class="history-modal_list">
      <HistoryEntry
        v-for="entry in entries"
        :key="entry.ID"
        :entry="entry"
        :created-label="createdLabel"
      />
    </div>

    <div v-if="loading" class="history-modal_status">Lade Verlauf…</div>
    <div v-else-if="error" class="history-modal_status">
      <span class="app-modal_error">{{ error }}</span>
      <AppButton size="small" variant="secondary" @click="loadMore">Erneut versuchen</AppButton>
    </div>
    <div v-else-if="!entries.length" class="history-modal_status">Noch keine Änderungen.</div>

    <!-- Erreicht dieses Element den sichtbaren Bereich, wird die nächste Seite geladen -->
    <div ref="sentinel" class="history-modal_sentinel" />
  </AppModal>
</template>

<script setup>
import { ref, nextTick, onBeforeUnmount } from 'vue'
import { apiGet } from '@utils/api'
import AppModal from '@components/AppModal.vue'
import AppButton from '@components/AppButton.vue'
import HistoryEntry from '@components/HistoryEntry.vue'

// Generisches Verlaufs-Modal für jedes Datenmodell mit HistoryExtension. Erwartet
// einen Endpoint, der ApiController::historyResponse() nutzt ({ entries, hasMore },
// Cursor-Paginierung über ?before=<EntryID>).
const props = defineProps({
  endpoint: { type: String, required: true },
  title: { type: String, default: 'Verlauf' },
  createdLabel: { type: String, default: 'hat den Eintrag erstellt' },
  pageSize: { type: Number, default: 20 },
})

const modal = ref(null)
const sentinel = ref(null)
const entries = ref([])
const hasMore = ref(true)
const loading = ref(false)
const error = ref(null)

let observer = null
// Verwirft Antworten, die nach erneutem Öffnen (ggf. für einen anderen Datensatz) eintreffen
let requestToken = 0

async function loadMore() {
  if (loading.value || !hasMore.value) return

  loading.value = true
  error.value = null
  const token = requestToken
  const last = entries.value[entries.value.length - 1]
  const query = `limit=${props.pageSize}` + (last ? `&before=${last.ID}` : '')
  const separator = props.endpoint.includes('?') ? '&' : '?'

  try {
    const response = await apiGet(`${props.endpoint}${separator}${query}`, false)
    if (token !== requestToken) return
    if (!response?.entries) {
      throw new Error(response?.error || 'Verlauf konnte nicht geladen werden.')
    }
    entries.value.push(...response.entries)
    hasMore.value = response.hasMore
  } catch (err) {
    if (token !== requestToken) return
    error.value = err.message || 'Verlauf konnte nicht geladen werden.'
  } finally {
    if (token === requestToken) loading.value = false
  }

  // Der Observer meldet sich nur bei einer Zustandsänderung. Ist das Sentinel nach
  // dem Laden immer noch sichtbar (kurze Seite), neu beobachten, damit er sofort
  // erneut auslöst und die nächste Seite nachlädt.
  await nextTick()
  if (observer && sentinel.value && hasMore.value && !error.value) {
    observer.unobserve(sentinel.value)
    observer.observe(sentinel.value)
  }
}

function open() {
  requestToken++
  entries.value = []
  hasMore.value = true
  loading.value = false
  error.value = null
  modal.value?.open()

  observer?.disconnect()
  observer = new IntersectionObserver((observed) => {
    if (observed.some(e => e.isIntersecting)) loadMore()
  }, { rootMargin: '0px 0px 200px 0px' })

  nextTick(() => {
    if (sentinel.value) observer.observe(sentinel.value)
  })
}

function close() {
  observer?.disconnect()
  observer = null
  modal.value?.close()
}

onBeforeUnmount(() => observer?.disconnect())

defineExpose({ open, close })
</script>
