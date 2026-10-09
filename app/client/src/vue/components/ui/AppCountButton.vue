<!--
  Button mit angeschlossenem Zähler, z.B. "Interessiert | 12" auf der Event-Seite:
  links die Druckfläche (Text, optional Icon per Slot), rechts direkt verbunden der
  Zähler — der Zähler selbst ist nicht klickbar. `active` zeigt die eigene Auswahl
  (gefüllt, aria-pressed).

    <AppCountButton label="Interessiert" :count="12" :active="mine" @click="toggle" />
-->
<template>
  <span
    class="app-count-button"
    :class="{ 'app-count-button--active': active, 'app-count-button--disabled': disabled }"
  >
    <button
      type="button"
      class="app-count-button_action"
      :aria-pressed="active"
      :disabled="disabled"
      @click="emit('click', $event)"
    >
      <slot name="icon" />
      <span>{{ label }}</span>
    </button>
    <span class="app-count-button_count" :aria-label="countLabel ? `${count} ${countLabel}` : null">{{ count }}</span>
  </span>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  count: { type: Number, default: 0 },
  active: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  // Für Screenreader hinter der Zahl, z.B. "Personen interessiert"
  countLabel: { type: String, default: '' },
})

const emit = defineEmits(['click'])
</script>
