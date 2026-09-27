<template>
  <div class="member-picker">
    <!-- Mehrfachauswahl mit "Alle"-Umschalter: klare Wahl zwischen "alle
         Mitglieder" und "einzelne per Suche", damit bei großen Organisationen
         (z.B. 100+ Mitglieder) nicht immer die komplette Liste angezeigt
         werden muss. -->
    <div
      v-if="multiple && showSelectAll"
      class="member-picker_toolbar"
      :class="{ 'member-picker_toolbar--with-label': label }"
    >
      <span v-if="label" :id="`${uid}-label`" class="member-picker_label">{{ label }}</span>
      <div class="member-picker_mode-toggle" role="group" :aria-labelledby="label ? `${uid}-label` : undefined" :aria-label="label ? undefined : 'Auswahl'">
        <button
          type="button"
          class="member-picker_mode-btn"
          :class="{ 'member-picker_mode-btn--active': mode === 'all' }"
          :aria-pressed="mode === 'all'"
          @click="setMode('all')"
        >Alle</button>
        <button
          type="button"
          class="member-picker_mode-btn"
          :class="{ 'member-picker_mode-btn--active': mode === 'individual' }"
          :aria-pressed="mode === 'individual'"
          @click="setMode('individual')"
        >Einzeln</button>
      </div>
    </div>

    <p v-if="multiple && showSelectAll && mode === 'all'" class="member-picker_all-hint">
      Alle {{ members.length }} Mitglieder ausgewählt.
    </p>

    <template v-else-if="multiple && showSelectAll">
      <!-- Individualauswahl: ausgewählte Personen als Chips + Suche zum
           Hinzufügen, statt die komplette (ggf. sehr lange) Mitgliederliste
           auf einmal als Checkboxen zu zeigen. -->
      <div v-if="selectedMembers.length" class="member-picker_chips">
        <span v-for="m in selectedMembers" :key="m.ID" class="member-picker_chip">
          <AppAvatar
            :src="m.Avatar"
            alt=""
            img-class="member-picker_chip-avatar"
            placeholder-class="member-picker_chip-avatar--placeholder"
          />
          {{ m.Name }}
          <button
            type="button"
            class="member-picker_chip-remove"
            :aria-label="`${m.Name} entfernen`"
            @click="toggle(m.ID)"
          >×</button>
        </span>
      </div>

      <div class="member-picker_search-wrap">
        <input
          v-model="query"
          type="search"
          class="input member-picker_search"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          data-1p-ignore
          placeholder="Personen suchen und hinzufügen…"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="listOpen"
          :aria-controls="`${uid}-listbox`"
          :aria-activedescendant="listOpen && matches[activeIndex] ? optionId(matches[activeIndex]) : undefined"
          :aria-label="searchLabel"
          @focus="dropdownOpen = true"
          @blur="dropdownOpen = false"
          @input="onSearchInput"
          @keydown="onSearchKeydown"
        >
        <!-- Combobox: Fokus bleibt im Suchfeld, der markierte Treffer per aria-activedescendant -->
        <ul v-if="listOpen" :id="`${uid}-listbox`" class="member-picker_dropdown" role="listbox" :aria-label="searchLabel">
          <li
            v-for="(m, index) in matches"
            :id="optionId(m)"
            :key="m.ID"
            role="option"
            class="member-picker_dropdown-option"
            :class="{ 'member-picker_dropdown-option--active': index === activeIndex }"
            :aria-selected="index === activeIndex"
            @mousedown.prevent="toggle(m.ID)"
            @mousemove="activeIndex = index"
          >{{ m.Name }}</li>
        </ul>
        <p v-else-if="dropdownOpen && query && !matches.length" class="member-picker_dropdown-empty">
          Keine Treffer
        </p>
      </div>
    </template>

    <!-- Kompakte Einzelauswahl (dropdown): gewählte Person als Chip + Suche
         mit ausklappendem Dropdown statt der kompletten Liste, z.B. für den
         Verantwortlichen im Aufgaben-Formular. -->
    <template v-else-if="!multiple && dropdown">
      <div v-if="selectedMember" class="member-picker_chips">
        <span class="member-picker_chip">
          <AppAvatar
            :src="selectedMember.Avatar"
            alt=""
            img-class="member-picker_chip-avatar"
            placeholder-class="member-picker_chip-avatar--placeholder"
          />
          {{ selectedMember.Name }}<template v-if="pinSelf && selectedMember.ID === selfId"> (Du)</template>
        </span>
      </div>

      <div class="member-picker_search-wrap">
        <input
          ref="searchInput"
          v-model="query"
          type="search"
          class="input member-picker_search"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          data-1p-ignore
          :placeholder="selectedMember ? 'Andere Person suchen…' : 'Person suchen…'"
          :disabled="disabled"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="listOpen"
          :aria-controls="`${uid}-listbox`"
          :aria-activedescendant="listOpen && matches[activeIndex] ? optionId(matches[activeIndex]) : undefined"
          :aria-label="searchLabel"
          @focus="dropdownOpen = true"
          @blur="dropdownOpen = false"
          @input="onSearchInput"
          @keydown="onSearchKeydown"
        >
        <ul v-if="listOpen" :id="`${uid}-listbox`" class="member-picker_dropdown" role="listbox" :aria-label="searchLabel">
          <li
            v-for="(m, index) in matches"
            :id="optionId(m)"
            :key="m.ID"
            role="option"
            class="member-picker_dropdown-option"
            :class="{ 'member-picker_dropdown-option--active': index === activeIndex }"
            :aria-selected="index === activeIndex"
            @mousedown.prevent="toggle(m.ID)"
            @mousemove="activeIndex = index"
          >{{ m.Name }}<template v-if="pinSelf && m.ID === selfId"> (Du)</template></li>
        </ul>
        <p v-else-if="dropdownOpen && query && !matches.length" class="member-picker_dropdown-empty">
          Keine Treffer
        </p>
      </div>
    </template>

    <template v-else>
      <input
        v-if="searchable"
        ref="searchInput"
        v-model="query"
        type="search"
        class="input member-picker_search"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        data-1p-ignore
        placeholder="Person suchen…"
        aria-label="Person suchen"
      >

      <!--
        Einzelauswahl (Radios): ein Tab-Stopp, Pfeiltasten wählen nativ.
        Mehrfachauswahl (Checkboxen): ein Tab-Stopp über v-roving-focus,
        Pfeiltasten wechseln, Leertaste hakt an/ab.
      -->
      <div
        v-roving-focus="multiple ? { selector: 'input[type=checkbox]', role: null } : null"
        class="member-picker_list"
        :role="multiple ? 'group' : 'radiogroup'"
        :aria-label="label || (multiple ? 'Personen' : 'Person')"
      >
        <label
          v-for="m in sortedFilteredMembers"
          :key="m.ID"
          class="member-picker_option"
          :class="{ 'member-picker_option--self': pinSelf && m.ID === selfId }"
        >
          <input
            :type="multiple ? 'checkbox' : 'radio'"
            :name="inputName"
            :value="m.ID"
            :checked="isSelected(m.ID)"
            @change="toggle(m.ID)"
          >
          <AppAvatar
            :src="m.Avatar"
            alt=""
            img-class="member-picker_avatar"
            placeholder-class="member-picker_avatar--placeholder"
          />
          <span class="member-picker_name">
            {{ m.Name }}<template v-if="pinSelf && m.ID === selfId"> (Du)</template>
          </span>
        </label>

        <p v-if="!sortedFilteredMembers.length" class="member-picker_empty">Keine Personen gefunden.</p>
      </div>
    </template>

    <!-- Zahl der Treffer für Screenreader (nur bei den Such-Dropdowns). Bewusst
         außerhalb der v-if/v-else-Kette oben, sonst hängt sich deren letzter Zweig daran. -->
    <span v-if="usesCombobox" class="member-picker_sr-only" aria-live="polite">{{ resultAnnouncement }}</span>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import { vRovingFocus } from '@utils/rovingFocus'

