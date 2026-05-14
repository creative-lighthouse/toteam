<template>
  <div class="section section--OrganizationsPage">
    <div class="section_content">
      <div v-if="store.loading" class="section_infobox">
        <p>Lade Organisationen...</p>
      </div>

      <div v-else-if="store.error" class="section_infobox">
        <p>Fehler beim Laden: {{ store.error }}</p>
        <button class="button" @click="store.fetchOrganizations(true)">Erneut versuchen</button>
      </div>

      <template v-else>
        <div v-if="store.organizations.length === 0" class="section_infobox">
          <p>Keine Organisationen gefunden.</p>
        </div>

        <div v-else class="organizations-list">
          <OrganizationCard
            v-for="org in store.organizations"
            :key="org.ID"
            :org="org"
            @joined="handleJoin"
            @manage-applicants="openApplicantsModal"
          />
        </div>
      </template>
    </div>

    <ApplicantsModal ref="applicantsModal" @accepted="handleApplicantAccepted" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useOrganizationsStore } from '@stores/organizations'
import { usePageHeaderStore } from '@stores/pageHeader'
import OrganizationCard from '@components/OrganizationCard.vue'
import ApplicantsModal from '@components/ApplicantsModal.vue'

const route = useRoute()
const store = useOrganizationsStore()
usePageHeaderStore().setHeader('Organisationen', 'Alle Organisationen auf einen Blick.')

const joiningID      = ref(null)
const applicantsModal = ref(null)

onMounted(async () => {
  await store.fetchOrganizations(true)

  const targetOrgID = route.query.applicants ? Number(route.query.applicants) : null
  if (targetOrgID) {
    const org = store.organizations.find(o => o.ID === targetOrgID)
    if (org) applicantsModal.value?.open(org)
  }
})

async function handleJoin(orgID) {
  if (joiningID.value) return
  joiningID.value = orgID

  try {
    const response = await store.joinOrganization(orgID)
    if (!response.success) {
      alert(response.error ?? 'Fehler beim Beitreten.')
    }
  } catch {
    alert('Fehler beim Beitreten.')
  } finally {
    joiningID.value = null
  }
}

function openApplicantsModal(org) {
  applicantsModal.value?.open(org)
}

function handleApplicantAccepted(orgID) {
  const org = store.organizations.find(o => o.ID === orgID)
  if (org) {
    org.MemberCount++
    if (org.ApplicantCount !== null) org.ApplicantCount--
  }
}
</script>
