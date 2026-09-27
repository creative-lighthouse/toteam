<template>
  <!--
    Kein Teleport nach body: Manche Aufrufer (z.B. das Termin-Modal) sind ein
    natives <dialog>, dessen Inhalt der Browser in den "Top Layer" hebt — der
    liegt immer über normalem body-Inhalt, unabhängig vom z-index. Würde dieses
    Menü nach body teleportiert, würde es hinter so einem Dialog verschwinden.
    Als normales Kind an der Aufrufstelle landet es im selben Layer wie der
    Dialog (bzw. im normalen Dokumentfluss, wenn es außerhalb eines Dialogs
    verwendet wird) und liegt dank position:fixed + hohem z-index trotzdem oben.

    Tastatur: Beim Öffnen liegt der Fokus auf dem ersten Eintrag, Pfeiltasten
    (sowie Pos1/Ende) wechseln, Enter/Leertaste wählt, Esc schließt und bringt
    den Fokus zurück, Tab schließt. Geöffnet wird es meist über v-context-menu.
  -->
  <div
    v-if="open"
    ref="menuEl"
    class="context-menu"
    :style="menuStyle"
    role="menu"
    @contextmenu.prevent
    @keydown="onMenuKeydown"
  >
    <button
      v-for="(item, index) in items"
      :key="index"
      type="button"
      role="menuitem"
      tabindex="-1"
      class="context-menu_item"
      :class="{ 'context-menu_item--danger': item.danger }"
      @click="select(item)"
    >{{ item.label }}</button>
  </div>
</template>

<script setup>
import { ref, reactive, nextTick, onBeforeUnmount } from 'vue'

const VIEWPORT_MARGIN = 8

const open = ref(false)
const items = ref([])
const menuEl = ref(null)
// Unsichtbar, bis die echte Größe gemessen und die Position berechnet ist
const menuStyle = reactive({ top: '0px', left: '0px', visibility: 'hidden' })

let returnFocusEl = null

/**
 * @param {{ clientX: number, clientY: number, preventDefault?: Function, fromKeyboard?: boolean }} event
 * @param {{ label: string, onClick?: Function, danger?: boolean }[]} menuItems
 */
async function openMenu(event, menuItems) {
  event.preventDefault?.()
  if (!menuItems?.length) return

  // Fokus nach dem Schließen zurückgeben — bei Tastatur an das auslösende Element
  returnFocusEl = event.fromKeyboard ? document.activeElement : null

  items.value = menuItems
  menuStyle.visibility = 'hidden'
  open.value = true
  addListeners()

  await nextTick()
  const el = menuEl.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const maxLeft = window.innerWidth - rect.width - VIEWPORT_MARGIN
  const maxTop = window.innerHeight - rect.height - VIEWPORT_MARGIN
  menuStyle.left = `${Math.max(VIEWPORT_MARGIN, Math.min(event.clientX, maxLeft))}px`
  menuStyle.top = `${Math.max(VIEWPORT_MARGIN, Math.min(event.clientY, maxTop))}px`
  menuStyle.visibility = 'visible'
  focusItem(0)
}

function closeMenu({ restoreFocus = false } = {}) {
  if (!open.value) return
  open.value = false
  removeListeners()
  if (restoreFocus && returnFocusEl?.isConnected) returnFocusEl.focus()
  returnFocusEl = null
}

function itemButtons() {
  return [...(menuEl.value?.querySelectorAll('[role="menuitem"]') ?? [])]
}

function focusItem(index) {
  // preventScroll: ein Scrollen würde das Menü sofort wieder schließen
  itemButtons()[index]?.focus({ preventScroll: true })
}

function onMenuKeydown(event) {
  const buttons = itemButtons()
  const current = buttons.indexOf(document.activeElement)
  const last = buttons.length - 1
  const target = {
    ArrowDown: current >= last ? 0 : current + 1,
    ArrowUp: current <= 0 ? last : current - 1,
    Home: 0,
    End: last,
  }[event.key]
  if (target !== undefined) {
    event.preventDefault()
    focusItem(target)
  } else if (event.key === 'Tab') {
    // Tab verlässt das Menü — dann einfach schließen, der Fokus wandert normal weiter
    closeMenu()
  }
}

// ── Schließen: außerhalb tippen/klicken, Esc, Scrollen, Größe ändern ────────

// pointerdown statt mousedown: Safari auf iOS meldet beim Tippen auf nicht
// klickbare Flächen oft kein mousedown, das Menü bliebe sonst offen
function onPointerDownOutside(event) {
  if (menuEl.value?.contains(event.target)) return
  closeMenu()
}

// In der Capture-Phase und mit preventDefault: Esc soll nur das Menü schließen,
// nicht zusätzlich das <dialog>-Modal, in dem es geöffnet wurde
function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    closeMenu({ restoreFocus: true })
  }
}

// Das Menü ist fest positioniert und würde beim Scrollen von seinem Eintrag wegschweben
function onScroll(event) {
  if (menuEl.value?.contains(event.target)) return
  closeMenu()
}

function onResize() {
  closeMenu()
}

function addListeners() {
  document.addEventListener('pointerdown', onPointerDownOutside, true)
  document.addEventListener('keydown', onKeydown, true)
  window.addEventListener('scroll', onScroll, true)
  window.addEventListener('resize', onResize)
}

function removeListeners() {
  document.removeEventListener('pointerdown', onPointerDownOutside, true)
  document.removeEventListener('keydown', onKeydown, true)
  window.removeEventListener('scroll', onScroll, true)
  window.removeEventListener('resize', onResize)
}

function select(item) {
  closeMenu({ restoreFocus: true })
  item.onClick?.()
}

onBeforeUnmount(removeListeners)

defineExpose({ open: openMenu, close: closeMenu })
</script>
