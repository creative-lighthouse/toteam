<!--
  Suchleiste für Übersichtsseiten: Suchfeld mit optionalen Aktionen rechts
  daneben (z. B. "+ Neuer Raum") und optional einer zweiten Zeile mit Filtern.

    <AppSearchBar v-model="search" placeholder="Links durchsuchen…">
      <template #actions>
        <AppButton variant="primary" @click="…">+ Link hinzufügen</AppButton>
      </template>
      <template #filters>
        <select>…</select>
      </template>
    </AppSearchBar>

  Selects/Inputs im filters-Slot bekommen automatisch denselben Look.
-->
<template>
  <div class="app-search-bar">
    <div class="app-search-bar_row">
      <div class="app-search-bar_field">
        <svg class="app-search-bar_icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          type="search"
          class="app-search-bar_input"
          :value="modelValue"
          :placeholder="placeholder"
          :aria-label="ariaLabel || placeholder"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </div>
      <div v-if="$slots.actions" class="app-search-bar_actions">
        <slot name="actions" />
      </div>
    </div>

    <div v-if="$slots.filters" class="app-search-bar_filters">
      <slot name="filters" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Suchen…' },
  ariaLabel: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>
