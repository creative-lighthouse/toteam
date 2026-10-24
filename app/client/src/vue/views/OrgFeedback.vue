<template>
  <div class="section section--OrgFeedbackPage">
    <div class="section_content">

      <div class="org-feedback-toolbar">
        <div class="org-feedback-toolbar_filters">
          <div class="field org-feedback-toolbar_filter" role="group" aria-labelledby="org-feedback-filter-label">
            <span id="org-feedback-filter-label" class="org-feedback-toolbar_label">Filter</span>
            <div class="org-feedback-toolbar_selects">
              <select
                v-if="store.organizations.length > 1"
                v-model="filters.organization"
                class="input"
                aria-label="Organisation"
              >
                <option :value="0">Alle Organisationen</option>
                <option v-for="org in store.organizations" :key="org.ID" :value="org.ID">{{ org.Title }}</option>
              </select>

              <select v-model="filters.event" class="input" aria-label="Wozu">
                <option value="">Alles</option>
                <option value="general">Allgemein</option>
                <option v-for="ev in filterEvents" :key="ev.ID" :value="String(ev.ID)">{{ ev.Title }}</option>
              </select>
            </div>
          </div>

          <AppSegmentedToggle
            v-model="filters.sort"
            :options="[{ value: 'votes', label: 'Meiste +1' }, { value: 'newest', label: 'Neueste' }]"
            label="Sortierung"
          />
        </div>

        <!-- Rechts neben der Sortierung, wie in den anderen Totems -->
        <AppButton
          variant="primary"
          class="org-feedback-toolbar_new"
          title="Feedback geben"
          aria-label="Feedback geben"
          :disabled="!store.organizations.length"
          @click="openCreate"
        >+ Neu</AppButton>
      </div>

      <div v-if="store.loading" class="section_infobox">
        <p>Lade Feedback…</p>
      </div>

      <div v-else-if="store.error" class="section_infobox error">
        <p>Fehler: {{ store.error }}</p>
        <AppButton variant="primary" @click="load">Erneut versuchen</AppButton>
      </div>

      <div v-else-if="!store.organizations.length" class="section_infobox">
        <p>In keiner deiner Organisationen ist das Feedback-Totem aktiv.</p>
      </div>

      <div v-else-if="store.items.length === 0" class="section_infobox">
        <p>{{ hasFilter ? 'Hierzu gibt es noch kein Feedback.' : 'Noch kein Feedback vorhanden.' }}</p>
      </div>

      <OrgFeedbackList v-else :items="store.items" @open="openDetail" @error="listError = $event" />

      <p v-if="listError" class="org-feedback-toolbar_error">{{ listError }}</p>
    </div>

    <OrgFeedbackFormModal ref="formModal" @saved="onSaved" @open="openDetail" />
    <OrgFeedbackDetailModal ref="detailModal" @edit="formModal?.openForEdit($event)" @closed="onDetailClosed" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import { usePageHeaderStore } from '@stores/pageHeader'
import AppButton from '@components/ui/AppButton.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import OrgFeedbackList from '@components/feedback/OrgFeedbackList.vue'
import OrgFeedbackFormModal from '@components/feedback/OrgFeedbackFormModal.vue'
import OrgFeedbackDetailModal from '@components/feedback/OrgFeedbackDetailModal.vue'

const route = useRoute()
const router = useRouter()
const store = useOrgFeedbackStore()
usePageHeaderStore().setHeader('Feedback', 'Feedback zu Events und zu deinen Organisationen.')

const formModal = ref(null)
const detailModal = ref(null)
const listError = ref(null)

// Filter stehen in der URL (?organization=, ?event=, ?sort=), damit sie ein Neuladen überstehen
const filters = reactive({
  organization: Number(route.query.organization) || 0,
  event: route.query.event ? String(route.query.event) : '',
  sort: route.query.sort === 'newest' ? 'newest' : 'votes',
})

const hasFilter = computed(() => !!(filters.organization || filters.event))

// Events nur der gewählten Organisation
const filterEvents = computed(() => (filters.organization
  ? store.events.filter(e => e.OrganizationID === filters.organization)
  : store.events))

function load() {
  return store.fetchList({
    organization: filters.organization || null,
    event: filters.event || null,
    sort: filters.sort,
  })
}

watch(() => filters.organization, () => {
  if (filters.event && filters.event !== 'general' && !filterEvents.value.some(e => String(e.ID) === filters.event)) {
    filters.event = ''
  }
})

watch(filters, () => {
  const query = { ...route.query }
  for (const key of ['organization', 'event', 'sort']) delete query[key]
  if (filters.organization) query.organization = filters.organization
  if (filters.event) query.event = filters.event
  if (filters.sort !== 'votes') query.sort = filters.sort
  router.replace({ query })
  load()
})

function openCreate() {
  const eventId = filters.event && filters.event !== 'general' ? Number(filters.event) : 0
  formModal.value?.open({ eventId, organizationId: filters.organization })
}

// Detail über ?id= — so führen auch Benachrichtigungen direkt hin
function openDetail(item) {
  router.replace({ query: { ...route.query, id: item.ID } })
}

function onDetailClosed() {
  const { id: _id, ...query } = route.query
  router.replace({ query })
}

watch(() => route.query.id, (id) => {
  if (id) detailModal.value?.open(Number(id))
})

function onSaved(feedback) {
  // Neues Feedback gleich öffnen, bearbeitetes im offenen Modal aktualisieren
  if (Number(route.query.id) === feedback.ID) {
    detailModal.value?.setFeedback(feedback)
  } else {
    openDetail(feedback)
  }
}

onMounted(async () => {
  await load()
  if (route.query.id) detailModal.value?.open(Number(route.query.id))
})
</script>
