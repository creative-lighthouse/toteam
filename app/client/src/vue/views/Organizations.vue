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
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import { usePageHeaderStore } from '@stores/pageHeader'
import OrganizationCard from '@components/OrganizationCard.vue'

const store = useOrganizationsStore()
usePageHeaderStore().setHeader('Organisationen', 'Alle Organisationen auf einen Blick.')

const joiningID = ref(null)

onMounted(async () => {
  await store.fetchOrganizations(true)
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
</script>
