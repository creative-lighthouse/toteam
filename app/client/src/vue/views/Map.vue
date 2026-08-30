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
          <AppButton to="/map/new" variant="primary">+ Neuen Lageplan erstellen</AppButton>
          <AppButton to="/rooms" variant="secondary">Räume verwalten</AppButton>
        </div>

        <ul v-if="maps.length" class="map-list">
          <li v-for="map in maps" :key="map.id" class="map-entry">
            <router-link :to="`/map/${map.id}`" class="map-entry_link">
              <div class="map-entry_thumbnail">
                <img v-if="map.thumbnailUrl" :src="map.thumbnailUrl" :alt="map.title" />
                <div v-else class="map-entry_thumbnail--placeholder"></div>
                <div v-if="map.organizationLogoUrl" class="map-entry_org-logo">
                  <img :src="map.organizationLogoUrl" :alt="map.organizationTitle" />
                </div>
              </div>
              <div class="map-entry_info">
                <h3 class="map-entry_title">{{ map.title }}</h3>
                <p v-if="map.shortText" class="map-entry_description">{{ map.shortText }}</p>
                <small v-if="map.organizationTitle && !map.organizationLogoUrl" class="map-entry_org">{{ map.organizationTitle }}</small>
              </div>
            </router-link>
          </li>
        </ul>

        <div v-else class="section_infobox">
          <p>Keine Lagepläne verfügbar.</p>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet } from '@utils/api'
import AppButton from '@components/AppButton.vue'

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

onMounted(loadMaps)
</script>
