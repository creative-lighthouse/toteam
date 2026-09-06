<template>
  <img v-if="src" :src="src" :alt="alt" class="apporglogo" :style="sizeStyle">
  <span v-else class="apporglogo apporglogo--placeholder" :style="sizeStyle">{{ initials }}</span>
</template>

<script setup>
import { computed } from 'vue'

// Eigenständig von AppAvatar (Personen-Profilbilder) getrennt: Organisations-Icons
// sind quadratisch/abgerundet statt kreisförmig und zeigen im Fallback nur eine
// einzelne Initiale (der Organisationsname ist meist ein einzelnes, langes Wort).
//
// Größe/Eckenradius werden per Prop statt per Kontext-CSS-Klasse gesteuert, damit
// jede Einsatzstelle nicht erneut Breite/Höhe/Radius/Schriftgröße definieren muss.
// Zusätzliche, wirklich kontextspezifische Regeln (Position, Hintergrund, Schatten,
// Abstand) lassen sich weiterhin ganz normal per `class="..."` von außen mitgeben.
const props = defineProps({
  src: { type: String, default: null },
  alt: { type: String, default: '' },
  // Quelle für die Initiale im Platzhalter — meist identisch mit `alt`.
  name: { type: String, default: '' },
  // Standardmäßig kein fester Inline-Wert: Ausnahmsweise soll eine Einsatzstelle
  // (z.B. ein Logo, das responsiv 100% seines umgebenden Rahmens ausfüllt) die
  // Größe komplett per eigener CSS-Klasse steuern können, statt vom fixen px-Wert
  // hier überschrieben zu werden — Inline-Styles gewinnen sonst immer gegen CSS.
  size: { type: [Number, String], default: null },
  radius: { type: [Number, String], default: null },
  initialsLength: { type: Number, default: 1 },
})

function toPx(value) {
  return typeof value === 'number' ? `${value}px` : value
}

const sizeStyle = computed(() => {
  if (props.size === null) return {}
  const sizeNum = typeof props.size === 'number' ? props.size : parseFloat(props.size)
  return {
    width: toPx(props.size),
    height: toPx(props.size),
    borderRadius: props.radius !== null ? toPx(props.radius) : undefined,
    fontSize: `${sizeNum * 0.4}px`,
  }
})

const initials = computed(() => {
  const source = props.name || props.alt || ''
  const letters = source
    .split(' ')
    .filter(Boolean)
    .slice(0, props.initialsLength)
    .map((word) => word[0].toUpperCase())
    .join('')
  return letters || '?'
})
</script>