const props = defineProps({
  // Liste der wählbaren Personen ({ ID, Name, Avatar }). Wird immer vom
  // Aufrufer übergeben (z.B. bereits nach Organisation gefiltert) statt
  // selbst zu laden.
  members: { type: Array, required: true },
  // Einzelauswahl: Number|null. Mehrfachauswahl (multiple): Array<Number>.
  modelValue: { type: [Number, String, Array], default: null },
  multiple: { type: Boolean, default: false },
  searchable: { type: Boolean, default: true },
  // Zeigt bei Mehrfachauswahl den "Alle"/"Einzeln"-Umschalter (siehe oben).
  showSelectAll: { type: Boolean, default: false },
  // Optionale Beschriftung, die links neben dem Alle/Einzeln-Umschalter steht
  // (spart die sonst separate <label>-Zeile darüber).
  label: { type: String, default: '' },
  // Zeigt die eigene Person zuerst in der Liste, mit "(Du)"-Hinweis.
  pinSelf: { type: Boolean, default: false },
  selfId: { type: Number, default: null },
  // Fokussiert das Suchfeld beim Einblenden (Einzelauswahl mit searchable)
  autofocus: { type: Boolean, default: false },
  // Einzelauswahl als Chip + Such-Dropdown statt kompletter Liste (siehe oben)
  dropdown: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const query = ref('')
const searchInput = ref(null)
const dropdownOpen = ref(false)
const uid = `member-picker-${Math.random().toString(36).slice(2)}`
const inputName = uid

const allSelected = computed(() =>
  props.members.length > 0 && (props.modelValue?.length ?? 0) === props.members.length
)

// "Alle"/"Einzeln"-Modus: startet passend zum aktuellen Auswahlzustand
// (z.B. wenn der Aufrufer per autoSelectAll bereits alle vorausgewählt hat),
// bleibt danach eine reine UI-Entscheidung unabhängig von der Auswahl.
const mode = ref(allSelected.value ? 'all' : 'individual')
let modeInitialized = allSelected.value

watch(allSelected, (val) => {
  if (!modeInitialized && val) {
    mode.value = 'all'
    modeInitialized = true
  }
})

function setMode(next) {
  mode.value = next
  modeInitialized = true
  if (next === 'all') {
    emit('update:modelValue', props.members.map(m => m.ID))
  } else {
    emit('update:modelValue', [])
  }
}

// Mit pinSelf steht die eigene Person immer ganz oben — auch während der Suche,
// damit man sich selbst jederzeit mit einem Klick auswählen kann
const sortedFilteredMembers = computed(() => {
  const q = query.value.trim().toLowerCase()
  const self = props.pinSelf && props.selfId != null ? props.members.find(m => m.ID === props.selfId) : null
  const others = props.members.filter(m => m !== self && (!q || m.Name?.toLowerCase().includes(q)))
  return self ? [self, ...others] : others
})

const selectedMembers = computed(() =>
  props.members.filter(m => (props.modelValue || []).includes(m.ID))
)

const selectedMember = computed(() =>
  props.multiple ? null : props.members.find(m => m.ID === props.modelValue) ?? null
)

// Dropdown-Treffer: noch nicht gewählte Personen passend zur Suche; mit
// pinSelf steht die eigene Person zuerst
const matches = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = props.members
    .filter(m => !isSelected(m.ID))
    .filter(m => !q || m.Name?.toLowerCase().includes(q))
  if (props.pinSelf && props.selfId != null) {
    const selfIdx = list.findIndex(m => m.ID === props.selfId)
    if (selfIdx > 0) list.unshift(...list.splice(selfIdx, 1))
  }
  return list.slice(0, 30)
})

