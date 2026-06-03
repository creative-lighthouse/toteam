<template>
  <div class="section section--MapCreate">
    <div class="section_content">
      <div v-if="loadingOrgs" class="section_infobox">
        <p>Lade Organisationen...</p>
      </div>

      <div v-else-if="organizations.length === 0" class="section_infobox">
        <p>Du hast keine Organisation, für die du Lagepläne erstellen kannst.</p>
        <router-link to="/map" class="button">← Zurück</router-link>
      </div>

      <form v-else class="map-create-form" @submit.prevent="createMap">
        <div class="form-field">
          <label for="mapTitle">Titel *</label>
          <input id="mapTitle" v-model="form.title" type="text" class="form-control" required placeholder="z.B. Vereinsgelände" />
        </div>

        <div class="form-field">
          <label for="mapShortText">Beschreibung</label>
          <textarea id="mapShortText" v-model="form.shortText" class="form-control" rows="3" placeholder="Kurze Beschreibung des Lageplans"></textarea>
        </div>

        <div class="form-field">
          <label for="mapOrg">Organisation *</label>
          <select id="mapOrg" v-model="form.organizationId" class="form-control" required>
            <option value="" disabled>Organisation wählen...</option>
            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.title }}</option>
          </select>
        </div>

        <div class="form-field">
          <label for="mapBgImage">Hintergrundbild</label>
          <div class="map-create-preview" v-if="bgPreviewUrl">
            <img :src="bgPreviewUrl" alt="Vorschau" />
          </div>
          <input id="mapBgImage" type="file" class="form-control" accept="image/*" @change="onBgImageSelected" />
        </div>

        <details class="form-details">
          <summary>Koordinaten (optional)</summary>
          <div class="form-field">
            <label for="coordUL">Oben links (Lat, Lng)</label>
            <input id="coordUL" v-model="form.coordinatesUpperLeft" type="text" class="form-control" placeholder="53.6371, 10.3829" aria-label="Koordinaten oben links" />
          </div>
          <div class="form-field">
            <label for="coordUR">Oben rechts (Lat, Lng)</label>
            <input id="coordUR" v-model="form.coordinatesUpperRight" type="text" class="form-control" placeholder="53.6369, 10.3834" aria-label="Koordinaten oben rechts" />
          </div>
          <div class="form-field">
            <label for="coordLL">Unten links (Lat, Lng)</label>
            <input id="coordLL" v-model="form.coordinatesLowerLeft" type="text" class="form-control" placeholder="53.6369, 10.3824" aria-label="Koordinaten unten links" />
          </div>
          <div class="form-field">
            <label for="coordLR">Unten rechts (Lat, Lng)</label>
            <input id="coordLR" v-model="form.coordinatesLowerRight" type="text" class="form-control" placeholder="53.6365, 10.3829" aria-label="Koordinaten unten rechts" />
          </div>
        </details>

        <div v-if="error" class="section_infobox error">
          <p>{{ error }}</p>
        </div>

        <div class="form-actions">
          <router-link to="/map" class="button button--secondary">Abbrechen</router-link>
          <button type="submit" class="button" :disabled="saving">
            {{ saving ? 'Wird erstellt…' : 'Lageplan erstellen' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost, apiPostForm, clearCacheForEndpoint } from '@utils/api'

usePageHeaderStore().setHeader('Neuen Lageplan erstellen', '')

const router = useRouter()
const organizations = ref([])
const loadingOrgs = ref(true)
const saving = ref(false)
const error = ref(null)

const form = ref({
  title: '',
  shortText: '',
  organizationId: '',
  coordinatesUpperLeft: '',
  coordinatesUpperRight: '',
  coordinatesLowerLeft: '',
  coordinatesLowerRight: '',
})
const bgPreviewUrl = ref('')
let pendingBgFile = null

async function loadOrgs() {
  try {
    const data = await apiGet('/maps/managedorgs', false)
    organizations.value = data.organizations || []
    if (organizations.value.length === 1) {
      form.value.organizationId = organizations.value[0].id
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loadingOrgs.value = false
  }
}

function onBgImageSelected(e) {
  const file = e.target.files[0]
  if (!file) return
  pendingBgFile = file
  bgPreviewUrl.value = URL.createObjectURL(file)
}

async function createMap() {
  error.value = null
  saving.value = true
  try {
    const result = await apiPost('/maps/createmap', {
      title: form.value.title,
      shortText: form.value.shortText,
      organizationId: form.value.organizationId,
      coordinatesUpperLeft: form.value.coordinatesUpperLeft,
      coordinatesUpperRight: form.value.coordinatesUpperRight,
      coordinatesLowerLeft: form.value.coordinatesLowerLeft,
      coordinatesLowerRight: form.value.coordinatesLowerRight,
    })

    const newMapId = result.data.mapId

    await clearCacheForEndpoint('/maps')

    if (pendingBgFile) {
      const formData = new FormData()
      formData.append('image', pendingBgFile)
      await apiPostForm(`/maps/uploadbackgroundimage/${newMapId}`, formData)
    }

    router.push(`/map/${newMapId}`)
  } catch (e) {
    error.value = e.message
    saving.value = false
  }
}

onMounted(loadOrgs)
</script>
