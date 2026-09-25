<template>
  <div class="section section--InventoryItemPublicPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Objekt…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
      </div>

      <article v-else-if="item" class="inventory-public">
        <ul v-if="item.Images?.length" class="inventory-public_images">
          <li v-for="(img, index) in item.Images" :key="img.ID">
            <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(item.Images, index)">
              <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
            </a>
          </li>
        </ul>

        <div class="inventory-public_head">
          <span class="inventory-public_number">{{ item.InventoryNumber }}</span>
          <span v-if="typeTitle" class="inventory-public_type">{{ typeTitle }}</span>
          <span v-if="isMember && item.IsRentedOut" class="inventory-status-badge inventory-status-badge--rented_out">Ausgeliehen</span>
          <span class="inventory-status-badge" :class="`inventory-status-badge--${item.Status}`">{{ item.StatusLabel }}</span>
        </div>

        <p v-if="item.Description" class="inventory-public_description"><AppLinkifiedText :text="item.Description" /></p>

        <dl class="inventory-public_facts">
          <div>
            <dt>Besitzer</dt>
            <dd>{{ ownerLabel }}</dd>
          </div>
          <div v-for="fact in facts" :key="fact.label">
            <dt>{{ fact.label }}</dt>
            <dd>{{ fact.value }}</dd>
          </div>
        </dl>

        <template v-if="isMember && item.Documents?.length">
          <h2 class="hl3 inventory-public_subtitle">Dokumente</h2>
          <ul class="inventory-public_documents">
            <li v-for="doc in item.Documents" :key="doc.ID">
              <a :href="doc.URL" target="_blank" rel="noopener">{{ doc.Name }}</a>
              <span v-if="doc.Size" class="inventory-public_doc-size">{{ formatFileSize(doc.Size) }}</span>
            </li>
          </ul>
        </template>

        <div class="inventory-public_actions">
          <template v-if="isMember">
            <AppButton variant="secondary" @click="router.push({ name: 'Inventory' })">Zum Inventar</AppButton>
            <AppButton v-if="item.CanEdit" variant="secondary" @click="edit">Bearbeiten</AppButton>
            <AppButton v-if="canRent" variant="primary" @click="rent">{{ item.IsMine ? 'Verleihen' : 'Ausleihen' }}</AppButton>
          </template>
          <template v-else-if="!authStore.isAuthenticated">
            <p class="inventory-public_login-hint">Mitglieder sehen nach der Anmeldung alle Details und können das Objekt ausleihen.</p>
            <AppButton variant="primary" @click="router.push({ name: 'Login', query: { redirect: route.fullPath } })">Anmelden</AppButton>
          </template>
          <p v-else class="inventory-public_login-hint">Du hast keinen Zugriff auf weitere Details dieses Objekts.</p>
        </div>
      </article>

    </div>

    <AppLightbox ref="lightbox" />

    <template v-if="isMember">
      <InventoryItemFormModal ref="formModal" @saved="load" />
      <InventoryRentalRequestModal ref="rentalModal" @saved="rental => router.push({ name: 'InventoryRentals', params: { id: rental.ID } })" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useInventoryStore } from '@stores/inventory'
import { usePageHeaderStore } from '@stores/pageHeader'
import { formatFieldValue, formatFileSize } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import InventoryItemFormModal from '@components/inventory/InventoryItemFormModal.vue'
import InventoryRentalRequestModal from '@components/inventory/InventoryRentalRequestModal.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const store = useInventoryStore()
const pageHeader = usePageHeaderStore()

const item = ref(null)
const isMember = ref(false)
const loading = ref(true)
const error = ref(null)
const formModal = ref(null)
const lightbox = ref(null)
const rentalModal = ref(null)

// Öffentliche Antwort: Type als Titel, Owner als Text; Mitglieder-Antwort: volle Objektdaten
const typeTitle = computed(() => (isMember.value ? item.value?.Type?.Title : item.value?.Type))
const ownerLabel = computed(() => {
  if (!isMember.value) return item.value?.Owner
  if (item.value?.IsPrivate) return item.value.IsMine ? 'Du (privat)' : `${item.value.Owner?.Name} (privat)`
  return item.value?.Owner?.Name
})

// Mitglieder sehen alle ausgefüllten Felder, sonst liefert der Server nur die öffentlichen (mit Wert)
const facts = computed(() => {
  if (!item.value) return []
  const mileage = isMember.value && item.value.Kind === 'vehicle' && item.value.Mileage
    ? [{ label: 'Kilometerstand', value: `${new Intl.NumberFormat('de-DE').format(item.value.Mileage)} km` }]
    : []
  return [...mileage, ...(item.value.Fields || [])
    .map(field => ({
      label: field.Label,
      value: formatFieldValue(field, isMember.value ? item.value.Values?.[field.ID] : field.Value),
    }))
    .filter(fact => fact.value !== '')]
})

const canRent = computed(() =>
  item.value?.Status === 'available'
  && (
    item.value.CanRentPrivately
    || (item.value.RentableOrgIDs || []).some(id => item.value.IsMine || store.orgById(id)?.CanRequest)
  )
)

async function load() {
  loading.value = !item.value
  error.value = null
  try {
    const response = await store.fetchPublicItem(route.params.token)
    if (!response?.item) {
      error.value = response?.error || 'Dieser Link ist ungültig oder wurde deaktiviert.'
      return
    }
    item.value = response.item
    isMember.value = !!response.isMember
    pageHeader.setHeader(item.value.Title, isMember.value ? 'Inventar' : 'Geteiltes Objekt')
    // Arten und Organisationen für Bearbeiten/Ausleihen
    if (isMember.value && !store.organizations.length) await store.fetchItems()
  } catch (err) {
    error.value = err.message || 'Objekt konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

function edit() {
  formModal.value?.openForEdit(item.value)
}

function rent() {
  rentalModal.value?.open({ organizationId: store.rentalContextFor(item.value), groups: { [item.value.GroupKey]: 1 } })
}

onMounted(load)
</script>