// ── Combobox (Such-Dropdowns): Tastatur und Screenreader ──────────────────────

const usesCombobox = computed(() => (props.multiple && props.showSelectAll && mode.value === 'individual') || (!props.multiple && props.dropdown))
const listOpen = computed(() => dropdownOpen.value && matches.value.length > 0)
const activeIndex = ref(0)
const searchLabel = computed(() => props.label
  ? `${props.label}: Person suchen`
  : (props.multiple ? 'Personen suchen und hinzufügen' : 'Person suchen'))

const optionId = m => `${uid}-option-${m.ID}`

// Neue Treffer → Markierung wieder auf den ersten
watch(matches, () => { activeIndex.value = 0 })

const resultAnnouncement = computed(() => {
  if (!dropdownOpen.value || !query.value.trim()) return ''
  const n = matches.value.length
  return n === 0 ? 'Keine Treffer' : n === 1 ? '1 Treffer' : `${n} Treffer`
})

function onSearchInput() {
  dropdownOpen.value = true
}

function moveActive(delta) {
  const n = matches.value.length
  if (!n) return
  activeIndex.value = (activeIndex.value + delta + n) % n
  // Markierten Treffer sichtbar halten
  nextTick(() => document.getElementById(optionId(matches.value[activeIndex.value]))?.scrollIntoView({ block: 'nearest' }))
}

function onSearchKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      if (!dropdownOpen.value) { dropdownOpen.value = true; return }
      moveActive(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      if (!dropdownOpen.value) { dropdownOpen.value = true; return }
      moveActive(-1)
      break
    case 'Enter':
      event.preventDefault()
      if (listOpen.value && matches.value[activeIndex.value]) toggle(matches.value[activeIndex.value].ID)
      break
    case 'Escape':
      if (dropdownOpen.value) {
        // Nur die Liste schließen, nicht zusätzlich das Modal drumherum
        event.preventDefault()
        event.stopPropagation()
        dropdownOpen.value = false
      }
      break
  }
}

function isSelected(id) {
  if (props.multiple) return (props.modelValue || []).includes(id)
  return props.modelValue === id
}

function toggle(id) {
  if (props.multiple) {
    const current = props.modelValue || []
    const next = current.includes(id) ? current.filter(x => x !== id) : [...current, id]
    emit('update:modelValue', next)
    query.value = ''
  } else {
    emit('update:modelValue', id)
    if (props.dropdown) {
      // Liste schließen, Fokus bleibt im Suchfeld (sonst fiele er für Tastatur-Nutzer heraus)
      query.value = ''
      dropdownOpen.value = false
    }
  }
}

// Suchfeld direkt bereit, z. B. wenn der Picker in einem Dropdown aufklappt
onMounted(() => {
  if (props.autofocus) searchInput.value?.focus()
})
</script>
