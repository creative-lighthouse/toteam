<!--
  Auswahlkästchen eines Fotos (Mehrfachauswahl in Liste und großen Kacheln). Meldet beim
  Klick, ob Shift gedrückt war — dann wählt die Galerie den ganzen Bereich bis zum zuletzt
  angeklickten Foto.

    <PhotoSelectCheckbox :checked="…" :label="…" overlay @toggle="({ shift }) => …" />
-->
<template>
  <label class="photo-select-checkbox" :class="{ 'photo-select-checkbox--overlay': overlay, 'photo-select-checkbox--checked': checked }" :title="label">
    <input
      type="checkbox"
      class="photo-select-checkbox_input"
      :checked="checked"
      :aria-label="label"
      @click="onClick"
    >
    <span class="photo-select-checkbox_box" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10" /></svg>
    </span>
  </label>
</template>

<script setup>
defineProps({
  checked: { type: Boolean, default: false },
  label: { type: String, required: true },
  // Auf dem Bild (große Kacheln): mit dunklem Rand, damit es auf jedem Foto sichtbar ist
  overlay: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])

function onClick(event) {
  // Der Zustand kommt von außen (checked), nicht vom Browser
  event.preventDefault()
  emit('toggle', { shift: event.shiftKey })
}
</script>
