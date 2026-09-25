<template>
  <div class="section section--InventoryRentalsPage">
    <div class="section_content">

      <div class="inventory-rentals-toolbar">
        <div class="inventory-rentals-toolbar_filters">
          <select v-model="filter" class="input" aria-label="Ausleihen filtern">
            <option value="">Alle Ausleihen</option>
            <option value="mine">Meine Anträge</option>
            <option value="private">Private Ausleihen</option>
            <optgroup v-if="store.organizations.length" label="Organisationen">
              <option v-for="org in store.organizations" :key="org.ID" :value="String(org.ID)">{{ org.Title }}</option>
            </optgroup>
          </select>
        </div>
        <AppButton v-if="store.organizations.length" variant="primary" @click="requestModal?.open({ organizationId: presetContext })">
          + Ausleihe
        </AppButton>
      </div>

      <div v-if="store.rentalsLoading" class="section_infobox">
        <p>Lade Ausleihen…</p>
      </div>

      <div v-else-if="store.rentalsError" class="section_infobox error">
        <p>Fehler: {{ store.rentalsError }}</p>
        <AppButton variant="primary" @click="store.fetchRentals(true)">Erneut versuchen</AppButton>
      </div>

      <template v-else>
        <section v-for="group in groups" :key="group.key" class="inventory-rentals-group">
          <h2 class="hl3 inventory-rentals-group_title">
            {{ group.title }}
            <span class="inventory-rentals-group_count">{{ group.rentals.length }}</span>
          </h2>

          <p v-if="!group.rentals.length" class="inventory-rentals-group_empty">{{ group.empty }}</p>

          <ul v-else class="inventory-rentals-list">
            <li v-for="rental in group.rentals" :key="rental.ID">
              <button
                type="button"
                class="inventory-rental-entry"
                :class="{ 'inventory-rental-entry--actionable': rental.CanDecide }"
                @click="openRental(rental.ID)"
              >
                <AppAvatar
                  v-if="rental.Member"
                  :src="rental.Member.Avatar"
                  :alt="rental.Member.Name"
                  img-class="inventory-rental-entry_avatar"
                />
                <div class="inventory-rental-entry_info">
                  <p class="inventory-rental-entry_title">
                    {{ rental.Member?.Name || 'Unbekannt' }}
                    <span class="inventory-rental-entry_dates">{{ formatDateRange(rental.StartDate, rental.EndDate) }}</span>
                  </p>
                  <p class="inventory-rental-entry_items">
                    <template v-if="rental.Lender">{{ rental.IsLentByMe ? 'Dein Equipment' : `Privat: ${rental.Lender.Name}` }} · </template>
                    <template v-else-if="rental.LenderOrganization">von {{ rental.LenderOrganization.Title }} für {{ rental.IsPrivateUse ? 'private Zwecke' : rental.Organization?.Title }} · </template>
                    <template v-else-if="rental.IsPrivateUse">privat · </template>{{ itemSummary(rental) }}
                  </p>
                </div>
                <span
                  v-if="rental.OpenDamageCount"
                  class="inventory-rental-entry_damage"
                  role="img"
                  :title="`${rental.OpenDamageCount} offene Schadensmeldung(en)`"
                  :aria-label="`${rental.OpenDamageCount} offene Schadensmeldung(en)`"
                >
                  <span class="icon-mask" :style="problemIconStyle" />
                  {{ rental.OpenDamageCount }}
                </span>
                <span v-if="rental.CanDecide" class="inventory-rental-entry_action">Entscheiden</span>
                <span class="inventory-rental-badge" :class="`inventory-rental-badge--${rental.Status}`">{{ rental.StatusLabel }}</span>
              </button>
            </li>
          </ul>
        </section>

        <AppCollapse
          v-for="section in archiveSections"
          :key="section.key"
          :title="`${section.title} (${section.rentals.length})`"
          class="inventory-rentals-archive"
        >
          <ul class="inventory-rentals-list">
            <li v-for="rental in section.rentals" :key="rental.ID">
              <button type="button" class="inventory-rental-entry" @click="openRental(rental.ID)">
                <div class="inventory-rental-entry_info">
                  <p class="inventory-rental-entry_title">
                    {{ rental.Member?.Name || 'Unbekannt' }}
                    <span class="inventory-rental-entry_dates">{{ formatDateRange(rental.StartDate, rental.EndDate) }}</span>
                  </p>
                  <p class="inventory-rental-entry_items">{{ itemSummary(rental) }}</p>
                </div>
                <span
                  v-if="rental.OpenDamageCount"
                  class="inventory-rental-entry_damage"
                  role="img"
                  :title="`${rental.OpenDamageCount} offene Schadensmeldung(en)`"
                  :aria-label="`${rental.OpenDamageCount} offene Schadensmeldung(en)`"
                >
                  <span class="icon-mask" :style="problemIconStyle" />
                  {{ rental.OpenDamageCount }}
                </span>
                <span class="inventory-rental-badge" :class="`inventory-rental-badge--${rental.Status}`">{{ rental.StatusLabel }}</span>
              </button>
            </li>
          </ul>
        </AppCollapse>
      </template>

    </div>

    <InventoryRentalRequestModal ref="requestModal" @saved="rental => openRental(rental.ID)" />
    <InventoryRentalDetailModal ref="detailModal" @closed="onDetailClosed" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useInventoryStore } from '@stores/inventory'
