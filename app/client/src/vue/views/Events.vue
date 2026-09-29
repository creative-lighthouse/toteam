<template>
  <div class="section section--EventsPage">
    <div class="section_content">

      <AppSearchBar v-model="search" placeholder="Events durchsuchen…">
        <template v-if="managedOrgs.length > 0" #actions>
          <AppButton variant="primary" @click="createModal?.open()">+ Event anlegen</AppButton>
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
          <ul class="events-list">
            <li v-for="event in group.events" :key="event.ID">
              <button type="button" class="org-event-card" @click="detailModal?.open(event)">
                <AppOrgLogo
                  v-if="authStore.hasMultipleOrganizations"
                  :src="event.OrganizationLogoURL"
                  :alt="event.OrganizationTitle"
                  :name="event.OrganizationTitle"
                  :size="40"
                  class="org-event-card_logo"
                />
                <span class="org-event-card_body">
                  <span class="org-event-card_title">{{ event.Title }}</span>
                  <span class="org-event-card_meta">
                    <template v-if="event.DateStart">{{ formatDateRange(event.DateStart, event.DateEnd) }} · </template>
                    {{ event.AppointmentCount === 1 ? '1 Termin' : `${event.AppointmentCount} Termine` }}
                    <template v-if="event.FoodCount"> · {{ event.FoodCount === 1 ? '1 Gericht' : `${event.FoodCount} Gerichte` }}</template>
                  </span>
                </span>
                <span class="org-event-card_arrow" aria-hidden="true">›</span>
              </button>
            </li>
          </ul>
        </section>

        <div v-if="groups.length === 0" class="section_infobox">
          <p v-if="search.trim()">Keine Events gefunden.</p>
          <p v-else>Es gibt noch keine Events. Ein Event bündelt mehrere Termine, z.B. Aufbau, Shows und Abbau.</p>
        </div>
      </template>

    </div>

    <OrgEventDetailModal ref="detailModal" />
    <OrgEventCreateModal ref="createModal" :orgs="managedOrgs" @created="event => detailModal?.open(event)" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useAuthStore } from '@stores/auth'
import { useOrganizationsStore } from '@stores/organizations'
import { useOrgEventsStore } from '@stores/orgEvents'
import { formatDateRange } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppSearchBar from '@components/ui/AppSearchBar.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import OrgEventDetailModal from '@components/events/OrgEventDetailModal.vue'
import OrgEventCreateModal from '@components/events/OrgEventCreateModal.vue'

usePageHeaderStore().setHeader('Events', 'Alle Events deiner Organisationen mit ihren Terminen.')

const authStore = useAuthStore()
const orgsStore = useOrganizationsStore()
const orgEventsStore = useOrgEventsStore()

const search = ref('')
const detailModal = ref(null)
const createModal = ref(null)

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
    { key: 'upcoming', title: 'Aktuell & geplant', events: events.filter(e => e.DateStart && (e.DateEnd ?? e.DateStart) >= today) },
    { key: 'empty', title: 'Ohne Termine', events: events.filter(e => !e.DateStart) },
    { key: 'past', title: 'Vergangen', events: events.filter(e => e.DateStart && (e.DateEnd ?? e.DateStart) < today) },
  ].filter(g => g.events.length)
})

onMounted(() => {
  orgEventsStore.fetchEvents()
  orgsStore.fetchOrganizations()
})
</script>
