<template>
  <div class="section section--MarketingPage">
    <div class="section_content">

      <div class="marketing-toolbar">
        <div class="marketing-toolbar_filters">
          <select v-model="yearFilter" class="input" @change="onYearChange">
            <option :value="null">Alle Jahre</option>
            <option v-for="year in store.years" :key="year" :value="year">{{ year }}</option>
          </select>

          <select v-if="store.organizations.length > 1" v-model="orgFilter" class="input" @change="onOrgChange">
            <option :value="null">Alle Organisationen</option>
            <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
          </select>
        </div>

        <div class="marketing-toolbar_actions">
          <AppIconButton
            variant="neutral"
            aria-label="Statistiken"
            title="Statistiken"
            @click="router.push({ name: 'MarketingStatistics' })"
          >
            <span class="icon-mask" :style="statisticsIconStyle" />
          </AppIconButton>
          <AppButton
            v-if="store.canManageSizes"
            variant="secondary"
            @click="openSizeManager"
          >
            Plakat-Größen verwalten
          </AppButton>
          <AppButton variant="primary" @click="entryModal?.open()">
            + Neuer Eintrag
          </AppButton>
        </div>
      </div>

      <div v-if="store.loading" class="section_infobox">
        <p>Lade Einträge…</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="store.fetchDistributions(true)">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="store.distributions.length === 0" class="section_infobox">
        <p>Noch keine Verteil-Einträge vorhanden.</p>
      </div>

      <div v-else class="marketing-list">
        <div v-for="entry in store.distributions" :key="entry.ID" class="marketing-list-row">
          <div class="marketing-list-row_avatar">
            <img
              v-if="entry.Member?.Avatar"
              :src="entry.Member.Avatar"
              :title="entry.Member.Name"
              :alt="entry.Member.Name"
              class="marketing-list-row_avatar-img"
            />
          </div>

          <span class="marketing-list-row_date">{{ formatDate(entry.DistributedAt) }}</span>
          <span class="marketing-list-row_quantity">{{ entry.Quantity }}×</span>
          <span
            v-if="entry.PosterSize"
            class="marketing-list-row_size"
            :style="{ backgroundColor: pastelColorForId(entry.PosterSize.ID) }"
          >{{ entry.PosterSize.Title }}</span>
          <span v-else class="marketing-list-row_size">–</span>
          <span class="marketing-list-row_location">{{ entry.Location || 'Erfasste Position' }}</span>

          <div class="marketing-list-row_map">
            <AppIconButton
              v-if="entry.Latitude && entry.Longitude"
              variant="ghost"
              aria-label="Auf Karte öffnen"
              title="Auf Karte öffnen"
              @click="openMap(entry)"
            >
              <span class="icon-mask" :style="locationIconStyle" />
            </AppIconButton>
          </div>

          <div class="marketing-list-row_actions">
            <AppIconButton
              v-if="entry.CanEdit"
              variant="primary"
              aria-label="Eintrag bearbeiten"
              title="Bearbeiten"
              @click="entryModal?.open(entry)"
            >
              <span class="icon-mask" :style="editIconStyle" />
            </AppIconButton>
            <AppIconButton
              v-if="entry.CanDelete"
              variant="danger"
              aria-label="Eintrag löschen"
              title="Löschen"
              @click="removeEntry(entry)"
            >
              <span class="icon-mask" :style="trashIconStyle" />
            </AppIconButton>
          </div>

          <p v-if="entry.Note" class="marketing-list-row_note">{{ entry.Note }}</p>
        </div>
      </div>

    </div>

    <MarketingEntryModal ref="entryModal" @manage-sizes="openSizeManager" />
    <MarketingSizeManagerModal
      ref="sizeManagerModal"
      :organizations="store.organizations"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMarketingStore } from '@stores/marketing'
import { usePageHeaderStore } from '@stores/pageHeader'
import { pastelColorForId } from '@utils/colors'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import MarketingEntryModal from '@components/MarketingEntryModal.vue'
import MarketingSizeManagerModal from '@components/MarketingSizeManagerModal.vue'
import actionEdit from '../../../icons/actions/action_edit.svg'
import actionTrash from '../../../icons/actions/action_trash.svg'
import actionLocation from '../../../icons/actions/action_location.svg'
import actionStatistics from '../../../icons/actions/action_statistics.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }
const locationIconStyle = { maskImage: `url("${actionLocation}")`, WebkitMaskImage: `url("${actionLocation}")` }
const statisticsIconStyle = { maskImage: `url("${actionStatistics}")`, WebkitMaskImage: `url("${actionStatistics}")` }

const router = useRouter()
const store = useMarketingStore()
usePageHeaderStore().setHeader('Marketing', 'Plakat-Verteilung deiner Organisationen.')

const entryModal = ref(null)
const sizeManagerModal = ref(null)
const yearFilter = ref(null)
const orgFilter = ref(null)

function onYearChange() {
  store.setYearFilter(yearFilter.value)
  store.fetchDistributions(true)
}

function onOrgChange() {
  store.setOrganizationFilter(orgFilter.value)
  store.fetchDistributions(true)
}

function openSizeManager() {
  sizeManagerModal.value?.open()
}

async function removeEntry(entry) {
  if (!confirm(`Eintrag "${entry.Location || 'Erfasste Position'}" wirklich löschen?`)) return
  await store.deleteDistribution(entry.ID)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }).format(new Date(dateStr))
}

function openMap(entry) {
  window.open(`https://www.google.com/maps?q=${entry.Latitude},${entry.Longitude}`, '_blank', 'noopener')
}

onMounted(() => store.fetchDistributions())
</script>
