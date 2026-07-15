<template>
  <fieldset class="app-button-group" :class="{ 'app-button-group--compact': size === 'compact' }" :disabled="disabled">
    <div class="app-button-group_pill" :style="pillStyle"></div>
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      class="app-button-group_item"
      :class="[
        `app-button-group_item--${opt.tone || 'neutral'}`,
        {
          'is-selected': modelValue === opt.value,
          'is-hint': !hasSelection,
        },
      ]"
      :disabled="disabled"
      @click="handleClick(opt.value)"
    >
      <svg v-if="opt.tone === 'positive'" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3.5 8.5L6.5 11.5L12.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <svg v-else-if="opt.tone === 'warning'" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M5 6a3 3 0 1 1 4.5 2.6C8.6 9.1 8 9.7 8 10.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="8" cy="13" r="0.9" fill="currentColor"/></svg>
      <svg v-else-if="opt.tone === 'negative'" width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      {{ opt.label }}
    </button>
  </fieldset>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  options: { type: Array, required: true }, // [{ value, label, tone }] tone: positive | warning | negative | neutral
  modelValue: { type: [String, Number], default: null },
  size: { type: String, default: 'default' }, // default | compact
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

// Erneutes Klicken der bereits aktiven Option wählt sie wieder ab (zurück zu "Ohne Antwort")
function handleClick(value) {
  emit('select', props.modelValue === value ? null : value)
}

const activeIndex = computed(() => props.options.findIndex(o => o.value === props.modelValue))
const hasSelection = computed(() => activeIndex.value !== -1)

function toneColorVar(tone) {
  switch (tone) {
    case 'positive': return 'var(--ColorStatusGood)'
    case 'warning': return 'var(--ColorStatusWarning)'
    case 'negative': return 'var(--ColorStatusBad)'
    default: return 'var(--ColorGray)'
  }
}

// Merkt sich Position/Farbe der zuletzt aktiven Option, damit der Pill beim Abwählen
// an Ort und Stelle ausblendet statt zu springen (left/width/Farbe bleiben stehen,
// nur die Opacity animiert).
const lastActiveIndex = ref(Math.max(activeIndex.value, 0))
const lastActiveTone = ref(props.options[lastActiveIndex.value]?.tone)

watch(activeIndex, (idx) => {
  if (idx !== -1) {
    lastActiveIndex.value = idx
    lastActiveTone.value = props.options[idx]?.tone
  }
})

const pillStyle = computed(() => {
  const n = props.options.length || 1
  const idx = hasSelection.value ? activeIndex.value : lastActiveIndex.value
  const tone = hasSelection.value ? props.options[activeIndex.value]?.tone : lastActiveTone.value
  return {
    left: `calc(3px + (100% - 6px) * ${idx} / ${n})`,
    width: `calc((100% - 6px) / ${n})`,
    backgroundColor: toneColorVar(tone),
    opacity: hasSelection.value ? 1 : 0,
  }
})
</script>
