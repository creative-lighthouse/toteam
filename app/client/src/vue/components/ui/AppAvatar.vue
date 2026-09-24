<template>
  <img v-if="src" :src="src" :alt="alt" :class="imgClass">
  <span v-else :class="[imgClass, placeholderClass || `${imgClass}--placeholder`]">{{ initials }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  src: { type: String, default: null },
  alt: { type: String, default: '' },
  // Quelle für die Initialen im Platzhalter — meist identisch mit `alt`, aber z. B.
  // bei Bewerbern soll der Platzhalter nur die Initiale des Vornamens zeigen, während
  // der Alt-Text weiterhin den vollen Namen trägt.
  name: { type: String, default: '' },
  imgClass: { type: String, required: true },
  placeholderClass: { type: String, default: null },
  initialsLength: { type: Number, default: 2 },
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
