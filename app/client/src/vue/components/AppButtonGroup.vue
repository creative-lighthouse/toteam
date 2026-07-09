<template>
  <fieldset class="app-button-group" :class="{ 'app-button-group--compact': size === 'compact' }" :disabled="disabled">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      class="app-button-group_item"
      :class="[
        `app-button-group_item--${opt.tone || 'neutral'}`,
        {
          'is-selected': modelValue === opt.value,
          'is-unselected': modelValue !== null && modelValue !== undefined && modelValue !== opt.value,
        },
      ]"
      :disabled="disabled"
      @click="emit('select', opt.value)"
    >{{ opt.label }}</button>
  </fieldset>
</template>

<script setup>
defineProps({
  options: { type: Array, required: true }, // [{ value, label, tone }] tone: positive | warning | negative | neutral
  modelValue: { type: [String, Number], default: null },
  size: { type: String, default: 'default' }, // default | compact
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
</script>
