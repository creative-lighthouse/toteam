<template>
  <AppModal ref="modal" class="inventory-rental-detail-modal" title="Ausleihe" @close="close">
    <div v-if="loading" class="inventory-rental-detail-modal_loading">Lade Ausleihe…</div>

    <template v-else-if="rental">
      <div class="inventory-rental-detail-modal_head">
        <span class="inventory-rental-badge" :class="`inventory-rental-badge--${rental.Status}`">{{ rental.StatusLabel }}</span>
        <span class="inventory-rental-detail-modal_dates">{{ formatDateRange(rental.StartDate, rental.EndDate) }}</span>
      </div>

      <!-- Bei der Rücknahme sofort sichtbar, was während der Ausleihe kaputt gegangen ist -->
      <p v-if="rental.CanReturn && openDamages.length" class="inventory-rental-detail-modal_damage-alert" role="alert">
        <span class="icon-mask" :style="problemIconStyle" aria-hidden="true" />
        <span>{{ openDamages.length === 1 ? '1 Schaden wurde' : `${openDamages.length} Schäden wurden` }} während der Ausleihe gemeldet – bitte bei der Rücknahme prüfen.</span>
      </p>

      <dl class="inventory-rental-detail-modal_facts">
        <div>
          <dt>Beantragt von</dt>
          <dd class="inventory-rental-detail-modal_person">
            <AppAvatar v-if="rental.Member" :src="rental.Member.Avatar" :alt="rental.Member.Name" img-class="inventory-rental-detail-modal_avatar" />
            {{ rental.Member?.Name || 'Unbekannt' }}
          </dd>
        </div>
        <div v-if="rental.LenderOrganization">
          <dt>Verleihende Organisation</dt>
          <dd>{{ rental.LenderOrganization.Title }}</dd>
        </div>
        <div v-if="rental.Lender">
          <dt>Verleiht (privat)</dt>
          <dd class="inventory-rental-detail-modal_person">
            <AppAvatar :src="rental.Lender.Avatar" :alt="rental.Lender.Name" img-class="inventory-rental-detail-modal_avatar" />
            {{ rental.IsLentByMe ? 'Du' : rental.Lender.Name }}
          </dd>
        </div>
        <div v-if="rental.IsPrivateUse">
          <dt>Ausgeliehen für</dt>
          <dd>Private Zwecke</dd>
        </div>
        <div v-else-if="rental.Organization">
          <dt>{{ rental.Lender || rental.LenderOrganization ? 'Für Organisation' : 'Organisation' }}</dt>
          <dd>{{ rental.Organization.Title }}</dd>
        </div>
        <div v-if="rental.Purpose" class="inventory-rental-detail-modal_fact--wide">
          <dt>Zweck</dt>
          <dd><AppLinkifiedText :text="rental.Purpose" /></dd>
        </div>
        <div v-if="rental.Events?.length" class="inventory-rental-detail-modal_fact--wide">
          <dt>Für Termin(e)</dt>
          <dd>
            <span v-for="(event, i) in rental.Events" :key="event.ID">{{ i ? ', ' : '' }}{{ event.Title }} ({{ formatDateRange(event.DateStart, event.DateEnd) }})</span>
          </dd>
        </div>
      </dl>

      <template v-if="rental.Items.length">
        <h3 class="hl3 inventory-rental-detail-modal_subtitle">Objekte ({{ rental.Items.length }})</h3>
        <ul class="inventory-rental-detail-modal_items">
          <li
            v-for="group in itemGroups"
            :key="group.key"
            class="inventory-rental-detail-modal_group"
            :class="{ 'inventory-rental-detail-modal_item--conflict': group.items.some(isConflicting) }"
          >
            <div class="inventory-rental-detail-modal_group-head">
              <span v-if="group.items.length > 1" class="inventory-rental-detail-modal_item-quantity">{{ group.items.length }}×</span>
              <span class="inventory-rental-detail-modal_item-title">{{ group.items[0].Title }}</span>
              <span v-if="group.items.some(isConflicting)" class="inventory-rental-detail-modal_item-conflict">
                {{ group.items.filter(isConflicting).length }} nicht mehr verfügbar
              </span>
            </div>
            <div class="inventory-rental-detail-modal_numbers">
              <template v-for="item in group.items" :key="`${item.ID}-${swapRevision}`">
                <select
                  v-if="alternativesFor(item).length"
                  class="inventory-rental-detail-modal_number inventory-rental-detail-modal_number--swappable"
                  :class="{ 'inventory-rental-detail-modal_number--conflict': isConflicting(item) }"
                  :value="item.ID"
                  :disabled="busy"
                  :aria-label="`${item.InventoryNumber} tauschen`"
                  :title="'Gegen ein anderes freies Objekt tauschen'"
                  @change="swap(item, Number($event.target.value))"
                >
                  <option :value="item.ID">{{ item.InventoryNumber }}</option>
                  <option v-for="alt in alternativesFor(item)" :key="alt.ID" :value="alt.ID">↔ {{ alt.InventoryNumber }}</option>
                </select>
                <span
                  v-else
                  class="inventory-rental-detail-modal_number"
                  :class="{ 'inventory-rental-detail-modal_number--conflict': isConflicting(item) }"
                >{{ item.InventoryNumber }}</span>
              </template>
            </div>
          </li>
        </ul>
      </template>

      <template v-if="rental.Rooms?.length">
        <h3 class="hl3 inventory-rental-detail-modal_subtitle">Räume ({{ rental.Rooms.length }})</h3>
        <ul class="inventory-rental-detail-modal_items">
          <li
            v-for="room in rental.Rooms"
            :key="room.ID"
            :class="{ 'inventory-rental-detail-modal_item--conflict': rental.ConflictingRoomIDs?.includes(room.ID) }"
          >
            <span class="inventory-rental-detail-modal_item-title">{{ room.Title }}</span>
            <span v-if="rental.ConflictingRoomIDs?.includes(room.ID)" class="inventory-rental-detail-modal_item-conflict">Im Zeitraum bereits vergeben</span>
          </li>
        </ul>
      </template>

      <template v-if="rental.Damages?.length || rental.CanReportDamage">
        <div class="inventory-rental-detail-modal_subtitle-row">
          <h3 class="hl3 inventory-rental-detail-modal_subtitle">Schäden<template v-if="rental.Damages?.length"> ({{ rental.Damages.length }})</template></h3>
          <AppButton v-if="rental.CanReportDamage" variant="danger" size="small" @click="damageModal?.open(rental)">Schaden melden</AppButton>
        </div>
        <InventoryDamageList
          v-if="rental.Damages?.length"
          :damages="rental.Damages"
          :show-target="rental.Items.length + (rental.Rooms?.length || 0) > 1"
          @changed="reload"
        />
      </template>

      <!-- Ergebnis der Entscheidung -->
      <div v-if="rental.DecidedBy" class="inventory-rental-detail-modal_decision" :class="`inventory-rental-detail-modal_decision--${rental.Status === 'rejected' ? 'rejected' : 'approved'}`">
        <p class="inventory-rental-detail-modal_decision-title">
          {{ rental.Status === 'rejected' ? 'Abgelehnt' : 'Genehmigt' }} von {{ rental.DecidedBy.Name }}
          <span v-if="rental.DecidedAt" class="inventory-rental-detail-modal_decision-date">am {{ formatDate(rental.DecidedAt) }}</span>
        </p>
        <p v-if="rental.Status !== 'rejected'" class="inventory-rental-detail-modal_condition" :class="`inventory-rental-detail-modal_condition--${rental.UsageCondition}`">
          <strong>Auflage:</strong> {{ rental.ConditionLabel }}<template v-if="rental.ConditionComment"> – {{ rental.ConditionComment }}</template>
        </p>
        <p v-if="rental.DecisionComment" class="inventory-rental-detail-modal_decision-comment">„{{ rental.DecisionComment }}“</p>
      </div>

      <p v-if="rental.HandedOverAt || rental.ReturnedAt" class="inventory-rental-detail-modal_timeline">
        <span v-if="rental.HandedOverAt">Übergeben am {{ formatDate(rental.HandedOverAt) }}</span>
        <span v-if="rental.ReturnedAt"> · Zurückgegeben am {{ formatDate(rental.ReturnedAt) }}</span>
      </p>

      <!-- Kilometerstände der Fahrzeuge -->
      <ul v-if="vehicles.length && !mileageStep && vehicles.some(v => v.StartMileage || v.EndMileage)" class="inventory-rental-detail-modal_mileage">
        <li v-for="vehicle in vehicles" :key="vehicle.ID">
          <span class="inventory-rental-detail-modal_mileage-title">{{ vehicle.Title }}</span>
          <span>{{ formatKm(vehicle.StartMileage) }} → {{ vehicle.EndMileage ? formatKm(vehicle.EndMileage) : 'unterwegs' }}</span>
          <strong v-if="vehicle.StartMileage && vehicle.EndMileage">{{ formatKm(vehicle.EndMileage - vehicle.StartMileage) }}</strong>
        </li>
      </ul>

      <!-- Kilometerstand bei Übergabe/Rückgabe erfassen (optional) -->
      <form v-if="mileageStep" id="inventory-rental-mileage-form" class="modalform inventory-rental-detail-modal_mileage-form" @submit.prevent="confirmMileage">
        <p class="field inventory-rental-detail-modal_hint">
          Kilometerstand bei {{ mileageStep === 'handed_over' ? 'Übergabe' : 'Rückgabe' }} – optional, leer lassen, wenn ihr das nicht erfasst.
        </p>
        <label v-for="vehicle in vehicles" :key="vehicle.ID" class="field field--3">
          {{ vehicle.Title }}<template v-if="vehicle.InventoryNumber"> ({{ vehicle.InventoryNumber }})</template>
          <input
            v-model="mileageInputs[vehicle.ID]"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :placeholder="mileageStep === 'returned' && vehicle.StartMileage ? `mind. ${formatKm(vehicle.StartMileage)}` : 'km'"
          >
        </label>
      </form>

      <!-- Entscheidung treffen -->
      <form v-if="rental.CanDecide" id="inventory-rental-decide-form" class="modalform inventory-rental-detail-modal_decide" @submit.prevent="decide('approve')">
        <AppSegmentedToggle v-model="decision.UsageCondition" label="Auflage bei Genehmigung" :options="CONDITION_OPTIONS" />
        <label v-if="decision.UsageCondition !== 'free'" class="field">
          {{ decision.UsageCondition === 'conditional' ? 'Bedingung *' : 'Hinweis' }}
          <textarea
            v-model="decision.ConditionComment"
            rows="2"
            :placeholder="decision.UsageCondition === 'conditional' ? 'z.B. nur mit Aufsicht, nur draußen, …' : 'z.B. nur transportieren und lagern'"
            :required="decision.UsageCondition === 'conditional'"
          />
        </label>
        <label class="field">
          Kommentar
          <textarea v-model="decision.DecisionComment" rows="2" placeholder="Optionaler Kommentar an die ausleihende Person…" />
        </label>
      </form>

      <p v-else-if="rental.Status === 'requested' && rental.IsMine" class="inventory-rental-detail-modal_hint">
        <template v-if="rental.Lender">Dein Antrag wartet auf die Entscheidung von {{ rental.Lender.Name }}.</template>
        <template v-else-if="rental.LenderOrganization">Dein Antrag wartet auf die Entscheidung von {{ rental.LenderOrganization.Title }}.</template>
        <template v-else>Dein Antrag wartet auf die Entscheidung eines Mitglieds mit der Berechtigung „Ausleih-Anträge genehmigen“.</template>
      </p>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </template>

    <template v-if="rental" #actions>
      <AppIconButton variant="neutral" aria-label="Verlauf" title="Verlauf" class="inventory-rental-detail-modal_history" @click="historyModal?.open()">
        <span class="icon-mask" :style="historyIconStyle" />
      </AppIconButton>
      <template v-if="mileageStep">
        <AppButton variant="secondary" :disabled="busy" @click="mileageStep = null">Zurück</AppButton>
        <AppButton type="submit" form="inventory-rental-mileage-form" variant="primary" :disabled="busy">
          {{ mileageStep === 'handed_over' ? 'Übergabe bestätigen' : 'Rückgabe bestätigen' }}
        </AppButton>
      </template>
      <template v-else>
      <AppButton v-if="rental.CanCancel" variant="secondary" :disabled="busy" @click="changeStatus('cancelled')">Stornieren</AppButton>
      <template v-if="rental.CanDecide">
        <AppButton variant="danger" :disabled="busy" @click="decide('reject')">Ablehnen</AppButton>
        <AppButton type="submit" form="inventory-rental-decide-form" variant="primary" :disabled="busy || !canApprove">Genehmigen</AppButton>
      </template>
      <AppButton v-if="rental.CanHandOver" variant="primary" :disabled="busy" @click="changeStatus('handed_over')">Übergeben</AppButton>
      <AppButton v-if="rental.CanReturn" variant="primary" :disabled="busy" @click="changeStatus('returned')">Zurückgegeben</AppButton>
      </template>
    </template>
  </AppModal>

  <InventoryDamageReportModal ref="damageModal" @reported="onDamageReported" />

  <HistoryModal
    v-if="rental"
    ref="historyModal"
    :endpoint="`/inventory/rentalHistory/${rental.ID}`"
    created-label="hat die Ausleihe beantragt"
  />
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useInventoryStore, CONDITION_OPTIONS } from '@stores/inventory'
import { formatDate, formatDateRange } from '@utils/inventory'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import HistoryModal from '@components/history/HistoryModal.vue'
import InventoryDamageList from '@components/inventory/InventoryDamageList.vue'
import InventoryDamageReportModal from '@components/inventory/InventoryDamageReportModal.vue'
import actionHistory from '../../../../icons/actions/action_history.svg'
import stateProblem from '../../../../icons/states/state_problem.svg'

