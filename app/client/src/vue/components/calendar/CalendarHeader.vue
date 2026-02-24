<template>
  <div class="calendar-header">
    <button
      @click="$emit('previous')"
      class="button button--icon"
      aria-label="Vorheriger Monat"
    >
      ←
    </button>
    <h2 class="hl2">{{ monthTitle }}</h2>
    <button
      @click="$emit('next')"
      class="button button--icon"
      aria-label="Nächster Monat"
    >
      →
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  year: {
    type: Number,
    required: true
  },
  month: {
    type: Number,
    required: true
  }
})

defineEmits(['previous', 'next'])

const monthTitle = computed(() => {
  const date = new Date(props.year, props.month - 1, 1)
  return new Intl.DateTimeFormat('de-DE', {
    month: 'long',
    year: 'numeric'
  }).format(date)
})
</script>
