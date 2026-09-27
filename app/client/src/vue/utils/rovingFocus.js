// Direktive für Listen mit vielen fokussierbaren Einträgen (z.B. Teilnehmerkarten):
// Die ganze Liste ist nur ein Tab-Stopp, Pfeiltasten wechseln zwischen den
// Einträgen, Pos1/Ende springen an Anfang/Ende. Beim nächsten Tab in die Liste
// landet man wieder auf dem zuletzt besuchten Eintrag.
//
//   <div class="participants-list" v-roving-focus="{ selector: '.participant', label: 'Teilnehmer' }">
//
// Kombinierbar mit v-context-menu auf den Einträgen (Menütaste/Umschalt+F10).
// Mit dem Wert null ist die Direktive aus (z.B. bei Radio-Buttons, die das nativ können).
// `role: null` lässt die Rolle des Containers unangetastet (setzt der Aufrufer selbst).

const KEYS = {
  ArrowDown: 1,
  ArrowRight: 1,
  ArrowUp: -1,
  ArrowLeft: -1,
}

function items(el, state) {
  // Deaktivierte Einträge (z.B. Checkboxen bei "Alle") lassen sich nicht fokussieren
  return [...el.querySelectorAll(state.selector)].filter(item => !item.disabled)
}

/** Genau ein Eintrag mit tabindex 0, alle anderen -1 */
function apply(el, state) {
  const list = items(el, state)
  if (!list.length) return
  if (!list.includes(state.current)) state.current = list[0]
  for (const item of list) {
    const value = item === state.current ? '0' : '-1'
    if (item.getAttribute('tabindex') !== value) item.setAttribute('tabindex', value)
  }
}

export const vRovingFocus = {
  mounted(el, binding) {
    if (!binding.value) return
    const { selector, label = '', role = 'group' } = binding.value
    const state = { selector, current: null }

    // Gruppe statt Liste: die Listen enthalten Zwischenüberschriften ("Zugesagt (3)"),
    // die in einer ARIA-Liste von manchen Screenreadern verschluckt würden
    if (role) el.setAttribute('role', role)
    if (label) el.setAttribute('aria-label', label)

    state.onKeydown = (event) => {
      const list = items(el, state)
      const index = list.indexOf(event.target)
      if (index === -1) return // z.B. ein Button innerhalb einer Karte
      let target
      if (event.key in KEYS) {
        target = Math.min(list.length - 1, Math.max(0, index + KEYS[event.key]))
      } else if (event.key === 'Home') {
        target = 0
      } else if (event.key === 'End') {
        target = list.length - 1
      } else {
        return
      }
      event.preventDefault()
      state.current = list[target]
      apply(el, state)
      list[target].focus()
    }

    // Maus/Screenreader-Fokus auf einem Eintrag merken
    state.onFocusIn = (event) => {
      const item = event.target.closest?.(state.selector)
      if (item && el.contains(item) && item !== state.current) {
        state.current = item
        apply(el, state)
      }
    }

    // Einträge kommen und gehen (neue Antworten, Umgruppieren) — tabindex nachziehen
    state.observer = new MutationObserver(() => apply(el, state))
    state.observer.observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['tabindex', 'disabled'] })

    el.addEventListener('keydown', state.onKeydown)
    el.addEventListener('focusin', state.onFocusIn)
    el._rovingFocus = state
    apply(el, state)
  },

  updated(el) {
    if (el._rovingFocus) apply(el, el._rovingFocus)
  },

  unmounted(el) {
    const state = el._rovingFocus
    if (!state) return
    state.observer.disconnect()
    el.removeEventListener('keydown', state.onKeydown)
    el.removeEventListener('focusin', state.onFocusIn)
    delete el._rovingFocus
  },
}
