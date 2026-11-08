<!--
  Suchleiste der Foto-Galerie mit Such-Helfern. Freitext sucht überall (Titel, Beschreibung,
  Personen, Charaktere, Räume, Schlagworte); mit Präfix nur in einer Art und nach genau diesem
  Namen: person:Max, charakter:Andrea, raum:Keller, schlagwort:"Mit Leerzeichen".
  Beim Tippen schlägt sie passende Tags vor — Auswahl setzt das Präfix automatisch.
  Gleicher Aufbau wie AppSearchBar (dieselben Klassen), plus Vorschlagsliste.

    <PhotoSearchBar v-model="search" :options="store.optionsByOrg[orgId]">
      <template #actions>…</template>
    </PhotoSearchBar>
-->
<template>
  <div class="app-search-bar photo-search-bar">
    <div class="app-search-bar_row">
      <div class="app-search-bar_field photo-search-bar_field">
        <svg class="app-search-bar_icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          ref="inputEl"
          type="search"
          class="app-search-bar_input"
          :value="modelValue"
          placeholder="Personen, Charaktere, Räume, Schlagworte suchen…"
          aria-label="Fotos durchsuchen"
          autocomplete="off"
          autocorrect="off"
          spellcheck="false"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="listOpen"
          :aria-controls="`${uid}-list`"
          :aria-activedescendant="listOpen && active >= 0 ? `${uid}-opt-${active}` : undefined"
          @input="onInput"
          @focus="focused = true"
          @blur="focused = false"
          @keydown="onKeydown"
        >

        <ul v-if="listOpen" :id="`${uid}-list`" class="photo-search-bar_list" role="listbox" aria-label="Vorschläge">
          <li
            v-for="(s, i) in suggestions"
            :id="`${uid}-opt-${i}`"
            :key="s.key"
            role="option"
            class="photo-search-bar_option"
            :class="{ 'photo-search-bar_option--active': i === active }"
            :aria-selected="i === active"
            @mousedown.prevent="choose(s)"
            @mousemove="active = i"
          >
            <span class="photo-search-bar_kind">{{ s.kindLabel }}</span>
            <span class="photo-search-bar_value">{{ s.label }}</span>
            <span v-if="s.hint" class="photo-search-bar_hint">{{ s.hint }}</span>
          </li>
        </ul>
      </div>
      <div v-if="$slots.actions" class="app-search-bar_actions">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  // Auswahl der Organisation aus GET /photos/options (people, characters, tags) — für Vorschläge
  options: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const MAX_SUGGESTIONS = 8
const uid = `photo-search-${Math.random().toString(36).slice(2)}`

// Präfix, das beim Auswählen eingesetzt wird, und welche Präfixe beim Tippen erkannt werden
// (dieselben wie im Backend, PhotosApiController::SEARCH_PREFIXES)
const KINDS = {
  people: { prefix: 'person', label: 'Person', plural: 'Personen', aliases: ['person', 'personen'] },
  characters: { prefix: 'charakter', label: 'Charakter', plural: 'Charaktere', aliases: ['charakter', 'character', 'charaktere', 'rolle'] },
  rooms: { prefix: 'raum', label: 'Raum', plural: 'Räume', aliases: ['raum', 'room'] },
  tags: { prefix: 'schlagwort', label: 'Schlagwort', plural: 'Schlagworte', aliases: ['schlagwort', 'tag'] },
}

const inputEl = ref(null)
const focused = ref(false)
const active = ref(-1)
// Nach einer Auswahl die Liste zu lassen, bis wieder getippt wird
const dismissed = ref(false)

