// Direktive für Kontextmenüs: Rechtsklick, langes Drücken auf Touch-Geräten
// (Safari auf iPhone/iPad löst dabei kein `contextmenu` aus, Android schon)
// und Tastatur — Menütaste bzw. Umschalt+F10 auf dem fokussierten Element.
//
//   <div v-context-menu="canEdit ? (e => menu.open(e, items)) : null">
//
// Der Handler bekommt ein Event mit clientX/clientY/preventDefault (passend zu
// ContextMenu.open); per Tastatur zusätzlich `fromKeyboard: true`, damit das
// Menü den Fokus danach zurückgibt. Ist der Wert null, bleibt das normale
// Browser-Menü und das Element wird nicht zusätzlich fokussierbar gemacht.

const LONG_PRESS_MS = 500
const MOVE_TOLERANCE = 10 // px Fingerbewegung, ab der es als Scrollen gilt
const DEDUPE_MS = 800 // doppelte Auslöser (z.B. Android: langes Drücken + contextmenu) ignorieren

// Elemente, die ohnehin per Tab erreichbar sind
const FOCUSABLE = 'a[href], button, input, select, textarea, [tabindex]'

/** Macht das Element für Tastatur und Screenreader als "hat ein Menü" erkennbar */
function applyAccessibility(el, state) {
  const active = !!state.handler
  if (active && !state.addedTabindex && !el.matches(FOCUSABLE)) {
    el.setAttribute('tabindex', '0')
    state.addedTabindex = true
  } else if (!active && state.addedTabindex) {
    el.removeAttribute('tabindex')
    state.addedTabindex = false
  }
  if (active) {
    el.setAttribute('aria-haspopup', 'menu')
    el.setAttribute('aria-keyshortcuts', 'Shift+F10')
    el.dataset.contextMenu = ''
  } else {
    el.removeAttribute('aria-haspopup')
    el.removeAttribute('aria-keyshortcuts')
    delete el.dataset.contextMenu
  }
}

export const vContextMenu = {
  mounted(el, binding) {
    const state = {
      handler: binding.value,
      timer: null,
      startX: 0,
      startY: 0,
      firedAt: 0,
      addedTabindex: false,
    }

    const clear = () => {
      clearTimeout(state.timer)
      state.timer = null
    }

    const fire = (event) => {
      state.firedAt = Date.now()
      state.handler(event)
    }

    state.onContextMenu = (event) => {
      if (!state.handler) return
      event.preventDefault()
      // Verschachtelte Einträge (z.B. Gericht in einer Mahlzeit): nur das innerste Menü
      event.stopPropagation()
      if (Date.now() - state.firedAt < DEDUPE_MS) return
      fire(event)
    }

    // Menütaste bzw. Umschalt+F10 — nur wenn das Element selbst fokussiert ist
    state.onKeydown = (event) => {
      if (!state.handler || event.target !== el) return
      const isMenuKey = event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey)
      if (!isMenuKey) return
      event.preventDefault()
      event.stopPropagation()
      const rect = el.getBoundingClientRect()
      fire({
        clientX: rect.left + 16,
        clientY: rect.bottom,
        target: el,
        fromKeyboard: true,
        preventDefault() {},
      })
    }

    state.onTouchStart = (event) => {
      if (!state.handler || event.touches.length !== 1) return
      const touch = event.touches[0]
      state.startX = touch.clientX
      state.startY = touch.clientY
      clear()
      state.timer = setTimeout(() => {
        state.timer = null
        navigator.vibrate?.(10)
        fire({
          clientX: state.startX,
          clientY: state.startY,
          target: event.target,
          preventDefault() {},
        })
      }, LONG_PRESS_MS)
      // Verschachtelte Einträge: nur das innerste Element wertet den langen Druck aus
      event.stopPropagation()
    }

    state.onTouchMove = (event) => {
      if (!state.timer) return
      const touch = event.touches[0]
      if (Math.hypot(touch.clientX - state.startX, touch.clientY - state.startY) > MOVE_TOLERANCE) clear()
    }

    state.onTouchEnd = (event) => {
      clear()
      // Nach einem langen Druck keinen Klick auf z.B. einen Link darunter auslösen
      if (Date.now() - state.firedAt < DEDUPE_MS) event.preventDefault()
    }

    el.addEventListener('contextmenu', state.onContextMenu)
    el.addEventListener('keydown', state.onKeydown)
    el.addEventListener('touchstart', state.onTouchStart, { passive: true })
    el.addEventListener('touchmove', state.onTouchMove, { passive: true })
    el.addEventListener('touchend', state.onTouchEnd)
    el.addEventListener('touchcancel', clear)
    state.clear = clear
    el._contextMenu = state
    applyAccessibility(el, state)
  },

  updated(el, binding) {
    const state = el._contextMenu
    if (!state) return
    state.handler = binding.value
    applyAccessibility(el, state)
  },

  unmounted(el) {
    const state = el._contextMenu
    if (!state) return
    state.clear()
    el.removeEventListener('contextmenu', state.onContextMenu)
    el.removeEventListener('keydown', state.onKeydown)
    el.removeEventListener('touchstart', state.onTouchStart)
    el.removeEventListener('touchmove', state.onTouchMove)
    el.removeEventListener('touchend', state.onTouchEnd)
    el.removeEventListener('touchcancel', state.clear)
    delete el._contextMenu
  },
}

/**
 * Menüeintrag "Profil ansehen" für eine Teilnahme (ParticipantCard) — für alle
 * sichtbar, sofern die Person einen Benutzernamen (und damit ein Profil) hat.
 *
 * @param {import('vue-router').Router} router
 * @param {{ Username?: string|null }} participation
 */
export function profileMenuItem(router, participation) {
  if (!participation?.Username) return null
  return {
    label: 'Profil ansehen',
    onClick: () => router.push({ name: 'PublicProfile', params: { username: participation.Username } }),
  }
}
