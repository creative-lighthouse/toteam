<template>
  <div class="section section--MarketingMapPage">
    <div class="section_content">

      <div class="marketing-map-toolbar">
        <AppIconButton variant="ghost" aria-label="Zurück zur Übersicht" title="Zurück" @click="router.push({ name: 'Marketing' })">
          <span class="icon-mask" :style="backIconStyle" />
        </AppIconButton>

        <div class="marketing-map-toolbar_filters">
          <select v-model="yearFilter" class="input" aria-label="Nach Jahr filtern" @change="onFilterChange">
            <option :value="null">Alle Jahre</option>
            <option v-for="year in store.years" :key="year" :value="year">{{ year }}</option>
          </select>

          <select v-if="store.organizations.length > 1" v-model="orgFilter" class="input" aria-label="Nach Organisation filtern" @change="onFilterChange">
            <option :value="null">Alle Organisationen</option>
            <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
          </select>

          <select v-if="store.sizes.length > 1" class="input" aria-label="Nach Plakat-Art filtern" :value="store.filterSize ?? ''" @change="onSizeChange($event.target.value)">
            <option value="">Alle Plakat-Arten</option>
            <option v-for="size in store.sizes" :key="size.ID" :value="size.ID">{{ size.Title }}</option>
          </select>
        </div>
      </div>

      <div v-if="!loaded" class="section_infobox">
        <p>Lade Einträge…</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.fetchDistributions(true)">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="!store.mapConfig" class="section_infobox">
        <p>Es sind noch keine Kartendaten vorhanden.</p>
      </div>

      <template v-else>
        <MarketingMap :entries="entriesWithPosition" :config="store.mapConfig" />

        <div class="marketing-map-legend">
          <span v-for="size in legendSizes" :key="size.ID" class="marketing-map-legend_item">
            <span
              class="marketing-map-legend_dot"
              :style="{ backgroundColor: pastelColorForId(size.ID), borderColor: pastelTextColorForId(size.ID) }"
            />
            {{ size.Title }} ({{ size.Total }})
          </span>
          <span v-if="hasApproximate" class="marketing-map-legend_item marketing-map-legend_item--approx">
            <span class="marketing-map-legend_dot" />
            blass = Position aus dem Ort ermittelt (ungefähr)
          </span>
        </div>

        <p class="marketing-map-summary">
          {{ entriesWithPosition.length }} von {{ store.filteredDistributions.length }} Einträgen haben eine Position.
        </p>
      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMarketingStore } from '@stores/marketing'
import { usePageHeaderStore } from '@stores/pageHeader'
import { pastelColorForId, pastelTextColorForId } from '@utils/colors'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import MarketingMap from '@components/marketing/MarketingMap.vue'
import actionBack from '../../../icons/actions/action_back.svg'

const backIconStyle = { maskImage: `url("${actionBack}")`, WebkitMaskImage: `url("${actionBack}")` }

const router = useRouter()
const store = useMarketingStore()
usePageHeaderStore().setHeader('Marketing-Karte', 'Wo schon Plakate verteilt wurden.')

const yearFilter = ref(store.filterYear)
const orgFilter = ref(store.filterOrganization)
// Die Karte erst nach dem ersten Laden zeigen, damit sie gleich auf die Punkte zoomt
const loaded = ref(false)

const entriesWithPosition = computed(() => store.filteredDistributions.filter(e => e.Latitude && e.Longitude))
const hasApproximate = computed(() => entriesWithPosition.value.some(e => e.CoordinatesSource === 'Address'))

// Plakate pro Größe unter den gezeigten Punkten
const legendSizes = computed(() => {
  const totals = new Map()
  for (const entry of entriesWithPosition.value) {
    const id = entry.PosterSize?.ID ?? 0
    const size = totals.get(id) ?? { ID: id, Title: entry.PosterSize?.Title || 'Ohne Größe', Total: 0 }
    size.Total += entry.Quantity
    totals.set(id, size)
  }
  return [...totals.values()].sort((a, b) => b.Total - a.Total)
})

function onFilterChange() {
  store.setYearFilter(yearFilter.value)
  store.setOrganizationFilter(orgFilter.value)
  store.fetchDistributions(true)
}

// Rein clientseitiger Filter (kein Server-Roundtrip nötig, siehe Store)
function onSizeChange(value) {
  store.setSizeFilter(value ? parseInt(value) : null)
}

onMounted(async () => {
  await store.fetchDistributions()
  loaded.value = true
})
</script>
