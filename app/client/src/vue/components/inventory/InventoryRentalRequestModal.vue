<template>
  <AppModal ref="modal" class="inventory-rental-request-modal" title="Ausleihe beantragen" @close="close">
    <form id="inventory-rental-request-form" class="modalform" @submit.prevent="submit">

      <div class="field">
        <label>Ausleihen für</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="contextOptions" :searchable="contextOptions.length > 6" />
      </div>

      <p v-if="isPrivateUse" class="field inventory-rental-request-modal_hint">
        Für private Zwecke – zur Auswahl stehen dein eigenes Equipment und alle Objekte, die ihre Besitzer auch privat verleihen.
      </p>
      <p v-else-if="form.OrganizationID && !canRequestInOrg" class="field inventory-rental-request-modal_hint">
        Du hast in dieser Organisation keine Berechtigung, Ausleihen zu beantragen – du kannst hier nur dein eigenes Equipment verleihen.
      </p>

      <template v-if="form.OrganizationID">
        <DateTimeRangeField v-model="period" time="none" start-label-date="Von" end-label-date="Bis" />

        <div v-if="requestableGroups.length || !visibleRooms.length" class="field">
          <label>Objekte <span v-if="selectedCount" class="inventory-rental-request-modal_count">({{ selectedCount }} ausgewählt)</span></label>
          <input
            v-if="requestableGroups.length > 6"
            v-model="itemQuery"
            type="search"
            class="input inventory-rental-request-modal_search"
            placeholder="Objekte suchen…"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
          >
          <p v-if="loadingOptions && !options.groups.length" class="inventory-rental-request-modal_hint">Lade Objekte…</p>
          <p v-else-if="!requestableGroups.length" class="inventory-rental-request-modal_hint">
            {{ isPrivateUse
              ? 'Es gibt keine Objekte, die privat ausgeliehen werden können.'
              : (canRequestInOrg ? 'Für diese Organisation gibt es keine ausleihbaren Objekte.' : 'Du hast hier kein eigenes Equipment, das du verleihen könntest.') }}
          </p>
          <p v-else-if="!sections.length" class="inventory-rental-request-modal_hint">Keine passenden Objekte gefunden.</p>
          <template v-else>
            <template v-for="section in sections" :key="section.key">
              <p class="inventory-rental-request-modal_section">{{ section.label }}</p>
              <ul class="inventory-rental-request-modal_items">
                <li v-for="group in section.groups" :key="group.Key" class="inventory-rental-request-modal_row">
                  <label
                    class="inventory-rental-request-modal_item"
                    :class="{ 'inventory-rental-request-modal_item--disabled': unavailableReason(group) && !isSelected(group) }"
                  >
                    <input
                      type="checkbox"
                      :checked="isSelected(group)"
                      :disabled="!!unavailableReason(group) && !isSelected(group)"
                      @change="toggleGroup(group)"
                    >
                    <span class="inventory-rental-request-modal_item-number">{{ group.Count > 1 ? `${group.Count}×` : group.Numbers[0] }}</span>
                    <span class="inventory-rental-request-modal_item-title">{{ group.Title }}</span>
                    <span v-if="unavailableReason(group)" class="inventory-rental-request-modal_item-reason">{{ unavailableReason(group) }}</span>
                    <template v-else>
                      <span v-if="group.Type" class="inventory-rental-request-modal_item-type">{{ group.Type }}</span>
                      <span v-if="group.Count > 1 && !isSelected(group)" class="inventory-rental-request-modal_item-type">{{ group.Available }} frei</span>
                    </template>
                  </label>
                  <span v-if="isSelected(group) && group.Count > 1" class="inventory-rental-request-modal_quantity">
                    <input
                      :value="form.Groups[group.Key]"
                      type="number"
                      min="1"
                      :max="group.Available"
                      inputmode="numeric"
                      class="input"
                      :aria-label="`Anzahl ${group.Title}`"
                      @input="setQuantity(group, $event.target.value)"
                    >
                    <span>/ {{ group.Available }}</span>
                  </span>
                </li>
              </ul>
            </template>
          </template>
        </div>

        <div v-if="visibleRooms.length" class="field">
          <label>Räume <span v-if="form.RoomIDs.length" class="inventory-rental-request-modal_count">({{ form.RoomIDs.length }} ausgewählt)</span></label>
          <p class="inventory-rental-request-modal_section">{{ contextOrgTitle }}</p>
          <ul class="inventory-rental-request-modal_items">
            <li v-for="room in visibleRooms" :key="room.ID">
              <label
                class="inventory-rental-request-modal_item"
                :class="{ 'inventory-rental-request-modal_item--disabled': !!roomUnavailableReason(room) && !form.RoomIDs.includes(room.ID) }"
              >
                <input
                  type="checkbox"
                  :value="room.ID"
                  :checked="form.RoomIDs.includes(room.ID)"
                  :disabled="!!roomUnavailableReason(room) && !form.RoomIDs.includes(room.ID)"
                  @change="toggleRoom(room.ID)"
                >
                <span class="inventory-rental-request-modal_item-title">{{ room.Title }}</span>
                <span v-if="roomUnavailableReason(room)" class="inventory-rental-request-modal_item-reason">{{ roomUnavailableReason(room) }}</span>
              </label>
            </li>
          </ul>
        </div>

        <p v-if="splitHint" class="field inventory-rental-request-modal_hint">{{ splitHint }}</p>

        <p v-if="selectedUnavailable.length" class="field inventory-rental-request-modal_warning">
          Im gewählten Zeitraum nicht verfügbar: {{ selectedUnavailable.map(i => i.Title).join(', ') }}
        </p>

        <div v-if="options.events.length" class="field">
          <label>Für Termin(e)</label>
          <AppChipSelect
            v-model="form.EventIDs"
            aria-label="Termine"
            :options="options.events.map(e => ({ value: e.ID, label: `${e.Title} (${formatDateRange(e.DateStart, e.DateEnd)})` }))"
          />
        </div>

        <label class="field">
          Zweck
          <textarea v-model="form.Purpose" rows="3" placeholder="Wofür brauchst du das?" />
        </label>
      </template>

      <div v-if="error" class="app-modal_error">
        {{ error }}
      </div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">
        Abbrechen
      </AppButton>
      <AppButton type="submit" form="inventory-rental-request-form" variant="primary" :disabled="saving || !canSubmit">
        {{ saving ? 'Senden…' : submitLabel }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import { formatDateRange, todayIso } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppChipSelect from '@components/ui/AppChipSelect.vue'
import AppModal from '@components/ui/AppModal.vue'
import DateTimeRangeField from '@components/ui/DateTimeRangeField.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'

const emit = defineEmits(['saved'])
const store = useInventoryStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const itemQuery = ref('')
const loadingOptions = ref(false)
const EMPTY_OPTIONS = { groups: [], rooms: [], busyRoomIDs: [], events: [] }
const options = ref({ ...EMPTY_OPTIONS })

const form = reactive({
  OrganizationID: null,
  // Gewünschte Anzahl je Gruppe gleicher Objekte ({ GroupKey: n }); freie Objekte reserviert der Server
  Groups: {},
  RoomIDs: [],
  EventIDs: [],
  Purpose: '',
})

const period = ref({ dateStart: todayIso(), dateEnd: todayIso() })

// Nur, was man im gewählten Kontext auch beantragen kann: ohne Antrags-Berechtigung
// bleibt nur das eigene Equipment (privat liefert der Server schon gefiltert)
const requestableGroups = computed(() =>
  options.value.groups.filter(g => g.IsMine || canRequestInOrg.value)
)

const contextOrgTitle = computed(() => store.orgById(form.OrganizationID)?.Title || '')

// Räume brauchen immer die Antrags-Berechtigung der Organisation
const visibleRooms = computed(() => (canRequestInOrg.value && !isPrivateUse.value ? options.value.rooms : []))

const filteredGroups = computed(() => {
  const q = itemQuery.value.trim().toLowerCase()
  if (!q) return requestableGroups.value
  return requestableGroups.value.filter(g =>
    isSelected(g)
    || g.Title?.toLowerCase().includes(q)
    || g.Numbers.some(n => n?.toLowerCase().includes(q))
  )
})

function isSelected(group) {
  return group.Key in form.Groups
}

const selectedCount = computed(() => Object.values(form.Groups).reduce((sum, n) => sum + n, 0))

// "Privat" als zusätzliche Wahl neben den Organisationen (Ausleihe für private Zwecke)
const PRIVATE_CONTEXT = 'private'
const contextOptions = computed(() => [
  ...store.organizations,
  { ID: PRIVATE_CONTEXT, Title: 'Privat', LogoURL: null },
])
const isPrivateUse = computed(() => form.OrganizationID === PRIVATE_CONTEXT)

// Privat ausleihbare Objekte liefert der Server bereits nach Berechtigung gefiltert
const canRequestInOrg = computed(() => isPrivateUse.value || !!store.orgById(form.OrganizationID)?.CanRequest)

function unavailableReason(group) {
  if (!group.Capacity) return group.Count > 1 ? 'Keins einsatzbereit' : 'Nicht einsatzbereit'
  if (!group.Available) return group.Count > 1 ? 'Alle belegt' : 'Im Zeitraum belegt'
  return null
}

/**
 * Objekte nach Quelle mit Zwischenüberschrift: zuerst die gewählte Organisation,
 * dann das eigene Equipment ("Dein Equipment"), danach alle anderen (Organisationen und privates
 * Equipment anderer Mitglieder) alphabetisch.
 */
const sections = computed(() => {
  const contextOrgId = isPrivateUse.value ? null : form.OrganizationID
  const map = new Map()
  for (const group of filteredGroups.value) {
    let key, label, rank
    if (group.IsMine) {
      key = 'mine'; label = 'Dein Equipment'; rank = 1
    } else if (group.IsPrivate) {
      key = `m:${group.OwnerName}`; label = `Privat: ${group.OwnerName}`; rank = 2
    } else {
      key = `o:${group.OrganizationID}`; label = group.OrgTitle || 'Organisation'; rank = group.OrganizationID === contextOrgId ? 0 : 2
    }
    if (!map.has(key)) map.set(key, { key, label, rank, groups: [] })
    map.get(key).groups.push(group)
  }
  return [...map.values()].sort((a, b) => (a.rank - b.rank) || a.label.localeCompare(b.label, 'de'))
})

function roomUnavailableReason(room) {
  return options.value.busyRoomIDs.includes(room.ID) ? 'Im Zeitraum belegt' : null
}

const selectedItems = computed(() => options.value.groups.filter(g => isSelected(g)))

const onlyOwnEquipment = computed(() =>
  selectedItems.value.length > 0 && !form.RoomIDs.length && selectedItems.value.every(i => i.IsMine)
)

// Nur eigenes Equipment: für eine Organisation "verleihen", privat nur für sich eintragen
const submitLabel = computed(() => {
  if (!onlyOwnEquipment.value) return 'Beantragen'
  return isPrivateUse.value ? 'Eintragen' : 'Verleihen'
})

function setQuantity(group, value) {
  form.Groups = { ...form.Groups, [group.Key]: Math.max(1, parseInt(value) || 1) }
}

function toggleGroup(group) {
  const next = { ...form.Groups }
  if (isSelected(group)) delete next[group.Key]
  else next[group.Key] = 1
  form.Groups = next
}

// Pro Quelle entsteht ein eigener Antrag: Org-Inventar/Räume und je privatem Besitzer
const splitHint = computed(() => {
  const owners = new Set(selectedItems.value.filter(i => i.IsPrivate && !i.IsMine).map(i => i.OwnerName))
  const lenderOrgs = new Set(selectedItems.value.filter(i => !i.IsPrivate && i.LenderOrgTitle).map(i => i.LenderOrgTitle))
  const hasOwn = selectedItems.value.some(i => i.IsMine)
  const hasOrg = selectedItems.value.some(i => !i.IsPrivate && !i.LenderOrgTitle) || form.RoomIDs.length > 0
  const parts = (hasOrg ? 1 : 0) + lenderOrgs.size + owners.size + (hasOwn ? 1 : 0)
  const notes = []
  if (lenderOrgs.size) notes.push(`Über freigegebene Objekte entscheidet die besitzende Organisation (${[...lenderOrgs].join(', ')}).`)
  if (owners.size) notes.push(`Über private Objekte entscheidet der Besitzer (${[...owners].join(', ')}).`)
  if (hasOwn) notes.push('Dein eigenes Equipment ist direkt genehmigt.')
  if (parts > 1) notes.unshift(`Es entstehen ${parts} getrennte Anträge.`)
  return notes.join(' ')
})

// Bereits gewählte Objekte/Räume, die nach einer Datumsänderung nicht mehr verfügbar sind
const selectedUnavailable = computed(() => [
  ...options.value.groups.filter(g => isSelected(g) && (unavailableReason(g) || form.Groups[g.Key] > g.Available)),
  ...visibleRooms.value.filter(r => form.RoomIDs.includes(r.ID) && roomUnavailableReason(r)),
])

const canSubmit = computed(() =>
  !!form.OrganizationID
  && (selectedCount.value > 0 || form.RoomIDs.length > 0)
  && !selectedUnavailable.value.length
  && !!period.value.dateStart
  && (!period.value.dateEnd || period.value.dateEnd >= period.value.dateStart)
)

function toggleRoom(id) {
  form.RoomIDs = form.RoomIDs.includes(id) ? form.RoomIDs.filter(x => x !== id) : [...form.RoomIDs, id]
}

let optionsToken = 0
async function loadOptions() {
  if (!form.OrganizationID) {
    options.value = { ...EMPTY_OPTIONS }
    return
  }
  const token = ++optionsToken
  loadingOptions.value = true
  const result = await store.fetchRentalOptions(form.OrganizationID, period.value.dateStart, period.value.dateEnd || period.value.dateStart)
  if (token !== optionsToken) return
  options.value = {
    groups: result.groups || [],
    rooms: result.rooms || [],
    busyRoomIDs: result.busyRoomIDs || [],
    events: result.events || [],
  }
  const eventIds = new Set(options.value.events.map(e => e.ID))
  form.EventIDs = form.EventIDs.filter(id => eventIds.has(id))
  loadingOptions.value = false
}

// Beim Öffnen mit Vorauswahl (z.B. "Ausleihen" an einem Objekt) gehört die
// Auswahl bereits zur neuen Organisation und darf nicht zurückgesetzt werden
let keepSelectionOnOrgChange = false

watch(() => form.OrganizationID, (orgId, oldOrgId) => {
  if (oldOrgId && orgId !== oldOrgId && !keepSelectionOnOrgChange) {
    form.Groups = {}
    form.RoomIDs = []
    form.EventIDs = []
  }
  keepSelectionOnOrgChange = false
  loadOptions()
})

watch(() => [period.value.dateStart, period.value.dateEnd], () => {
  // Enddatum vor dem Start nachziehen, statt einen ungültigen Zeitraum abzufragen
  if (period.value.dateStart && period.value.dateEnd && period.value.dateEnd < period.value.dateStart) {
    period.value = { ...period.value, dateEnd: period.value.dateStart }
    return
  }
  loadOptions()
})

/**
 * @param {{ organizationId?: number, groups?: Record<string, number>, roomIds?: number[] }} preset
 */
function open(preset = {}) {
  const orgs = contextOptions.value
  const presetOrg = preset.organizationId && orgs.some(o => o.ID === preset.organizationId) ? preset.organizationId : null
  // Mit nur einer Organisation diese vorauswählen ("Privat" zählt dabei nicht mit)
  const orgId = presetOrg ?? (store.organizations.length === 1 ? store.organizations[0].ID : null)

  error.value = null
  itemQuery.value = ''
  period.value = { dateStart: todayIso(), dateEnd: todayIso() }
  form.EventIDs = []
  form.Purpose = ''
  form.Groups = presetOrg ? { ...(preset.groups || {}) } : {}
  form.RoomIDs = presetOrg ? [...(preset.roomIds || [])] : []
  if (form.OrganizationID === orgId) {
    loadOptions()
  } else {
    keepSelectionOnOrgChange = true
    form.OrganizationID = orgId
  }
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submit() {
  if (!canSubmit.value) return
  saving.value = true
  error.value = null

  try {
    const response = await store.createRental({
      OrganizationID: form.OrganizationID,
      GroupQuantities: form.Groups,
      RoomIDs: form.RoomIDs,
      EventIDs: form.EventIDs,
      StartDate: period.value.dateStart,
      EndDate: period.value.dateEnd || period.value.dateStart,
      Purpose: form.Purpose.trim(),
    })
    if (response.success) {
      emit('saved', response.data.rental)
      close()
    } else {
      error.value = response.error || 'Fehler beim Beantragen der Ausleihe.'
    }
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, close })
</script>
