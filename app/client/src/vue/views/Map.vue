<template>
  <div class="section section--MapPage">
    <div class="section_content">
      <div v-if="loading" class="section_infobox">
        <p>Lade Lagepläne...</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>Fehler beim Laden: {{ error }}</p>
        <AppButton variant="primary" @click="loadMaps">Erneut versuchen</AppButton>
      </div>

      <template v-else>
        <div v-if="canManageAny" class="map-list_actions">
          <AppButton variant="primary" @click="createModal?.open()">+ Neuen Lageplan erstellen</AppButton>
          <AppIconButton to="/rooms" variant="neutral" aria-label="Räume verwalten" title="Räume verwalten">
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet } from '@utils/api'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import MapCreateModal from '@components/maps/MapCreateModal.vue'
import actionRoom from '../../../icons/actions/action_room.svg'

const router = useRouter()
const createModal = ref(null)
const roomIconStyle = { maskImage: `url("${actionRoom}")`, WebkitMaskImage: `url("${actionRoom}")` }

usePageHeaderStore().setHeader('Lagepläne', 'Finde Orte und POIs auf der Karte.')

const maps = ref([])
const canManageAny = ref(false)
const loading = ref(true)
const error = ref(null)

async function loadMaps() {
  loading.value = true
  error.value = null
  try {
    const data = await apiGet('/maps', false)
    maps.value = data.maps || []
    canManageAny.value = data.canManageAny || false
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function onMapCreated(mapId) {
  router.push(`/map/${mapId}`)
}

onMounted(loadMaps)
</script>
