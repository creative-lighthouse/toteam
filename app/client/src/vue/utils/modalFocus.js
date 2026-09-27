// Injection-Key: Funktion, die das Element liefert, auf das ein AppModal nach
// dem Schließen den Fokus setzt, wenn es kein auslösendes Element (mehr) gibt.
//
//   provide(MODAL_FOCUS_FALLBACK, () => headingEl.value)
export const MODAL_FOCUS_FALLBACK = Symbol('modalFocusFallback')