const emit = defineEmits(['changed', 'closed'])
const store = useInventoryStore()

const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
const problemIconStyle = { maskImage: `url("${stateProblem}")`, WebkitMaskImage: `url("${stateProblem}")` }

const modal = ref(null)
const historyModal = ref(null)
const rental = ref(null)
const loading = ref(false)
const busy = ref(false)
const error = ref(null)

const decision = reactive({ UsageCondition: 'free', ConditionComment: '', DecisionComment: '' })

// Nicht mehr verfügbare Objekte ersetzt der Server beim Genehmigen durch freie gleiche —
// blockiert ist nur, wenn es keinen Ersatz gibt
const canApprove = computed(() =>
  !(rental.value?.Items || []).some(i => isConflicting(i) && !alternativesFor(i).length)
  && !rental.value?.ConflictingRoomIDs?.length
  && (decision.UsageCondition !== 'conditional' || !!decision.ConditionComment.trim())
)

const STATUS_CONFIRM = {
  cancelled:   'Ausleihe wirklich stornieren?',
  handed_over: 'Wurde alles übergeben?',
  returned:    'Wurde alles zurückgegeben?',
}

async function open(rentalId) {
  rental.value = store.rentals.find(r => r.ID === rentalId) ?? null
  loading.value = !rental.value
  error.value = null
  mileageStep.value = null
  Object.assign(decision, { UsageCondition: 'free', ConditionComment: '', DecisionComment: '' })
  modal.value?.open()
  const detail = await store.fetchRentalDetail(rentalId)
  if (detail) {
    rental.value = detail
  } else if (!rental.value) {
    error.value = 'Ausleihe nicht gefunden.'
  }
  loading.value = false
}

