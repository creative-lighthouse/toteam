<!--
  Leiste der Mehrfachauswahl, unten am Bildschirm, sobald Fotos ausgewählt sind:
  Anzahl, alle (geladenen) auswählen, Tags hinzufügen, löschen, Auswahl aufheben.

    <PhotoSelectionBar :count="3" :selectable-count="60" :busy="…" @select-all="…" @tag="…" @remove="…" @clear="…" />
-->
<template>
  <Transition name="photo-selection-bar">
    <div v-if="count" class="photo-selection-bar" role="region" aria-label="Auswahl">
      <AppIconButton variant="ghost" aria-label="Auswahl aufheben" title="Auswahl aufheben (Esc)" @click="emit('clear')">✕</AppIconButton>
      <p class="photo-selection-bar_count" role="status">{{ count }} ausgewählt</p>
      <button
        v-if="selectableCount > count"
        type="button"
        class="photo-selection-bar_all"
        @click="emit('select-all')"
      >Alle {{ selectableCount }} auswählen</button>
      <div class="photo-selection-bar_actions">
        <AppButton variant="primary" size="small" :disabled="busy" @click="emit('tag')">Tags hinzufügen</AppButton>
        <AppIconButton
          variant="danger"
          aria-label="Ausgewählte Fotos löschen"
          title="Löschen"
          :disabled="busy"
          @click="emit('remove')"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

defineProps({
  count: { type: Number, required: true },
  // Wie viele der geladenen Fotos auswählbar sind
  selectableCount: { type: Number, default: 0 },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['select-all', 'tag', 'remove', 'clear'])

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }
</script>
