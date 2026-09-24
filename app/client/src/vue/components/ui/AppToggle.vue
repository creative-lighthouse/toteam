<!--
  Wiederverwendbarer An/Aus-Schalter für Modal-Formulare — Label links,
  Toggle-Switch rechts (dasselbe Muster wie das "Ganztägig"-Feld). Ersetzt
  einzelne Ja/Nein-Checkboxen, die bisher als normale Browser-Checkbox oder
  als Chip in einer .multiselect-group dargestellt wurden.

  Rendert sich selbst als kompletten .field-Block (Default: volle Breite,
  span 6) — einfach direkt als Kind eines .modalform verwenden:
    <AppToggle v-model="form.EnableMeals" label="Mahlzeiten" />
  Für mehrere Toggles nebeneinander per `field-class` überschreiben, z.B.
  `field-class="field field--3"`.
-->
<template>
  <label :class="fieldClass">
    <span>{{ label }}</span>
    <span class="toggle-switch">
      <input
        type="checkbox"
        class="toggle-switch_input"
        :checked="modelValue"
        @change="$emit('update:modelValue', $event.target.checked)"
      >
      <span class="toggle-switch_track"></span>
    </span>
  </label>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  fieldClass: { type: String, default: 'field toggle-field' },
})

defineEmits(['update:modelValue'])
</script>