function close() {
  modal.value?.close()
  emit('closed')
}

async function decide(kind) {
  if (!rental.value) return
  if (kind === 'reject' && !confirm('Antrag wirklich ablehnen?')) return
  busy.value = true
  error.value = null
  try {
    const response = await store.decideRental(rental.value.ID, {
      Decision: kind,
      UsageCondition: decision.UsageCondition,
      ConditionComment: decision.ConditionComment.trim(),
      DecisionComment: decision.DecisionComment.trim(),
    })
    if (response.success) {
      rental.value = response.data.rental
      emit('changed', rental.value)
    } else {
      error.value = response.error || 'Fehler beim Speichern der Entscheidung.'
    }
  } finally {
    busy.value = false
  }
}

// Reservierte Objekte nach Gruppe gleicher Objekte zusammengefasst
const itemGroups = computed(() => {
  const groups = new Map()
  for (const item of rental.value?.Items || []) {
    if (!groups.has(item.GroupKey)) groups.set(item.GroupKey, { key: item.GroupKey, items: [] })
    groups.get(item.GroupKey).items.push(item)
  }
  return [...groups.values()].map(group => ({
    ...group,
    items: group.items.sort((a, b) => (a.InventoryNumber || '').localeCompare(b.InventoryNumber || '', 'de', { numeric: true })),
  }))
})

