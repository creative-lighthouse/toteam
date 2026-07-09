<template>
  <component
    :is="to ? 'router-link' : 'button'"
    :to="to ?? undefined"
    :type="to ? undefined : type"
    :disabled="!to && disabled"
    class="app-button"
    :class="[`app-button--${variant}`, `app-button--${size}`]"
  >
    <slot />
  </component>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | danger
  size: { type: String, default: 'default' }, // default | small
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  to: { type: [String, Object], default: null },
})
</script>
