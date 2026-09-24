<!--
  Umschalter zwischen zwei (oder wenigen) festen Werten, z. B. Ausgabe/Einnahme
  oder Externe URL/Datei — immer genau ein Wert ist gewählt. Technisch eine
  Radio-Gruppe (Tastatur/Screenreader), optisch ein Segment-Schalter mit
  gleitendem Marker. Rendert sich als kompletter .field-Block:

    <AppSegmentedToggle
      v-model="form.ChangeType"
      label="Typ"
      :options="[
        { value: 'Withdrawal', label: 'Ausgabe', disabled: !canEnterWithdrawal },
        { value: 'Deposit', label: 'Einnahme' },
      ]"
    />

  Nicht zu verwechseln mit AppButtonGroup (Zu-/Absagen mit Farbtönen,
  abwählbar) und AppToggle (einzelner An/Aus-Schalter).
-->
<template>
  <div class="field app-segmented-toggle" :class="{ 'app-segmented-toggle--disabled': disabled }">
    <span v-if="label" :id="labelId" class="app-segmented-toggle_label">{{ label }}</span>
    <div
      class="app-segmented-toggle_track"
      role="radiogroup"
      :aria-labelledby="label ? labelId : undefined"
      :style="{ '--segment-count': options.length, '--segment-index': Math.max(activeIndex, 0) }"
    >
      <span v-if="activeIndex !== -1" class="app-segmented-toggle_marker" aria-hidden="true"></span>
      <label
        v-for="opt in options"
        :key="opt.value"
        class="app-segmented-toggle_option"
        :class="{
          'app-segmented-toggle_option--active': opt.value === modelValue,
          'app-segmented-toggle_option--disabled': disabled || opt.disabled,
        }"
      >
        <input
          type="radio"
          class="app-segmented-toggle_input"
          :name="inputName"
          :value="opt.value"
          :checked="opt.value === modelValue"
          :disabled="disabled || opt.disabled"
          @change="$emit('update:modelValue', opt.value)"
        >
        {{ opt.label }}
      </label>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, Boolean], default: null },
  // [{ value, label, disabled? }]
  options: { type: Array, required: true },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const uid = Math.random().toString(36).slice(2)
const inputName = `segmented-toggle-${uid}`
const labelId = `segmented-toggle-label-${uid}`

const activeIndex = computed(() => props.options.findIndex(o => o.value === props.modelValue))
</script>
