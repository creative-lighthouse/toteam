<template>
  <component
    :is="to ? 'router-link' : 'button'"
    :to="to ?? undefined"
    :type="to ? undefined : 'button'"
    class="app-icon-button"
    :class="`app-icon-button--${variant}`"
    :disabled="!to && disabled"
    :aria-label="ariaLabel"
  >
    <slot />
  </component>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'neutral' }, // primary | danger | neutral | ghost
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, required: true },
  // Wie bei AppButton: mit `to` wird ein router-link gerendert statt eines <button>
  to: { type: [String, Object], default: null },
})
</script>
