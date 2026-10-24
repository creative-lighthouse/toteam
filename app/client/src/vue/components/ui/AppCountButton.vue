<!--
  Button mit angeschlossenem Zähler, z.B. "Interessiert | 12" auf der Event-Seite:
  links die Druckfläche (Text, optional Icon per Slot), rechts direkt verbunden der
  Zähler. `active` zeigt die eigene Auswahl (gefüllt, aria-pressed). Mit
  `count-clickable` wird der Zähler (ab 1) selbst ein Button und meldet `count-click`,
  z.B. um zu zeigen, wer dahintersteckt.

    <AppCountButton label="Interessiert" :count="12" :active="mine" @click="toggle" />
    <AppCountButton label="+1" :count="3" count-clickable @count-click="showVoters" />
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
    <button
      v-if="countClickable && count > 0"
      type="button"
      class="app-count-button_count app-count-button_count--clickable"
      :aria-label="countLabel ? `${count} ${countLabel} anzeigen` : null"
      :title="countTitle || null"
      @click="emit('count-click', $event)"
    >{{ count }}</button>
    <span v-else class="app-count-button_count" :aria-label="countLabel ? `${count} ${countLabel}` : null">{{ count }}</span>
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
  // Zähler als eigener Button (ab 1), meldet count-click
  countClickable: { type: Boolean, default: false },
  countTitle: { type: String, default: '' },
})

const emit = defineEmits(['click', 'count-click'])
</script>
