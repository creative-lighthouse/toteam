import { nextTick } from 'vue'

// Morph per View Transitions API: ein angeklicktes Element (z.B. eine Terminkarte)
// verwandelt sich in das daraufhin geöffnete Modal. Ohne Browser-Unterstützung
// oder bei "Bewegung reduzieren" wird `update` einfach direkt ausgeführt — das
// Modal öffnet dann mit seiner normalen Einblend-Animation.

const MORPH_NAME = 'app-morph'
// Solange diese Klasse am <html> hängt, ist die eigene Einblend-Animation der
// Modals aus (siehe AppModal.scss), sonst liefen zwei Animationen gleichzeitig
const MORPH_CLASS = 'is-morphing'
// Bleibt am gemorphten Modal, bis es wieder geöffnet wird (AppModal.open()
// entfernt sie). Würde nur die <html>-Klasse wieder entfernt, startete der
// Browser die Einblend-Animation des bereits offenen Modals ein zweites Mal.
export const MORPHED_MODAL_CLASS = 'app-modal--morphed'

/** Läuft gerade ein Morph? (AppModal verzichtet dann auf seine Schließ-Kopie) */
export function isMorphing() {
  return document.documentElement.classList.contains(MORPH_CLASS)
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

function lastOpenModal() {
  const dialogs = document.querySelectorAll('dialog.app-modal[open]')
  return dialogs[dialogs.length - 1] ?? null
}

/**
 * @param {HTMLElement|null} sourceEl  Element, aus dem gemorpht wird
 * @param {() => (void|Promise<void>)} update  Zustandsänderung, die das Modal öffnet
 */
export async function morphIntoModal(sourceEl, update) {
  if (!sourceEl || !document.startViewTransition || prefersReducedMotion()) {
    await update()
    return
  }

  const root = document.documentElement
  root.classList.add(MORPH_CLASS)
  // Name nur am angeklickten Element — er darf pro Zustand nur einmal vorkommen
  sourceEl.style.viewTransitionName = MORPH_NAME
  let target = null

  const transition = document.startViewTransition(async () => {
    sourceEl.style.viewTransitionName = ''
    await update()
    await nextTick() // Vue rendert das Modal, onMounted ruft showModal()
    target = lastOpenModal()
    if (target) {
      target.classList.add(MORPHED_MODAL_CLASS)
      target.style.viewTransitionName = MORPH_NAME
    }
  })

  try {
    await transition.finished
  } catch {
    // Übergang abgebrochen (z.B. schneller zweiter Klick) — Zustand stimmt trotzdem
  } finally {
    sourceEl.style.viewTransitionName = ''
    if (target) target.style.viewTransitionName = ''
    root.classList.remove(MORPH_CLASS)
  }
}