const swapRevision = ref(0)

function isConflicting(item) {
  return !!rental.value?.ConflictingItemIDs?.includes(item.ID)
}

function alternativesFor(item) {
  return rental.value?.Alternatives?.[item.ID] || []
}

async function swap(item, newItemId) {
  if (!newItemId || newItemId === item.ID) return
  busy.value = true
  error.value = null
  try {
    const response = await store.swapRentalItem(rental.value.ID, item.ID, newItemId)
    if (response.success) {
      rental.value = response.data.rental
      emit('changed', rental.value)
    } else {
      error.value = response.error || 'Objekt konnte nicht getauscht werden.'
      // Auswahlfeld wieder auf die tatsächlich reservierte Nummer setzen
      swapRevision.value++
    }
  } finally {
    busy.value = false
  }
}

const damageModal = ref(null)
const openDamages = computed(() => (rental.value?.Damages || []).filter(d => d.IsOpen))

function onDamageReported(updated) {
  rental.value = updated
  emit('changed', updated)
}

// Nach "behoben" neu laden (Zustand der Objekte kann sich geändert haben)
async function reload() {
  const detail = await store.fetchRentalDetail(rental.value.ID)
  if (detail) {
    rental.value = detail
    emit('changed', detail)
  }
}

// Fahrzeuge der Ausleihe (für Kilometerstände)
const vehicles = computed(() => (rental.value?.Items || []).filter(i => i.IsVehicle))

