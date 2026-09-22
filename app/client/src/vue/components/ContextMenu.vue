<template>
  <!--
    Kein Teleport nach body: Manche Aufrufer (z.B. das Termin-Modal) sind ein
    natives <dialog>, dessen Inhalt der Browser in den "Top Layer" hebt — der
    liegt immer über normalem body-Inhalt, unabhängig vom z-index. Würde dieses
    Menü nach body teleportiert, würde es hinter so einem Dialog verschwinden.
    Als normales Kind an der Aufrufstelle landet es im selben Layer wie der
    Dialog (bzw. im normalen Dokumentfluss, wenn es außerhalb eines Dialogs
    verwendet wird) und liegt dank position:fixed + hohem z-index trotzdem oben.
  -->
  <div
    v-if="open"
    class="context-menu"
    :style="menuStyle"
    role="menu"
    @contextmenu.prevent
    @mousedown.stop
  >
    <button
      v-for="(item, index) in items"
      :key="index"
      type="button"
      role="menuitem"
      class="context-menu_item"
      :class="{ 'context-menu_item--danger': item.danger }"
      @click="select(item)"
    >{{ item.label }}</button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'

const open = ref(false)
const items = ref([])
const menuStyle = reactive({ top: '0px', left: '0px' })

function openMenu(event, menuItems) {
  event.preventDefault()
  items.value = menuItems
  open.value = true

  // Erst positionieren, nachdem das Menü im DOM ist, damit seine Größe bekannt ist
  requestAnimationFrame(() => {
    const maxLeft = window.innerWidth - 220
    const maxTop = window.innerHeight - (menuItems.length * 40 + 16)
    menuStyle.left = `${Math.min(event.clientX, Math.max(maxLeft, 0))}px`
    menuStyle.top = `${Math.min(event.clientY, Math.max(maxTop, 0))}px`
  })

  document.addEventListener('mousedown', onClickOutside)
  document.addEventListener('keydown', onKeydown)
}

function closeMenu() {
  open.value = false
  document.removeEventListener('mousedown', onClickOutside)
  document.removeEventListener('keydown', onKeydown)
}

function onClickOutside() {
  closeMenu()
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    closeMenu()
  }
}

function select(item) {
  closeMenu()
  item.onClick?.()
}

defineExpose({ open: openMenu, close: closeMenu })
</script>
