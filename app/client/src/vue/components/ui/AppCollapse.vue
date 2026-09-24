<!--
  Auf-/zuklappbarer Bereich mit klickbarer Kopfzeile und Chevron, z. B. für
  optionale Formularfelder in Modals. Innerhalb eines .modalform einfach als
  Feld verwenden — der Inhalt kann selbst wieder ein .modalform-Grid sein:

    <AppCollapse title="Koordinaten" subtitle="optional">
      <div class="modalform">…Felder…</div>
    </AppCollapse>

  Standardmäßig selbst verwaltet; mit v-model:open von außen steuerbar.
-->
<template>
  <div class="field app-collapse" :class="{ 'app-collapse--open': isOpen }">
    <button
      type="button"
      class="app-collapse_header"
      :aria-expanded="isOpen"
      :aria-controls="contentId"
      @click="toggle"
    >
      <span class="app-collapse_title">
        {{ title }}
        <span v-if="subtitle" class="app-collapse_subtitle">{{ subtitle }}</span>
      </span>
      <span class="app-collapse_chevron" aria-hidden="true"></span>
    </button>

    <div :id="contentId" class="app-collapse_content" :inert="!isOpen">
      <div class="app-collapse_inner">
        <div class="app-collapse_body">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  title: { type: String, required: true },
  // Kleiner Zusatz hinter dem Titel, z. B. "optional"
  subtitle: { type: String, default: '' },
  // Für v-model:open — ohne Angabe verwaltet die Komponente den Zustand selbst
  open: { type: Boolean, default: undefined },
  defaultOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open'])

const contentId = `collapse-${Math.random().toString(36).slice(2)}`
const internalOpen = ref(props.defaultOpen)
const isOpen = computed(() => props.open ?? internalOpen.value)

function toggle() {
  internalOpen.value = !isOpen.value
  emit('update:open', internalOpen.value)
}
</script>
