<template>
  <!--
    Auswahl wie Zu-/Absagen: für Tastatur und Screenreader eine Radio-Gruppe mit
    nur einem Tab-Stopp. Pfeiltasten (sowie Pos1/Ende) bewegen den Fokus,
    Leertaste/Enter wählt aus — so schickt nicht jeder Tastendruck eine Antwort ab.
    Erneutes Auswählen der aktiven Option wählt sie wieder ab ("Ohne Antwort").
  -->
  <div
    class="app-button-group"
    :class="{ 'app-button-group--compact': size === 'compact' }"
    role="radiogroup"
    :aria-label="label || undefined"
    :aria-disabled="disabled || undefined"
    @keydown="onKeydown"
  >
    <div class="app-button-group_pill" :style="pillStyle"></div>
    <button
      v-for="opt in options"
      :key="opt.value"
      ref="buttons"
      type="button"
      role="radio"
      class="app-button-group_item"
      :class="[
        `app-button-group_item--${opt.tone || 'neutral'}`,
        {
          'is-selected': modelValue === opt.value,
          'is-hint': !hasSelection,
        },
      ]"
      :aria-checked="modelValue === opt.value"
      :aria-disabled="disabled || undefined"
      :tabindex="opt.value === focusValue ? 0 : -1"
      @click="handleClick(opt.value)"
    >
      <svg v-if="opt.tone === 'positive'" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8.5L6.5 11.5L12.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <svg v-else-if="opt.tone === 'warning'" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M5 6a3 3 0 1 1 4.5 2.6C8.6 9.1 8 9.7 8 10.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="8" cy="13" r="0.9" fill="currentColor"/></svg>
      <svg v-else-if="opt.tone === 'negative'" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      {{ opt.label }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  options: { type: Array, required: true }, // [{ value, label, tone }] tone: positive | warning | negative | neutral
  modelValue: { type: [String, Number], default: null },
  size: { type: String, default: 'default' }, // default | compact
  // Während des Speicherns: Klicks werden ignoriert. Bewusst nicht als echtes
  // disabled, sonst verliert der Tastaturfokus beim Antworten seinen Platz.
  disabled: { type: Boolean, default: false },
  // Name der Gruppe für Screenreader, z.B. "Deine Teilnahme"
  label: { type: String, default: '' },
})

const emit = defineEmits(['select'])

// Erneutes Klicken der bereits aktiven Option wählt sie wieder ab (zurück zu "Ohne Antwort")
function handleClick(value) {
  if (props.disabled) return
  emit('select', props.modelValue === value ? null : value)
}

// Roving Tabindex: per Tab erreichbar ist nur die gewählte Option (sonst die erste)
const buttons = ref([])
const focusValue = computed(() =>
  props.options.some(o => o.value === props.modelValue) ? props.modelValue : props.options[0]?.value
)

function onKeydown(event) {
  const list = buttons.value
  const current = list.indexOf(document.activeElement)
  if (current === -1) return
  const last = list.length - 1
  const target = {
    ArrowRight: current === last ? 0 : current + 1,
    ArrowDown: current === last ? 0 : current + 1,
    ArrowLeft: current === 0 ? last : current - 1,
    ArrowUp: current === 0 ? last : current - 1,
    Home: 0,
    End: last,
  }[event.key]
  if (target === undefined) return
  event.preventDefault()
  list.forEach((b, i) => { b.tabIndex = i === target ? 0 : -1 })
  list[target].focus()
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