const kmFormat = new Intl.NumberFormat('de-DE')
function formatKm(km) {
  return km ? `${kmFormat.format(km)} km` : '–'
}

// Übergabe/Rückgabe mit Fahrzeugen: erst Kilometerstände abfragen (optional)
const mileageStep = ref(null)
const mileageInputs = reactive({})

function startMileageStep(status) {
  mileageStep.value = status
  for (const key of Object.keys(mileageInputs)) delete mileageInputs[key]
  for (const vehicle of vehicles.value) {
    // Bei Übergabe den letzten bekannten Stand vorschlagen
    mileageInputs[vehicle.ID] = status === 'handed_over' && vehicle.CurrentMileage ? String(vehicle.CurrentMileage) : ''
  }
}

async function confirmMileage() {
  const status = mileageStep.value
  await submitStatus(status, { ...mileageInputs })
  if (!error.value) mileageStep.value = null
}

async function changeStatus(status) {
  if (!rental.value) return
  if (vehicles.value.length && (status === 'handed_over' || status === 'returned')) {
    startMileageStep(status)
    return
  }
  if (!confirm(STATUS_CONFIRM[status])) return
  await submitStatus(status)
}

async function submitStatus(status, mileages = {}) {
  busy.value = true
  error.value = null
  try {
    const response = await store.setRentalStatus(rental.value.ID, status, mileages)
    if (response.success) {
      rental.value = response.data.rental
      emit('changed', rental.value)
    } else {
      error.value = response.error || 'Fehler beim Aktualisieren der Ausleihe.'
    }
  } finally {
    busy.value = false
  }
}

defineExpose({ open, close })
</script>
