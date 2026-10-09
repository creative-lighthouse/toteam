<template>
  <div class="section section--MapPage" :class="{ 'has-tab-nav': hasEventPlans }">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Lagepläne...</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler beim Laden: {{ error }}</p>
        <AppButton variant="primary" @click="loadMaps">Erneut versuchen</AppButton>
      </div>

      <!-- ── Events: Lagepläne der Events mit dem Event auf der Karte ───────── -->
      <template v-else-if="activeTab === 'events'">
        <ul v-if="upcomingEventPlans.length" class="map-list">
          <li v-for="entry in upcomingEventPlans" :key="`${entry.event.ID}-${entry.mapId}`" class="map-entry">
            <MapEventPlanLink :entry="entry" />
          </li>
        </ul>
        <div v-else class="section_infobox">
          <p>Keine anstehenden Events mit Lageplan.</p>
        </div>

        <AppCollapse
          v-if="pastEventPlans.length"
          :title="`Vergangene Events (${pastEventPlans.length})`"
          class="map-list_archive"
        >
          <ul class="map-list">
            <li v-for="entry in pastEventPlans" :key="`${entry.event.ID}-${entry.mapId}`" class="map-entry">
              <MapEventPlanLink :entry="entry" />
            </li>
          </ul>
        </AppCollapse>
      </template>

      <template v-else>
        <div v-if="canManageAny" class="map-list_actions">
          <AppButton variant="primary" @click="createModal?.open()">+ Neuen Lageplan erstellen</AppButton>
          <AppIconButton :to="{ name: 'Inventory', query: { tab: 'rooms' } }" variant="neutral" aria-label="Räume verwalten" title="Räume verwalten">
            <span class="icon-mask" :style="roomIconStyle" />
          </AppIconButton>
        </div>

        <ul v-if="maps.length" class="map-list">
          <li v-for="map in maps" :key="map.id" class="map-entry">
            <router-link :to="`/map/${map.id}`" class="map-entry_link">
              <div class="map-entry_thumbnail">
                <img v-if="map.thumbnailUrl" :src="map.thumbnailUrl" :alt="map.title" />
                <div v-else class="map-entry_thumbnail--placeholder"></div>
                <AppOrgLogo
                  v-if="map.organizationTitle"
                  :src="map.organizationLogoUrl"
                  :alt="map.organizationTitle"
                  :size="36"
                  class="map-entry_org-logo"
                />
              </div>
              <div class="map-entry_info">
                <h3 class="map-entry_title">{{ map.title }}</h3>
                <p v-if="map.shortText" class="map-entry_description">{{ map.shortText }}</p>
              </div>
            </router-link>
          </li>
        </ul>

        <div v-else class="section_infobox">
          <p>Keine Lagepläne verfügbar.</p>
        </div>
      </template>
    </div>

    <MapCreateModal ref="createModal" @created="onMapCreated" />

    <!-- Nur, wenn es Events mit Lageplan gibt -->
    <AppTabNav v-if="hasEventPlans" :tabs="TABS" :model-value="activeTab" label="Lagepläne" @update:model-value="selectTab" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import AppCollapse from '@components/ui/AppCollapse.vue'
import AppTabNav from '@components/ui/AppTabNav.vue'
import MapEventPlanLink from '@components/maps/MapEventPlanLink.vue'
import MapIcon from '../../../icons/actions/action_map.svg'
import EventIcon from '../../../icons/actions/action_schedule.svg'
import MapCreateModal from '@components/maps/MapCreateModal.vue'
import actionRoom from '../../../icons/actions/action_room.svg'

const route = useRoute()
const router = useRouter()
const createModal = ref(null)
const roomIconStyle = { maskImage: `url("${actionRoom}")`, WebkitMaskImage: `url("${actionRoom}")` }

usePageHeaderStore().setHeader('Lagepläne', 'Finde Orte und POIs auf der Karte.')

const maps = ref([])
// Lagepläne von Events der eigenen Organisationen: [{ mapId, title, thumbnailUrl, placedCount, event }]
const eventPlans = ref([])
const canManageAny = ref(false)
const loading = ref(true)
const error = ref(null)

async function loadMaps() {
  loading.value = true
  error.value = null
  try {
    const data = await apiGet('/maps', false)
    maps.value = data.maps || []
    eventPlans.value = data.eventPlans || []
    canManageAny.value = data.canManageAny || false
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// ── Tabs "Allgemein" / "Events" (in der URL, damit "Zurück" im richtigen Tab landet) ──

const TABS = [
  { id: 'general', label: 'Allgemein', icon: MapIcon },
  { id: 'events', label: 'Events', icon: EventIcon },
]

const hasEventPlans = computed(() => eventPlans.value.length > 0)
const activeTab = computed(() => (route.query.tab === 'events' && hasEventPlans.value ? 'events' : 'general'))

function selectTab(tab) {
  const { tab: _tab, ...rest } = route.query
  router.replace({ query: tab === 'events' ? { ...rest, tab } : rest })
}

// Laufende und kommende Events zuerst (nach Beginn), Events ohne Datum danach;
// vergangene im Archiv, das jüngste zuerst
const today = new Date().toISOString().slice(0, 10)
const isPast = entry => !!entry.event.RangeStart && (entry.event.RangeEnd ?? entry.event.RangeStart) < today
const byTitle = (a, b) => a.event.Title.localeCompare(b.event.Title, 'de') || a.title.localeCompare(b.title, 'de')

const upcomingEventPlans = computed(() => eventPlans.value.filter(e => !isPast(e)).sort((a, b) =>
  (!a.event.RangeStart - !b.event.RangeStart)
  || (a.event.RangeStart ?? '').localeCompare(b.event.RangeStart ?? '')
  || byTitle(a, b)
))

const pastEventPlans = computed(() => eventPlans.value.filter(isPast).sort((a, b) =>
  b.event.RangeStart.localeCompare(a.event.RangeStart) || byTitle(a, b)
))

function onMapCreated(mapId) {
  router.push(`/map/${mapId}`)
}

onMounted(loadMaps)
</script>
