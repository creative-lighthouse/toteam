<template>
  <nav class="script-toc" :class="{ 'script-toc--collapsed': collapsed }" aria-label="Gliederung">
    <button
      type="button"
      class="script-toc_toggle"
      :aria-expanded="!collapsed"
      :title="collapsed ? 'Gliederung einblenden' : 'Gliederung ausblenden'"
      @click="toggle"
    >
      <svg class="script-toc_toggle-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      <span class="script-toc_toggle-label">Gliederung</span>
    </button>

    <template v-if="!collapsed">
      <p v-if="entries.length === 0" class="script-toc_empty">Keine Überschriften.</p>
      <ul v-else class="script-toc_list">
        <li
          v-for="entry in entries"
          :key="entry.paragraphId"
          class="script-toc_item"
          :class="`script-toc_item--level-${entry.level}`"
        >
          <button type="button" class="script-toc_link" @click="jumpTo(entry.paragraphId)">
            {{ entry.text || '(ohne Titel)' }}
          </button>
        </li>
      </ul>
    </template>
  </nav>
</template>

<script setup>
import { ref } from 'vue'
import { getCookie, setCookie } from '@utils/cookies'

const COLLAPSE_COOKIE = 'toteam_skript_toc_collapsed'

defineProps({
  entries: { type: Array, default: () => [] },
})

// Remembers the user's choice across visits; on the very first visit it
// defaults to collapsed on narrow (mobile) screens and expanded otherwise.
const savedState = getCookie(COLLAPSE_COOKIE)
const collapsed = ref(
  savedState !== null ? savedState === '1' : window.matchMedia('(max-width: 700px)').matches
)

function toggle() {
  collapsed.value = !collapsed.value
  setCookie(COLLAPSE_COOKIE, collapsed.value ? '1' : '0')
}

function jumpTo(paragraphId) {
  const el = document.getElementById(`paragraph-${paragraphId}`)
  el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>
