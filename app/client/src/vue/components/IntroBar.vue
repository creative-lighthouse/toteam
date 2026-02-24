<template>
  <div class="intro_bar">
    <div class="intro_bar_content">
      <h1 class="hl1">{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
      <slot></slot>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  }
})
</script>

<style scoped>
/* All styles are inherited from main.scss - uses existing intro_bar classes */
</style>
