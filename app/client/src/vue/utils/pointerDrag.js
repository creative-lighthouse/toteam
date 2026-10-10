import { ref, onBeforeUnmount } from 'vue'

// Drag & Drop mit Pointer-Events — funktioniert mit Maus und Touch (natives
// HTML-Drag&Drop kann auf Touch-Geräten nicht zuverlässig ziehen).
//
// - Ziehbare Elemente rufen `start(event, payload)` in @pointerdown auf. Mit der
//   Maus reicht die ganze Karte; bei Touch sollte das nur ein Griff mit
//   `touch-action: none` tun, damit man die Seite weiterhin scrollen kann.
// - Ablageziele tragen `data-drop-zone="<id>"`; `onDrop(payload, zoneId, ratio)` wird
//   beim Loslassen über einem Ziel aufgerufen. `ratio` (auch live als `overRatio`)
//   ist die senkrechte Position des Zeigers im Ziel von 0 (oben) bis 1 (unten) —
//   z.B. für "davor / hinein / dahinter" beim Sortieren.
// - Während des Ziehens folgt eine Kopie des Elements dem Zeiger; nahe am oberen
//   bzw. unteren Rand scrollt die Seite mit.

const DRAG_THRESHOLD = 6 // px, bevor aus einem Klick ein Ziehen wird
const SCROLL_EDGE = 80 // px Abstand zum Rand, ab dem mitgescrollt wird
const SCROLL_SPEED = 14 // px pro Frame am äußersten Rand

/**
 * @param {{ onDrop: (payload: any, zoneId: string, ratio: number) => void, bottomInset?: () => number }} options
 *   bottomInset: Höhe fester Leisten am unteren Rand (Menü, Tab-Leiste) fürs Mitscrollen
 */
export function usePointerDrag({ onDrop, bottomInset = () => 0 }) {
  const dragging = ref(null) // payload des gezogenen Elements
  const overZone = ref(null) // ID des Ziels unter dem Zeiger
  const overRatio = ref(0) // senkrechte Zeigerposition im Ziel (0 = oben, 1 = unten)

  let sourceEl = null
  let ghost = null
  let startX = 0
  let startY = 0
  let offsetX = 0
  let offsetY = 0
  let pointerY = 0
  let pendingPayload = null
  let scrollFrame = null

  function start(event, payload) {
    if (event.button !== undefined && event.button !== 0) return
    // Klicks auf Buttons/Auswahlfelder in der Karte nicht als Ziehen werten
    if (event.target.closest('button, select, input, a, [data-no-drag]')) return
    // Bei Touch nur über den Griff ziehen — sonst würde jedes Scrollen über der Karte ein Ziehen starten
    if (event.pointerType === 'touch' && !event.target.closest('[data-drag-handle]')) return
    sourceEl = event.currentTarget
    pendingPayload = payload
    startX = event.clientX
    startY = event.clientY
    const rect = sourceEl.getBoundingClientRect()
    offsetX = event.clientX - rect.left
    offsetY = event.clientY - rect.top
    window.addEventListener('pointermove', onMove, { passive: false })
    window.addEventListener('pointerup', onUp)
    window.addEventListener('pointercancel', cancel)
  }

  function beginDrag() {
    dragging.value = pendingPayload
    const rect = sourceEl.getBoundingClientRect()
    ghost = sourceEl.cloneNode(true)
    ghost.classList.add('pointer-drag-ghost')
    ghost.setAttribute('aria-hidden', 'true')
    Object.assign(ghost.style, {
      position: 'fixed',
      left: '0',
      top: '0',
      width: `${rect.width}px`,
      margin: '0',
      pointerEvents: 'none',
      zIndex: '1000',
    })
    document.body.appendChild(ghost)
    document.body.classList.add('is-pointer-dragging')
    scrollLoop()
  }

  function onMove(event) {
    pointerY = event.clientY
    if (!dragging.value) {
      if (Math.hypot(event.clientX - startX, event.clientY - startY) < DRAG_THRESHOLD) return
      beginDrag()
    }
    event.preventDefault()
    ghost.style.transform = `translate(${event.clientX - offsetX}px, ${event.clientY - offsetY}px) rotate(1.5deg)`
    const zoneEl = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-drop-zone]')
    overZone.value = zoneEl?.dataset.dropZone ?? null
    if (zoneEl) {
      const rect = zoneEl.getBoundingClientRect()
      overRatio.value = rect.height ? Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)) : 0.5
    }
  }

  function onUp() {
    const payload = dragging.value
    const zone = overZone.value
    const ratio = overRatio.value
    cleanup()
    if (payload && zone) onDrop(payload, zone, ratio)
  }

  function cancel() {
    cleanup()
  }

  function scrollLoop() {
    if (!dragging.value) return
    const bottom = window.innerHeight - bottomInset()
    let delta = 0
    if (pointerY < SCROLL_EDGE) {
      delta = -SCROLL_SPEED * (1 - pointerY / SCROLL_EDGE)
    } else if (pointerY > bottom - SCROLL_EDGE) {
      delta = SCROLL_SPEED * Math.min(1, (pointerY - (bottom - SCROLL_EDGE)) / SCROLL_EDGE)
    }
    if (delta) window.scrollBy(0, delta)
    scrollFrame = requestAnimationFrame(scrollLoop)
  }

  function cleanup() {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
    window.removeEventListener('pointercancel', cancel)
    if (scrollFrame) cancelAnimationFrame(scrollFrame)
    scrollFrame = null
    ghost?.remove()
    ghost = null
    document.body.classList.remove('is-pointer-dragging')
    dragging.value = null
    overZone.value = null
    overRatio.value = 0
    sourceEl = null
    pendingPayload = null
  }

  onBeforeUnmount(cleanup)

  return { start, dragging, overZone, overRatio }
}
