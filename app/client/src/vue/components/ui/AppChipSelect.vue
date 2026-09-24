<!--
  Mehrfachauswahl als anklickbare Chips, z. B. Aufgaben eines Raums. v-model ist
  ein Array der gewählten Werte. Technisch Checkboxen (Tastatur/Screenreader).

    <AppChipSelect v-model="form.TaskIDs" :options="tasks.map(t => ({ value: t.ID, label: t.Title }))" />

  Für eine Auswahl aus genau einem von wenigen Werten: AppSegmentedToggle.
-->
<template>
  <div class="app-chip-select" role="group" :aria-label="ariaLabel || undefined">
    <label
      v-for="opt in options"
      :key="opt.value"
      class="app-chip-select_chip"
      :class="{ 'app-chip-select_chip--selected': isSelected(opt.value) }"
    >
      <input
        type="checkbox"
        class="app-chip-select_input"
        :value="opt.value"
        :checked="isSelected(opt.value)"
        @change="toggle(opt.value)"
      >
      {{ opt.label }}
    </label>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  // [{ value, label }]
  options: { type: Array, required: true },
  ariaLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

function isSelected(value) {
  return props.modelValue.includes(value)
}

function toggle(value) {
  emit('update:modelValue', isSelected(value)
    ? props.modelValue.filter(v => v !== value)
    : [...props.modelValue, value])
}
</script>
