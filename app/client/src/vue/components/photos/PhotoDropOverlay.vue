<!--
  Rückmeldung beim Ziehen von Dateien auf einen Foto-Ordner: legt sich über die ganze Seite
  und zeigt, wohin die Fotos hochgeladen werden — oder dass die Dateien keine Bilder sind.
  Die Logik (Ziehen erkennen, Ablegen) steckt in views/Photos.vue.

    <PhotoDropOverlay :state="'ok'" target="Ottos Halloweenhaus › Show 2026" />
-->
<template>
  <Transition name="photo-drop-overlay">
    <div v-if="state" class="photo-drop-overlay" :class="`photo-drop-overlay--${state}`" aria-hidden="true">
      <div class="photo-drop-overlay_box">
        <svg v-if="state !== 'invalid'" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 16V4" />
          <path d="m7 9 5-5 5 5" />
          <path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />
        </svg>
        <svg v-else viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9" />
          <path d="m5.6 5.6 12.8 12.8" />
        </svg>

        <p class="photo-drop-overlay_title">
          <template v-if="state === 'invalid'">Das sind keine unterstützten Bilder</template>
          <template v-else>Fotos hier ablegen</template>
        </p>
        <p v-if="state !== 'invalid'" class="photo-drop-overlay_target">Hochladen nach <strong>{{ target }}</strong></p>
        <p v-if="state === 'mixed'" class="photo-drop-overlay_hint">Dateien, die keine Bilder sind, werden übersprungen.</p>
        <p class="photo-drop-overlay_hint">Erlaubt: {{ ACCEPTED_LABEL }}</p>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ACCEPTED_LABEL } from '@utils/photoUpload'

defineProps({
  // null = unsichtbar; ok | mixed | invalid | unknown (Browser verrät die Dateitypen beim Ziehen nicht)
  state: { type: String, default: null },
  // Ziel als Text, z.B. "Ottos Halloweenhaus › Show 2026"
  target: { type: String, default: '' },
})
</script>