import { formatDateRange } from '@utils/inventory'
import stateProblem from '../../../icons/states/state_problem.svg'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import InventoryRentalRequestModal from '@components/inventory/InventoryRentalRequestModal.vue'
import InventoryRentalDetailModal from '@components/inventory/InventoryRentalDetailModal.vue'

usePageHeaderStore().setHeader('Ausleihen', 'Anträge, laufende und geplante Ausleihen und Raumreservierungen.')

const problemIconStyle = { maskImage: `url("${stateProblem}")`, WebkitMaskImage: `url("${stateProblem}")` }

const route = useRoute()
const router = useRouter()
const store = useInventoryStore()
const requestModal = ref(null)
const detailModal = ref(null)

// '' = alle, 'mine' = eigene Anträge, 'private' = für private Zwecke, sonst Organisations-ID (als String)
const filter = ref('')

// Beim Beantragen den gefilterten Kontext vorauswählen
const presetContext = computed(() => {
  if (filter.value === 'private') return 'private'
  return /^\d+$/.test(filter.value) ? Number(filter.value) : null
})

const visibleRentals = computed(() => store.rentals.filter(matchesFilter))

function matchesFilter(rental) {
  if (!filter.value) return true
  if (filter.value === 'mine') return rental.IsMine
  if (filter.value === 'private') return rental.IsPrivateUse
  // Organisation: Ausleihen für sie und Anträge an ihr (freigegebenes) Inventar
  const orgId = Number(filter.value)
  return rental.OrganizationID === orgId || rental.LenderOrganization?.ID === orgId
}

const byStartAsc = (a, b) => (a.StartDate || '').localeCompare(b.StartDate || '')

const groups = computed(() => [
  {
    key: 'requested',
    title: 'Offene Anträge',
    empty: 'Keine offenen Anträge.',
    // Anträge, über die man selbst entscheiden kann, zuerst
    rentals: visibleRentals.value
      .filter(r => r.Status === 'requested')
      .sort((a, b) => (b.CanDecide - a.CanDecide) || byStartAsc(a, b)),
  },
  {
    key: 'handed_over',
    title: 'Gerade ausgeliehen',
    empty: 'Aktuell ist nichts ausgeliehen.',
    rentals: visibleRentals.value.filter(r => r.Status === 'handed_over').sort(byStartAsc),
  },
  {
    key: 'approved',
    title: 'Genehmigt & geplant',
    empty: 'Keine geplanten Ausleihen.',
    rentals: visibleRentals.value.filter(r => r.Status === 'approved').sort(byStartAsc),
  },
])

// Eingeklappte Bereiche am Ende: erledigte und stornierte Ausleihen getrennt
const archiveSections = computed(() => [
  { key: 'done', title: 'Abgeschlossen', rentals: visibleRentals.value.filter(r => ['returned', 'rejected'].includes(r.Status)) },
  { key: 'cancelled', title: 'Storniert', rentals: visibleRentals.value.filter(r => r.Status === 'cancelled') },
].filter(section => section.rentals.length))

function itemSummary(rental) {
  // Gleiche Objekte zusammenfassen: "15× Schuko 5m"
  const counts = new Map()
  for (const item of rental.Items) {
    const entry = counts.get(item.GroupKey) || { title: item.Title, count: 0 }
    entry.count++
    counts.set(item.GroupKey, entry)
  }
  const titles = [
    ...(rental.Rooms || []).map(r => r.Title),
    ...[...counts.values()].map(e => (e.count > 1 ? `${e.count}× ${e.title}` : e.title)),
  ]
  const summary = titles.length <= 3
    ? titles.join(', ')
    : `${titles.slice(0, 3).join(', ')} +${titles.length - 3} weitere`

  // Fahrzeuge: gefahrene Kilometer, sobald beide Stände erfasst sind (nie abgeschnitten)
  const driven = rental.Items
    .filter(i => i.IsVehicle && i.StartMileage && i.EndMileage)
    .reduce((sum, i) => sum + (i.EndMileage - i.StartMileage), 0)
  return driven ? `${summary} · ${new Intl.NumberFormat('de-DE').format(driven)} km gefahren` : summary
}

function openRental(id) {
  if (Number(route.params.id) === id) {
    detailModal.value?.open(id)
  } else {
    router.push({ name: 'InventoryRentals', params: { id } })
  }
}

function onDetailClosed() {
  if (route.params.id) router.replace({ name: 'InventoryRentals' })
}

// Die ID in der URL (z.B. aus einer Benachrichtigung) öffnet die Detailansicht
watch(() => route.params.id, (id) => {
  if (id) detailModal.value?.open(Number(id))
})

onMounted(async () => {
  if (route.params.id) detailModal.value?.open(Number(route.params.id))
  await store.fetchRentals()
})
</script>
