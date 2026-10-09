<template>
  <AppModal ref="modal" class="calendar-ics-link-modal" title="Kalender abonnieren" @close="close">
    <form id="calendar-ics-link-form" class="modalform" @submit.prevent="copy">
      <div v-if="organizations.length > 1" class="field">
        <label>Organisationen</label>
        <OrganizationPicker v-model="orgIds" :orgs="organizations" multiple />
      </div>

      <label class="field">
        Welche Termine?
        <select v-model="filter">
          <option v-for="f in FILTERS" :key="f.value" :value="f.value">{{ f.label }}</option>
        </select>
      </label>
      <p class="calendar-ics-link-modal_hint">{{ activeFilter.hint }}</p>

      <AppToggle v-model="allDayAsTime" label="Ganztägige Termine mit Uhrzeit eintragen" />
      <template v-if="allDayAsTime">
        <label class="field field--3">
          Von
          <input v-model="allDayStart" type="time" required />
        </label>
        <label class="field field--3">
          Bis
          <input v-model="allDayEnd" type="time" required />
        </label>
        <p class="calendar-ics-link-modal_hint">
          Hilfreich z.B. im Apple Kalender, wo ganztägige Termine leicht untergehen.
          Mehrtägige Termine laufen vom ersten Tag {{ allDayStart || '…' }} bis zum letzten Tag {{ allDayEnd || '…' }}.
        </p>
      </template>

      <label class="field">
        Link
        <input :value="link" type="text" readonly @focus="$event.target.select()" />
      </label>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" @click="close">Schließen</AppButton>
      <AppButton type="submit" form="calendar-ics-link-form" variant="primary" :disabled="!valid">
        {{ copied ? '✓ Link kopiert' : 'Link kopieren' }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppToggle from '@components/ui/AppToggle.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const props = defineProps({
  // Member.Hash — authentifiziert den Abo-Link (ICSController)
  hash: { type: String, required: true },
  // Organisationen, in denen man Mitglied ist ({ ID, Username, Title, LogoURL })
  organizations: { type: Array, default: () => [] },
})

// Werte müssen zu ICSController::FILTERS passen
const FILTERS = [
  { value: 'all', label: 'Alle Termine', hint: 'Alle Termine deiner Organisationen.' },
  { value: 'notdeclined', label: 'Nur nicht abgesagte', hint: 'Alle Termine deiner Organisationen außer denen, für die du abgesagt hast.' },
  { value: 'invited', label: 'Nur eingeladene, nicht abgesagte', hint: 'Nur Termine, zu denen du eingeladen bist und für die du nicht abgesagt hast.' },
  { value: 'accepted', label: 'Nur zugesagte', hint: 'Nur Termine, für die du zugesagt oder „Vielleicht“ gesagt hast.' },
]

const modal = ref(null)
const orgIds = ref([])
const filter = ref('notdeclined')
const allDayAsTime = ref(false)
const allDayStart = ref('07:00')
const allDayEnd = ref('23:00')
const copied = ref(false)
const error = ref(null)

const activeFilter = computed(() => FILTERS.find(f => f.value === filter.value))

const allOrgsSelected = computed(() => props.organizations.every(o => orgIds.value.includes(o.ID)))

const timesValid = computed(() =>
  !allDayAsTime.value || (!!allDayStart.value && !!allDayEnd.value && allDayStart.value < allDayEnd.value)
)
const valid = computed(() => timesValid.value && (props.organizations.length === 0 || orgIds.value.length > 0))

// Standardwerte lässt der Link weg — der Controller nimmt dann dieselben an
const link = computed(() => {
  const params = new URLSearchParams({ user: props.hash })
  if (!allOrgsSelected.value && orgIds.value.length) {
    // Benutzername statt ID, wo vorhanden (ICSController::parseOrgIDs() erkennt beides)
    const orgKeys = props.organizations
      .filter(o => orgIds.value.includes(o.ID))
      .map(o => o.Username || o.ID)
    params.set('orgs', orgKeys.join(','))
  }
  if (filter.value !== 'all') params.set('filter', filter.value)
  if (allDayAsTime.value && timesValid.value) params.set('allday', `${allDayStart.value}-${allDayEnd.value}`)
  return `${window.location.origin}/ics?${params.toString().replace(/%3A/g, ':').replace(/%2C/g, ',')}`
})

function open() {
  orgIds.value = props.organizations.map(o => o.ID)
  filter.value = 'notdeclined'
  allDayAsTime.value = false
  allDayStart.value = '07:00'
  allDayEnd.value = '23:00'
  copied.value = false
  error.value = null
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function copy() {
  if (!valid.value) {
    error.value = timesValid.value
      ? 'Bitte mindestens eine Organisation auswählen.'
      : 'Die Endzeit muss nach der Startzeit liegen.'
    return
  }
  error.value = null
  try {
    await navigator.clipboard.writeText(link.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 3000)
  } catch {
    error.value = 'Kopieren nicht möglich — bitte den Link oben manuell kopieren.'
  }
}

defineExpose({ open, close })
</script>
