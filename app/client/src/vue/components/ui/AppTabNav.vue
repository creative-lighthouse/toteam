<template>
  <!-- Tab-Pille, die über dem App-Menü schwebt (z.B. Essen: Übersicht/Planen,
       Lagepläne: Allgemein/Events). Die Seite braucht unten Platz dafür (ca. 80px). -->
  <nav class="app-tab-nav" :aria-label="label">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      class="app-tab-nav_item"
      :class="{ 'is-active': modelValue === tab.id }"
      :aria-current="modelValue === tab.id ? 'page' : null"
      @click="emit('update:modelValue', tab.id)"
    >
      <span v-if="tab.icon" class="app-tab-nav_icon" :style="maskStyle(tab.icon)" aria-hidden="true"></span>
      {{ tab.label }}
    </button>
  </nav>
</template>

<script setup>
defineProps({
  // [{ id, label, icon? }] — icon: importierte SVG-URL, wird als Maske in Textfarbe gezeigt
  tabs: { type: Array, required: true },
  modelValue: { type: String, default: null },
  // Name der Navigation für Screenreader
  label: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

const maskStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })
</script>
