<template>
  <button
    type="button"
    class="app-icon-button"
    :class="[`app-icon-button--${variant}`, `app-icon-button--${size}`]"
    :disabled="disabled"
    :aria-label="ariaLabel"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'neutral' }, // primary | danger | neutral | ghost
  size: { type: String, default: 'default' }, // default | small
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, required: true },
})
</script>
