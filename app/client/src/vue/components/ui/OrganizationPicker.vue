<template>
  <div ref="rootEl" class="org-picker">
    <input
      v-if="searchable && orgs.length > 5"
      v-model="query"
      type="search"
      class="input org-picker_search"
      placeholder="Organisation suchen…"
      :aria-label="groupLabel ? `${groupLabel}: Organisation suchen` : 'Organisation suchen'"
      :aria-controls="`${inputName}-list`"
    >

    <!--
      Echte Radio-Buttons (Einzelauswahl) bzw. Checkboxen: Pfeiltasten wechseln
      bei Radios schon nativ, bei Checkboxen über v-roving-focus. Die Gruppe trägt den Namen aus `label` oder aus der
      <label>-Überschrift direkt davor (siehe onMounted).
    -->
    <!-- Mehrfachauswahl: ein Tab-Stopp, Pfeiltasten wechseln, Leertaste hakt an/ab -->
    <div
      :id="`${inputName}-list`"
      v-roving-focus="multiple ? { selector: 'input[type=checkbox]', role: null } : null"
      class="org-picker_list"
      :role="multiple ? 'group' : 'radiogroup'"
      :aria-label="label || undefined"
      :aria-labelledby="!label && labelledBy ? labelledBy : undefined"
    >
      <label v-if="allowAll" class="org-picker_option">
        <input
          :type="multiple ? 'checkbox' : 'radio'"
          :name="inputName"
          :checked="isAllSelected"
          @change="selectAll"
        >
        <span class="org-picker_name">Alle</span>
      </label>

      <label
        v-for="org in filteredOrgs"
        :key="org.ID"
        class="org-picker_option"
        :class="{ 'org-picker_option--disabled': allowAll && isAllSelected }"
      >
        <input
          :type="multiple ? 'checkbox' : 'radio'"
          :name="inputName"
          :value="org.ID"
          :checked="isSelected(org.ID)"
          :disabled="allowAll && isAllSelected"
          @change="toggle(org.ID)"
        >
        <AppOrgLogo :src="org.LogoURL" alt="" :name="org.Title" :size="24" aria-hidden="true" />
        <span class="org-picker_name">{{ org.Title }}</span>
      </label>

      <p v-if="!filteredOrgs.length" class="org-picker_empty" role="status">Keine Organisationen gefunden.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import { vRovingFocus } from '@utils/rovingFocus'

const props = defineProps({
  // Liste der wählbaren Organisationen ({ ID, Title, LogoURL }). Wird immer
  // vom Aufrufer übergeben (z.B. bereits nach Mitgliedschaft/Berechtigung
  // gefiltert) statt selbst aus dem Store zu lesen.
  orgs: { type: Array, required: true },
  // Einzelauswahl: Number|null. Mehrfachauswahl (multiple): Array<Number>.
  modelValue: { type: [Number, String, Array], default: null },
  multiple: { type: Boolean, default: false },
  searchable: { type: Boolean, default: true },
  // Zeigt eine zusätzliche "Alle"-Option, die die Einzelauswahl leert/deaktiviert
  // (z.B. "alle Organisationen" statt einer konkreten Auswahl).
  allowAll: { type: Boolean, default: false },
  // Name der Auswahl für Screenreader. Ohne Angabe wird die <label>-Überschrift
  // direkt vor dem Picker verwendet (so sind fast alle Formulare aufgebaut).
  label: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const query = ref('')
const inputName = `org-picker-${Math.random().toString(36).slice(2)}`

// ── Gruppenname ────────────────────────────────────────────────────────────
const rootEl = ref(null)
const labelledBy = ref(null)
const groupLabel = ref(props.label)

// Die Formulare setzen die Überschrift als eigenes <label> vor den Picker, ohne
// Verknüpfung (ein <label for> kann keine Gruppe beschriften). Diese hier per
// aria-labelledby anbinden.
onMounted(() => {
  if (props.label) return
  const prev = rootEl.value?.previousElementSibling
  if (prev?.tagName !== 'LABEL') return
  if (!prev.id) prev.id = `${inputName}-label`
  labelledBy.value = prev.id
  groupLabel.value = prev.textContent.replace('*', '').trim()
})

const filteredOrgs = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.orgs
  return props.orgs.filter(o => o.Title?.toLowerCase().includes(q))
})

const isAllSelected = computed(() => {
  if (!props.allowAll) return false
  return props.multiple ? (props.modelValue?.length ?? 0) === 0 : !props.modelValue
})

function isSelected(id) {
  if (props.multiple) return (props.modelValue || []).includes(id)
  return props.modelValue === id
}

function toggle(id) {
  if (props.multiple) {
    const current = props.modelValue || []
    const next = current.includes(id) ? current.filter(x => x !== id) : [...current, id]
    emit('update:modelValue', next)
  } else {
    emit('update:modelValue', id)
  }
}

function selectAll() {
  emit('update:modelValue', props.multiple ? [] : null)
}
</script>
