<!--
  Runder Download-Knopf für Fotos (Listenansicht, große Kacheln). `overlay` = halbtransparent
  dunkel, für die Platzierung direkt auf einem Bild.

    <PhotoDownloadButton :busy="…" overlay @click="…" />
-->
<template>
  <button
    type="button"
    class="photo-download-button"
    :class="{ 'photo-download-button--overlay': overlay }"
    :aria-label="busy ? 'Foto wird heruntergeladen…' : label"
    :title="busy ? 'Wird heruntergeladen…' : 'Herunterladen'"
    :disabled="busy"
  >
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 4v11" />
      <path d="m7 10 5 5 5-5" />
      <path d="M5 20h14" />
    </svg>
  </button>
</template>

<script setup>
defineProps({
  busy: { type: Boolean, default: false },
  overlay: { type: Boolean, default: false },
  // Für Screenreader, z.B. "Foto vom 30.10.2026 herunterladen"
  label: { type: String, default: 'Foto herunterladen' },
})
</script>
