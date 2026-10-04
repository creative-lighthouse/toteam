<template>
  <div class="section section--EventsPage">
    <div class="section_content">

      <AppSearchBar v-model="search" placeholder="Events durchsuchen…">
        <template v-if="managedOrgs.length > 0" #actions>
          <AppButton variant="primary" @click="formModal?.open()">+ Event anlegen</AppButton>
        </template>
      </AppSearchBar>

      <div v-if="orgEventsStore.loading && !orgEventsStore.events.length" class="section_infobox">
        <p>Lade Events…</p>
      </div>

      <div v-else-if="orgEventsStore.error" class="section_infobox error">
        <p>Fehler beim Laden: {{ orgEventsStore.error }}</p>
        <AppButton variant="primary" @click="orgEventsStore.fetchEvents()">Erneut versuchen</AppButton>
      </div>

      <template v-else>
        <section v-for="group in groups" :key="group.key" class="events-group">
          <h2 class="events-group_title">{{ group.title }}</h2>
          <ul class="org-event-grid">
            <li v-for="event in group.events" :key="event.ID">
              <OrgEventCard
                :event="event"
                :to="{ name: 'EventDetail', params: { segment: event.URLSegment } }"
                :show-organization="authStore.hasMultipleOrganizations"
              />
            </li>
          </ul>
        </section>

        <div v-if="groups.length === 0" class="section_infobox">
          <p v-if="search.trim()">Keine Events gefunden.</p>
          <p v-else>Es gibt noch keine Events. Ein Event bündelt mehrere Termine, z.B. Aufbau, Shows und Abbau.</p>
        </div>
      </template>

    </div>

    <OrgEventFormModal
      ref="formModal"
      :orgs="managedOrgs"
      @saved="event => router.push({ name: 'EventDetail', params: { segment: event.URLSegment } })"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useAuthStore } from '@stores/auth'
import { useOrganizationsStore } from '@stores/organizations'
import { useOrgEventsStore } from '@stores/orgEvents'
import AppButton from '@components/ui/AppButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import OrgEventCard from '@components/events/OrgEventCard.vue'
import OrgEventFormModal from '@components/events/OrgEventFormModal.vue'

usePageHeaderStore().setHeader('Events', 'Alle Events deiner Organisationen mit ihren Terminen.')

const authStore = useAuthStore()
const orgsStore = useOrganizationsStore()
const orgEventsStore = useOrgEventsStore()

const router = useRouter()

const search = ref('')
const formModal = ref(null)

const managedOrgs = computed(() =>
  orgsStore.organizations.filter(o => o.MembershipStatus === 'member' && o.Permissions?.includes('CALENDAR_MANAGE'))
)

const groups = computed(() => {
  const q = search.value.trim().toLowerCase()
  const today = new Date().toISOString().slice(0, 10)
  const events = orgEventsStore.sortedEvents.filter(e =>
    !q || e.Title.toLowerCase().includes(q) || e.OrganizationTitle?.toLowerCase().includes(q)
  )
  return [
    { key: 'upcoming', title: 'Aktuell & geplant', events: events.filter(e => e.RangeStart && (e.RangeEnd ?? e.RangeStart) >= today) },
    { key: 'empty', title: 'Ohne Datum', events: events.filter(e => !e.RangeStart) },
    { key: 'past', title: 'Vergangen', events: events.filter(e => e.RangeStart && (e.RangeEnd ?? e.RangeStart) < today) },
  ].filter(g => g.events.length)
})

onMounted(() => {
  orgEventsStore.fetchEvents()
  orgsStore.fetchOrganizations()
})
</script>
