<!--
  Generiertes Platzhalterbild für Datensätze ohne eigenes Bild (z.B. Events ohne
  Hauptbild): aus `seed` entsteht immer dasselbe unscharfe Farbmuster, siehe
  utils/patternImage.js. Füllt seinen Container vollständig (wie ein Bild mit
  object-fit: cover) — Größe und Seitenverhältnis bestimmt die Einsatzstelle.

    <AppPatternImage :seed="event.Title" />
-->
<template>
  <svg
    class="app-pattern-image"
    :viewBox="`0 0 ${pattern.width} ${pattern.height}`"
    preserveAspectRatio="xMidYMid slice"
    aria-hidden="true"
  >
    <defs>
      <linearGradient :id="`${id}-bg`" gradientUnits="objectBoundingBox" :gradientTransform="`rotate(${pattern.background.angle} 0.5 0.5)`">
        <stop offset="0" :stop-color="pattern.background.from" />
        <stop offset="1" :stop-color="pattern.background.to" />
      </linearGradient>
      <!-- Großzügige Filterfläche, damit die Unschärfe nicht an den Kanten abreißt -->
      <filter :id="`${id}-blur`" x="-50%" y="-50%" width="200%" height="200%">
        <feGaussianBlur :stdDeviation="pattern.width / 14" />
      </filter>
      <!-- Feine Körnung gegen den „Plastik“-Look reiner Verläufe -->
      <filter :id="`${id}-grain`" x="0" y="0" width="100%" height="100%">
        <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" stitchTiles="stitch" />
        <feColorMatrix type="saturate" values="0" />
      </filter>
    </defs>
    <rect :width="pattern.width" :height="pattern.height" :fill="`url(#${id}-bg)`" />
    <g :filter="`url(#${id}-blur)`">
      <circle
        v-for="(blob, i) in pattern.blobs"
        :key="i"
        :cx="blob.cx"
        :cy="blob.cy"
        :r="blob.r"
        :fill="blob.color"
        :fill-opacity="blob.opacity"
      />
    </g>
    <rect :width="pattern.width" :height="pattern.height" :filter="`url(#${id}-grain)`" opacity="0.12" style="mix-blend-mode: overlay" />
  </svg>
</template>

<script setup>
import { computed, useId } from 'vue'
import { generatePattern } from '@utils/patternImage'

const props = defineProps({
  // Grundlage für das Muster, z.B. der Titel
  seed: { type: String, default: '' },
})

// Eindeutige IDs für Verlauf/Filter — mehrere Platzhalter auf einer Seite dürfen sich nicht überschreiben
const id = `app-pattern-${useId()}`

const pattern = computed(() => generatePattern(props.seed))
</script>
