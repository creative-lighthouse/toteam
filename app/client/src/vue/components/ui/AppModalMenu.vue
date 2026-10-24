<!--
  Drei-Punkte-Menü für seltene Aktionen, die irgendwo erreichbar sein müssen,
  aber nicht als eigener Button im Weg stehen sollen (z.B. "Zur Aufgabe machen").
  Sitzt normalerweise im Kopf von AppModal (Prop `menu`), lässt sich aber auch
  allein verwenden. Einträge wie bei ContextMenu; null/false werden übersprungen,
  ohne Einträge wird nichts angezeigt.

    <AppModalMenu :items="[
      canConvert && { label: 'Zur Aufgabe machen', onClick: convertToTask },
      { label: 'Löschen', onClick: remove, danger: true },
    ]" />
-->
<template>
  <template v-if="visibleItems.length">
    <AppIconButton
      ref="buttonRef"
      variant="ghost"
      class="app-modal-menu"
      :aria-label="label"
      :title="label"
      aria-haspopup="menu"
      :aria-expanded="!!menu?.isOpen"
      @click="toggle"
    >
      <span class="app-modal-menu_dots" aria-hidden="true"><span /><span /><span /></span>
    </AppIconButton>
    <ContextMenu ref="menu" />
  </template>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'

const props = defineProps({
  // { label, onClick, danger? }[] — null/false-Einträge erlauben bedingte Aktionen
  items: { type: Array, default: () => [] },
  label: { type: String, default: 'Weitere Aktionen' },
})

const buttonRef = ref(null)
const menu = ref(null)

const visibleItems = computed(() => props.items.filter(Boolean))

function toggle() {
  if (menu.value?.isOpen) {
    menu.value.close({ restoreFocus: true })
    return
  }
  const anchor = buttonRef.value?.$el
  menu.value?.open({ anchor }, visibleItems.value)
}
</script>
