import { onMounted, onBeforeUnmount, watch } from 'vue'

/**
 * Masonry für ein CSS-Grid mit 1px hohen Zeilen (grid-auto-rows: 1px;
 * grid-auto-flow: row dense; row-gap: 0): Jedes Kind bekommt
 * grid-row-end: span <Höhe + Abstand>, dadurch rutscht jede Karte in die gerade
 * kürzere Spalte. Ändert sich eine Höhe (Bilder, Karte, neue Inhalte) oder kommen
 * Kinder dazu, wird neu verteilt.
 *
 * Nur aktiv, solange `enabled()` true ist und `media` passt — sonst werden die
 * Spans entfernt und das Layout bleibt beim normalen Fluss (z.B. einspaltig mobil).
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef
 * @param {{ gap?: number, media?: string, enabled?: () => boolean }} options
 */
export function useMasonry(containerRef, { gap = 25, media = '(min-width: 1000px)', enabled = () => true } = {}) {
  let resizeObserver = null
  let mutationObserver = null
  let mediaQuery = null
  let frame = 0

  const active = () => enabled() && !!mediaQuery?.matches

  function layout() {
    frame = 0
    const el = containerRef.value
    if (!el) return
    const on = active()
    for (const child of el.children) {
      child.style.gridRowEnd = on ? `span ${Math.ceil(child.getBoundingClientRect().height + gap)}` : ''
    }
  }

  function schedule() {
    if (!frame) frame = requestAnimationFrame(layout)
  }

  // Alle aktuellen Kinder beobachten (neu nach jeder Änderung der Kinderliste)
  function observeChildren() {
    resizeObserver.disconnect()
    for (const child of containerRef.value?.children ?? []) resizeObserver.observe(child)
    schedule()
  }

  function connect(el) {
    mutationObserver.disconnect()
    if (!el) return
    mutationObserver.observe(el, { childList: true })
    observeChildren()
  }

  onMounted(() => {
    mediaQuery = window.matchMedia(media)
    mediaQuery.addEventListener('change', schedule)
    resizeObserver = new ResizeObserver(schedule)
    mutationObserver = new MutationObserver(observeChildren)
    connect(containerRef.value)
  })

  // Container kann per v-if erst später erscheinen
  watch(containerRef, el => { if (mutationObserver) connect(el) })
  watch(enabled, schedule)

  onBeforeUnmount(() => {
    if (frame) cancelAnimationFrame(frame)
    resizeObserver?.disconnect()
    mutationObserver?.disconnect()
    mediaQuery?.removeEventListener('change', schedule)
  })
}