// Der Begriff, der gerade getippt wird: alles nach dem letzten Leerzeichen außerhalb von Anführungszeichen
const currentToken = computed(() => {
  const text = props.modelValue
  let inQuotes = false
  let start = 0
  for (let i = 0; i < text.length; i++) {
    if (text[i] === '"') inQuotes = !inQuotes
    else if (text[i] === ' ' && !inQuotes) start = i + 1
  }
  const raw = text.slice(start)
  const match = raw.match(/^(\p{L}+):"?(.*?)"?$/u)
  const kind = match ? Object.keys(KINDS).find(k => KINDS[k].aliases.includes(match[1].toLowerCase())) : null
  return {
    start,
    raw,
    kind: kind ?? null,
    hasPrefix: !!kind,
    value: (kind ? match[2] : raw.replace(/"/g, '')).trim().toLocaleLowerCase('de'),
  }
})

function candidates(kind) {
  const o = props.options
  if (!o) return []
  if (kind === 'people') return o.people.map(p => ({ id: p.ID, label: p.Name, hint: p.IsGuest ? 'Gast' : '' }))
  if (kind === 'characters') return o.characters.map(c => ({ id: c.ID, label: c.Title, hint: c.Script || '' }))
  if (kind === 'rooms') {
    // Inventar-Räume und Räume auf Event-Lageplänen; gleiche Namen nur einmal vorschlagen
    const seen = new Set()
    return [
      ...(o.rooms ?? []).map(r => ({ id: `r${r.ID}`, label: r.Title, hint: '' })),
      ...(o.areas ?? []).map(a => ({ id: `a${a.ID}`, label: a.Title, hint: a.Plan || '' })),
    ].filter(r => !seen.has(r.label.toLowerCase()) && seen.add(r.label.toLowerCase()))
  }
  return o.tags.map(t => ({ id: t.ID, label: t.Title, hint: '' }))
}

const suggestions = computed(() => {
  const token = currentToken.value
  const kinds = token.hasPrefix ? [token.kind] : Object.keys(KINDS)

  // Leeres Feld bzw. gerade ein Leerzeichen getippt: die Präfixe als Hilfe anbieten
  if (!token.hasPrefix && !token.value) {
    return Object.entries(KINDS).map(([kind, def]) => ({
      key: `prefix-${kind}`, kind, kindLabel: 'Suchen nach', label: `${def.prefix}:`, hint: `nur ${def.plural}`, prefixOnly: true,
    }))
  }

  const list = []
  for (const kind of kinds) {
    for (const c of candidates(kind)) {
      const label = c.label.toLocaleLowerCase('de')
      if (!token.value || label.includes(token.value)) {
        list.push({ key: `${kind}-${c.id}`, kind, kindLabel: KINDS[kind].label, label: c.label, hint: c.hint, starts: label.startsWith(token.value) })
      }
    }
  }
  // Treffer am Anfang zuerst
  list.sort((a, b) => Number(b.starts) - Number(a.starts))
  return list.slice(0, MAX_SUGGESTIONS)
})

const listOpen = computed(() => focused.value && !dismissed.value && suggestions.value.length > 0)

watch(suggestions, () => { active.value = -1 })

function onInput(event) {
  dismissed.value = false
  emit('update:modelValue', event.target.value)
}

function choose(s) {
  const before = props.modelValue.slice(0, currentToken.value.start)
  if (s.prefixOnly) {
    emit('update:modelValue', `${before}${s.label}`)
  } else {
    const value = /\s/.test(s.label) ? `"${s.label}"` : s.label
    emit('update:modelValue', `${before}${KINDS[s.kind].prefix}:${value} `)
    dismissed.value = true
  }
  active.value = -1
  inputEl.value?.focus()
}

function onKeydown(event) {
  if (event.key === 'ArrowDown' && listOpen.value) {
    event.preventDefault()
    active.value = Math.min(active.value + 1, suggestions.value.length - 1)
  } else if (event.key === 'ArrowUp' && listOpen.value) {
    event.preventDefault()
    active.value = Math.max(active.value - 1, -1)
  } else if (event.key === 'Enter' && listOpen.value && active.value >= 0) {
    event.preventDefault()
    choose(suggestions.value[active.value])
  } else if (event.key === 'Escape' && listOpen.value) {
    event.preventDefault()
    dismissed.value = true
  }
}
</script>
