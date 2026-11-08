<!--
  Text, der zum Bearbeiten angeklickt wird (Titel, Beschreibung im Foto-Betrachter).
  Speichern mit Enter (einzeilig) bzw. Strg+Enter oder beim Verlassen des Felds,
  Esc verwirft. Ohne `editable` nur Anzeige (leer = nichts). `suggestions` bietet beim
  Tippen Vorschläge an (Datalist des Browsers).

    <PhotoInlineText :value="photo.Title" placeholder="Titel hinzufügen" tag="h2" editable @save="v => …" />
-->
<template>
  <component
    :is="tag"
    v-if="!editing && (value || editable)"
    v-bind="$attrs"
    class="photo-inline-text"
    :class="{
      'photo-inline-text--editable': editable,
      'photo-inline-text--empty': !value,
      'photo-inline-text--multiline': multiline,
    }"
  >
    <button
      v-if="editable"
      type="button"
      class="photo-inline-text_button"
      :aria-label="value ? `${label} bearbeiten` : placeholder"
      :disabled="disabled"
      @click="start"
    >{{ value || placeholder }}</button>
    <template v-else>{{ value }}</template>
  </component>

  <textarea
    v-else-if="editing && multiline"
    v-bind="$attrs"
    ref="inputEl"
    v-model="draft"
    class="photo-inline-text_input photo-inline-text_input--multiline"
    :aria-label="label"
    :placeholder="placeholder"
    :maxlength="maxlength"
    rows="3"
    @keydown="onKeydown"
    @blur="commit"
  />
  <input
    v-else-if="editing"
    v-bind="$attrs"
    ref="inputEl"
    v-model="draft"
    type="text"
    class="photo-inline-text_input"
    :class="`photo-inline-text_input--${tag}`"
    :aria-label="label"
    :placeholder="placeholder"
    :maxlength="maxlength"
    :list="suggestions.length ? `${uid}-suggestions` : undefined"
    @keydown="onKeydown"
    @blur="commit"
  >
  <datalist v-if="editing && !multiline && suggestions.length" :id="`${uid}-suggestions`">
    <option v-for="s in suggestions" :key="s" :value="s" />
  </datalist>
</template>

<script setup>
import { ref, nextTick } from 'vue'

// Wegen der Datalist hat die Komponente mehrere Wurzelelemente — Klassen usw. der Eltern
// gehen deshalb ausdrücklich an das jeweils sichtbare Element
defineOptions({ inheritAttrs: false })

const props = defineProps({
  value: { type: String, default: '' },
  // Name des Felds für Screenreader ("Titel", "Beschreibung")
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
  tag: { type: String, default: 'p' },
  multiline: { type: Boolean, default: false },
  editable: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  maxlength: { type: Number, default: 255 },
  // Vorschläge beim Tippen (nur einzeilig), z.B. bisher verwendete Copyright-Einträge
  suggestions: { type: Array, default: () => [] },
})

const uid = `photo-inline-${Math.random().toString(36).slice(2)}`

const emit = defineEmits(['save'])

const editing = ref(false)
const draft = ref('')
const inputEl = ref(null)
let cancelled = false

async function start() {
  draft.value = props.value || ''
  cancelled = false
  editing.value = true
  await nextTick()
  inputEl.value?.focus()
  inputEl.value?.select?.()
}

function commit() {
  if (!editing.value) return
  editing.value = false
  const next = draft.value.trim()
  if (!cancelled && next !== (props.value || '').trim()) emit('save', next)
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    // Nicht den ganzen Betrachter schließen
    event.preventDefault()
    event.stopPropagation()
    cancelled = true
    editing.value = false
  } else if (event.key === 'Enter' && (!props.multiline || event.ctrlKey || event.metaKey)) {
    event.preventDefault()
    commit()
  }
}
</script>
