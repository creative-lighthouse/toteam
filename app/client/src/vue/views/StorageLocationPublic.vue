<template>
  <!-- Seite hinter dem Teilen-Link/NFC-Tag eines Lagerpunkts — auch ohne Anmeldung:
       Besitzer, Angaben, enthaltene Lagerpunkte und Inhalt. Ohne Zugriff sind nur
       Einträge mit eigenem Teilen-Link anklickbar; Mitglieder öffnen alles im Inventar. -->
  <div class="section section--InventoryItemPublicPage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox">
        <p>Lade Lagerpunkt…</p>
      </div>

      <div v-else-if="error" class="section_infobox error">
        <p>{{ error }}</p>
      </div>

      <!-- Gleiches Layout wie die geteilte Objektseite (pages/InventoryItemPublicPage.scss) -->
      <article v-else-if="location" class="inventory-public">
        <div class="inventory-public_head">
          <span v-if="typeTitle" class="inventory-public_type">{{ typeTitle }}</span>
        </div>

        <p v-if="isMember && location.Path?.length" class="storage-public_path">
          Lagert in: <strong>{{ location.Path.join(' › ') }}</strong>
        </p>

        <p v-if="location.Description" class="inventory-public_description"><AppLinkifiedText :text="location.Description" /></p>

        <dl class="inventory-public_facts">
          <div v-if="ownerLabel">
            <dt>Besitzer</dt>
            <dd>{{ ownerLabel }}</dd>
          </div>
          <div v-for="fact in facts" :key="fact.label">
            <dt>{{ fact.label }}</dt>
            <dd>{{ fact.value }}</dd>
          </div>
        </dl>

        <template v-if="location.Children?.length">
          <h2 class="hl3 inventory-public_subtitle">Enthaltene Lagerpunkte</h2>
          <ul class="storage-public_children">
            <li v-for="child in location.Children" :key="child.ID">
              <component
                :is="isMember || child.PublicPath ? 'button' : 'div'"
                :type="isMember || child.PublicPath ? 'button' : undefined"
                class="storage-public_child"
                :class="{ 'storage-public_child--static': !isMember && !child.PublicPath }"
                @click="openChild(child)"
              >
                <span class="storage-public_child-title">{{ child.Title }}</span>
                <span v-if="child.Type" class="storage-public_child-type">{{ child.Type }}</span>
                <span v-if="child.Count" class="inventory-storage-node_count">{{ child.Count }}</span>
              </component>
            </li>
          </ul>
        </template>

        <h2 class="hl3 inventory-public_subtitle">Inhalt</h2>
        <InventoryStorageContents
          v-if="location.Items?.length || location.Rooms?.length"
          :items="location.Items ?? []"
          :rooms="location.Rooms ?? []"
          :draggable="false"
          :is-clickable="entry => isMember || !!entry.PublicPath"
          @open-item="openItem"
          @open-room="openRoom"
        />
        <p v-else class="storage-public_empty">Hier lagern direkt keine Objekte, Fahrzeuge oder Räume.</p>

        <div class="inventory-public_actions">
          <template v-if="isMember">
            <AppButton variant="primary" @click="openInInventory({ location: location.ID })">Im Lager öffnen</AppButton>
          </template>
          <template v-else-if="!authStore.isAuthenticated">
            <p class="inventory-public_login-hint">Mitglieder können sich anmelden, um den Lagerpunkt und seinen Inhalt im Inventar zu öffnen.</p>
            <AppButton variant="primary" @click="router.push({ name: 'Login', query: { redirect: route.fullPath } })">Anmelden</AppButton>
          </template>
          <p v-else class="inventory-public_login-hint">Dieser Lagerpunkt gehört nicht zu deinen Organisationen.</p>
        </div>
      </article>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useStorageStore } from '@stores/storage'
import { usePageHeaderStore } from '@stores/pageHeader'
import { formatFieldValue } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import InventoryStorageContents from '@components/inventory/InventoryStorageContents.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const store = useStorageStore()
const pageHeader = usePageHeaderStore()

const location = ref(null)
const isMember = ref(false)
const loading = ref(true)
const error = ref(null)

// Öffentliche Antwort: Art/Besitzer als Text; Mitglieder-Antwort: volle Daten
const typeTitle = computed(() => (isMember.value ? location.value?.Type?.Title : location.value?.Type))
const ownerLabel = computed(() => {
  // Öffentliche Antwort: "Organisation" bzw. "Privat: Name"
  if (!isMember.value) return location.value?.Owner
  const l = location.value
  if (l?.IsPrivate) return l.IsMine ? 'Du (privat)' : `${l.OwnerName} (privat)`
  return l?.OwnerName
})

// Mitglieder sehen alle ausgefüllten Felder, sonst liefert der Server nur die öffentlichen (mit Wert)
const facts = computed(() =>
  (location.value?.Fields || [])
    .map(field => ({
      label: field.Label,
      value: formatFieldValue(field, isMember.value ? location.value.Values?.[field.ID] : field.Value),
    }))
    .filter(fact => fact.value !== '')
)

/** Ins Inventar wechseln und dort das passende Detail öffnen (siehe views/Inventory.vue) */
function openInInventory({ tab = 'storage', ...query }) {
  router.push({ name: 'Inventory', query: { ...(tab === 'items' ? {} : { tab }), ...query } })
}

// Mitglieder: ins Inventar; sonst die eigene öffentliche Seite (falls geteilt)
function openItem(item) {
  if (isMember.value) openInInventory({ item: item.ID, tab: item.Kind === 'vehicle' ? 'vehicles' : undefined })
  else if (item.PublicPath) router.push(item.PublicPath)
}

function openRoom(room) {
  if (isMember.value) openInInventory({ room: room.ID, tab: 'rooms' })
  else if (room.PublicPath) router.push(room.PublicPath)
}

function openChild(child) {
  if (isMember.value) openInInventory({ location: child.ID })
  else if (child.PublicPath) router.push(child.PublicPath)
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await store.fetchPublicLocation(route.params.token)
    if (!response?.location) {
      error.value = response?.error || 'Dieser Link ist ungültig oder wurde deaktiviert.'
      return
    }
    location.value = response.location
    isMember.value = !!response.isMember
    pageHeader.setHeader(location.value.Title, isMember.value ? 'Lagerpunkt' : 'Geteilter Lagerpunkt')
  } catch (err) {
    error.value = err.message || 'Lagerpunkt konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
