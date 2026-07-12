<template>
  <button
    type="button"
    class="app-icon-button"
    :class="`app-icon-button--${variant}`"
    :disabled="disabled"
    :aria-label="ariaLabel"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'neutral' }, // primary | danger | neutral | ghost
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, required: true },
})
</script>
