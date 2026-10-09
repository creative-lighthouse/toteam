<template>
  <div class="section section--MarketingStatisticsPage">
    <div class="section_content">

      <div class="marketing-stats-toolbar">
        <AppIconButton variant="ghost" aria-label="Zurück zur Übersicht" title="Zurück" @click="router.push({ name: 'Marketing' })">
          <span class="icon-mask" :style="backIconStyle" />
        </AppIconButton>

        <div class="marketing-stats-toolbar_filters">
          <select v-model="yearFilter" class="input" aria-label="Nach Jahr filtern" @change="onFilterChange">
            <option :value="null">Alle Jahre</option>
            <option v-for="year in store.years" :key="year" :value="year">{{ year }}</option>
          </select>

          <select v-if="store.organizations.length > 1" v-model="orgFilter" class="input" aria-label="Nach Organisation filtern" @change="onFilterChange">
            <option :value="null">Alle Organisationen</option>
            <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
          </select>
        </div>
      </div>

      <div v-if="store.statisticsLoading" class="section_infobox">
        <p>Lade Statistiken…</p>
      </div>

      <div v-else-if="store.statisticsError" class="section_infobox error">
        <p>Fehler: {{ store.statisticsError }}</p>
        <AppButton variant="primary" @click="store.fetchStatistics(true)">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="!store.statistics?.sizes?.length" class="section_infobox">
        <p>Noch keine Daten für Statistiken vorhanden.</p>
      </div>

      <div v-else class="marketing-stats-grid">
        <div class="marketing-stats-card">
          <h3 class="hl3 marketing-stats-card_title">Verteilte Plakate pro Größe</h3>
          <div class="marketing-stats-card_canvas">
            <canvas ref="sizeChartCanvas" />
          </div>
        </div>

        <div class="marketing-stats-card">
          <h3 class="hl3 marketing-stats-card_title">Verteilte Plakate pro Mitglied</h3>
          <div class="marketing-stats-card_canvas">
            <canvas ref="memberChartCanvas" />
          </div>
        </div>

        <div class="marketing-stats-card">
          <div class="marketing-stats-card_header">
            <h3 class="hl3 marketing-stats-card_title">Verteilte Plakate pro Ort</h3>
            <label v-if="store.statistics.districtLabels?.length" class="marketing-stats-card_toggle">
              <input v-model="showDistricts" type="checkbox" @change="renderCityChart" />
              Stadtteile
            </label>
          </div>
          <div class="marketing-stats-card_canvas">
            <canvas ref="cityChartCanvas" />
          </div>
          <p class="marketing-stats-card_hint">
            Ort und Stadtteil werden aus der GPS-Position oder dem eingegebenen Ort ermittelt.
          </p>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, BarController, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { useMarketingStore } from '@stores/marketing'
import { usePageHeaderStore } from '@stores/pageHeader'
import { pastelColorForId } from '@utils/colors'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionBack from '../../../icons/actions/action_back.svg'

Chart.register(BarController, CategoryScale, LinearScale, BarElement, Tooltip, Legend)

const backIconStyle = { maskImage: `url("${actionBack}")`, WebkitMaskImage: `url("${actionBack}")` }

const router = useRouter()
const store = useMarketingStore()
usePageHeaderStore().setHeader('Marketing-Statistiken', 'Auswertung der Plakat-Verteilung.')

const yearFilter = ref(store.filterYear)
const orgFilter = ref(store.filterOrganization)

const sizeChartCanvas = ref(null)
const memberChartCanvas = ref(null)
const cityChartCanvas = ref(null)
const showDistricts = ref(false)
let sizeChart = null
let memberChart = null
let cityChart = null

// Nach Ort oder — falls gewählt und vorhanden — nach Stadtteil
const cityData = computed(() => {
  const stats = store.statistics || {}
  return showDistricts.value && stats.districtLabels?.length
    ? { labels: stats.districtLabels, datasets: stats.districtDatasetsBySize || [] }
    : { labels: stats.cityLabels || [], datasets: stats.cityDatasetsBySize || [] }
})

// Während des Ladens wird das Raster ausgeblendet und danach mit neuen
// Canvas-Elementen eingeblendet — ein Chart am alten Canvas zeichnet ins Leere
function recreate(chart) {
  chart?.destroy()
  return null
}

function renderSizeChart() {
  if (!sizeChartCanvas.value) return
  if (sizeChart?.canvas !== sizeChartCanvas.value) sizeChart = recreate(sizeChart)
  const sizes = store.statistics?.sizes || []
  const labels = sizes.map(s => s.Title)
  const data = sizes.map(s => s.Total)
  const backgroundColor = sizes.map(s => pastelColorForId(s.ID))

  if (sizeChart) {
    sizeChart.data.labels = labels
    sizeChart.data.datasets[0].data = data
    sizeChart.data.datasets[0].backgroundColor = backgroundColor
    sizeChart.update()
    return
  }

  sizeChart = new Chart(sizeChartCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [{ label: 'Anzahl', data, backgroundColor }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

function renderMemberChart() {
  if (!memberChartCanvas.value) return
  if (memberChart?.canvas !== memberChartCanvas.value) memberChart = recreate(memberChart)
  const labels = store.statistics?.memberLabels || []
  const datasets = (store.statistics?.datasetsBySize || []).map(entry => ({
    label: entry.size,
    data: entry.data,
    backgroundColor: pastelColorForId(entry.sizeId),
  }))

  if (memberChart) {
    memberChart.data.labels = labels
    memberChart.data.datasets = datasets
    memberChart.update()
    return
  }

  memberChart = new Chart(memberChartCanvas.value, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { stacked: true },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
      },
    },
  })
}

function renderCityChart() {
  if (!cityChartCanvas.value) return
  if (cityChart?.canvas !== cityChartCanvas.value) cityChart = recreate(cityChart)
  const { labels } = cityData.value
  const datasets = cityData.value.datasets.map(entry => ({
    label: entry.size,
    data: entry.data,
    backgroundColor: pastelColorForId(entry.sizeId),
  }))

  if (cityChart) {
    cityChart.data.labels = labels
    cityChart.data.datasets = datasets
    cityChart.update()
    return
  }

  cityChart = new Chart(cityChartCanvas.value, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { stacked: true },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
      },
    },
  })
}

watch(() => store.statistics, async () => {
  await nextTick()
  renderSizeChart()
  renderMemberChart()
  renderCityChart()
}, { deep: true })

function onFilterChange() {
  store.setYearFilter(yearFilter.value)
  store.setOrganizationFilter(orgFilter.value)
  store.fetchStatistics(true)
}

onMounted(async () => {
  if (!store.organizations.length) {
    await store.fetchDistributions()
  }
  await store.fetchStatistics()
})

onUnmounted(() => {
  sizeChart?.destroy()
  memberChart?.destroy()
  cityChart?.destroy()
})
</script>
