<!--
  Icon-Umschalter zwischen Ansichten einer Seite (Liste, Kacheln, Kanban …),
  z. B. in der Filterzeile der AppSearchBar:

    <AppViewToggle
      v-model="viewMode"
      :options="[
        { value: 'list', label: 'Listenansicht', icon: 'list' },
        { value: 'cards', label: 'Kartenansicht', icon: 'grid' },
      ]"
    />

  Icons: list, grid, kanban, table. Für Werte in Formularen AppSegmentedToggle nehmen.
-->
<template>
  <div class="app-view-toggle" role="group" aria-label="Ansicht">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      class="app-view-toggle_btn"
      :class="{ 'app-view-toggle_btn--active': opt.value === modelValue }"
      :aria-pressed="opt.value === modelValue"
      :title="opt.label"
      :aria-label="opt.label"
      @click="$emit('update:modelValue', opt.value)"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="opt.icon === 'list'">
          <line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" />
          <line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />
        </template>
        <template v-else-if="opt.icon === 'grid'">
          <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
        </template>
        <template v-else-if="opt.icon === 'kanban'">
          <rect x="3" y="3" width="5" height="18" rx="1" /><rect x="10" y="3" width="5" height="12" rx="1" /><rect x="17" y="3" width="5" height="7" rx="1" />
        </template>
        <template v-else-if="opt.icon === 'table'">
          <rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="9" x2="21" y2="9" /><line x1="3" y1="15" x2="21" y2="15" /><line x1="9" y1="3" x2="9" y2="21" />
        </template>
      </svg>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: null },
  // [{ value, label, icon }]
  options: { type: Array, required: true },
})

defineEmits(['update:modelValue'])
</script>
