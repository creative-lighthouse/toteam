<template>
  <Teleport to="body">
    <dialog ref="dialogEl" v-bind="$attrs" class="event-modal app-modal" @cancel.prevent="$emit('close')">
      <div class="dialog-content app-modal_content" @click.stop>

        <div class="app-modal_header">
          <slot name="header">
            <h2 class="hl2">{{ title }}</h2>
          </slot>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="$emit('close')">✕</AppIconButton>
        </div>

        <div v-if="tabs.length > 1" class="app-modal_tabs">
          <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            class="app-modal_tab"
            :class="{ 'app-modal_tab--active': t.id === tab }"
            @click="$emit('update:tab', t.id)"
          >{{ t.label }}</button>
        </div>

        <div class="app-modal_body">
          <slot />
        </div>

        <div v-if="$slots.actions" class="app-modal_actions">
          <slot name="actions" />
        </div>

      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import { MORPHED_MODAL_CLASS, isMorphing } from '@utils/viewTransition'

// Root ist ein <Teleport>, kein normales DOM-Element — Vues automatisches
// Attribute-/Class-Fallthrough greift dabei nicht (landet ansonsten ins
// Leere), deshalb wird $attrs (v.a. die vom Aufrufer übergebene Modifier-
// Klasse wie "money-account-modal") hier manuell auf das <dialog> gebunden.
defineOptions({ inheritAttrs: false })

defineProps({
  title: { type: String, default: '' },
  // Optionale Tabs { id, label }[]. Die Tableiste wird nur angezeigt, wenn
  // mehr als ein Tab übergeben wird — bei 0 oder 1 Tab(s) bleibt sie
  // ausgeblendet (z.B. wenn beim Bearbeiten nur noch ein Tab relevant ist).
  tabs: { type: Array, default: () => [] },
  tab: { type: [String, Number], default: null },
})

defineEmits(['close', 'update:tab'])

const dialogEl = ref(null)

const CLOSING_CLASS = 'app-modal--closing'
const CLOSE_DURATION = 180 // ms, passend zur Animation in AppModal.scss

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

// Spielt die Schließ-Animation auf `el` ab und ruft danach `done` auf. Der
// Timeout ist die Absicherung, falls `animationend` nicht feuert.
function animateOut(el, done) {
  if (prefersReducedMotion()) {
    done()
    return
  }
  let finished = false
  const finish = () => {
    if (finished) return
    finished = true
    el.removeEventListener('animationend', onEnd)
    done()
  }
  const onEnd = (event) => { if (event.target === el) finish() }
  el.addEventListener('animationend', onEnd)
  el.classList.add(CLOSING_CLASS)
  setTimeout(finish, CLOSE_DURATION + 80)
}

// Zählt Öffnen/Schließen mit: wird während der Schließ-Animation wieder
// geöffnet, darf das verzögerte close() den Dialog nicht mehr zumachen
let closeToken = 0

function open() {
  const el = dialogEl.value
  if (!el) return
  closeToken++
  el.classList.remove(CLOSING_CLASS, MORPHED_MODAL_CLASS)
  if (!el.open) el.showModal()
}

function close() {
  const el = dialogEl.value
  if (!el?.open || el.classList.contains(CLOSING_CLASS)) return
  const token = ++closeToken
  animateOut(el, () => {
    if (token !== closeToken) return
    el.close()
    el.classList.remove(CLOSING_CLASS)
  })
}

// Viele Modals werden nicht per close() geschlossen, sondern von der
// Elternkomponente per v-if entfernt — dann ist das <dialog> sofort weg. Damit
// auch dort animiert wird, bleibt eine statische Kopie für die Dauer der
// Schließ-Animation stehen und wird danach entfernt.
onBeforeUnmount(() => {
  const el = dialogEl.value
  // Beim Morph (z.B. Skeleton → Termin) übernimmt die View Transition den Übergang
  if (!el?.open || prefersReducedMotion() || isMorphing()) return
  const ghost = el.cloneNode(true)
  // Die Kopie erbt das open-Attribut — showModal() würde darauf einen Fehler
  // werfen und die Kopie als offenen, nicht-modalen Dialog stehen lassen
  ghost.removeAttribute('open')
  ghost.removeAttribute('id')
  ghost.setAttribute('aria-hidden', 'true')
  ghost.inert = true
  document.body.appendChild(ghost)
  try {
    ghost.showModal()
    // Scrollposition übernehmen, sonst springt der Inhalt der Kopie nach oben
    const body = el.querySelector('.app-modal_body')
    const ghostBody = ghost.querySelector('.app-modal_body')
    if (body && ghostBody) ghostBody.scrollTop = body.scrollTop
    animateOut(ghost, () => ghost.remove())
  } catch {
    ghost.remove()
  }
})

defineExpose({ open, close })
</script>
