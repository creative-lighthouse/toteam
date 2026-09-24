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
      <span v-if="label" class="member-picker_label">{{ label }}</span>
      <div class="member-picker_mode-toggle">
        <button
          type="button"
          class="member-picker_mode-btn"
          :class="{ 'member-picker_mode-btn--active': mode === 'all' }"
          @click="setMode('all')"
        >Alle</button>
        <button
          type="button"
          class="member-picker_mode-btn"
          :class="{ 'member-picker_mode-btn--active': mode === 'individual' }"
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
            :alt="m.Name"
            img-class="member-picker_chip-avatar"
            placeholder-class="member-picker_chip-avatar--placeholder"
          />
          {{ m.Name }}
          <button
            type="button"
            class="member-picker_chip-remove"
            aria-label="Entfernen"
            @click="toggle(m.ID)"
          >×</button>
        </span>
      </div>

      <div class="member-picker_search-wrap">
        <input
          v-model="query"
          type="text"
          class="input member-picker_search"
          placeholder="Name eingeben, um Personen hinzuzufügen…"
          @focus="dropdownOpen = true"
          @blur="dropdownOpen = false"
          @keydown.enter.prevent="addFirstMatch"
          @keydown.escape="dropdownOpen = false"
        >
        <ul v-if="dropdownOpen && matches.length" class="member-picker_dropdown">
          <li v-for="m in matches" :key="m.ID">
            <button type="button" @mousedown.prevent="toggle(m.ID)">{{ m.Name }}</button>
          </li>
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
            :alt="selectedMember.Name"
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
          type="text"
          class="input member-picker_search"
          :placeholder="selectedMember ? 'Andere Person suchen…' : 'Person suchen…'"
          :disabled="disabled"
          @focus="dropdownOpen = true"
          @blur="dropdownOpen = false"
          @keydown.enter.prevent="addFirstMatch"
          @keydown.escape="dropdownOpen = false"
        >
        <ul v-if="dropdownOpen && matches.length" class="member-picker_dropdown">
          <li v-for="m in matches" :key="m.ID">
            <button type="button" @mousedown.prevent="toggle(m.ID)">
              {{ m.Name }}<template v-if="pinSelf && m.ID === selfId"> (Du)</template>
            </button>
          </li>
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
        placeholder="Person suchen…"
        aria-label="Person suchen"
      >

      <div class="member-picker_list">
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
            :alt="m.Name"
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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'

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
const inputName = `member-picker-${Math.random().toString(36).slice(2)}`

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

function addFirstMatch() {
  if (matches.value.length) {
    toggle(matches.value[0].ID)
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
      query.value = ''
      searchInput.value?.blur()
    }
  }
}

// Suchfeld direkt bereit, z. B. wenn der Picker in einem Dropdown aufklappt
onMounted(() => {
  if (props.autofocus) searchInput.value?.focus()
})
</script>
