<template>
  <!-- Detailansicht eines Lagerpunkts: Pfad, Angaben, enthaltene Lagerpunkte und Inhalt.
       Über open(id) auch von außen aufrufbar (z.B. später nach einem NFC-Scan an einer Kiste). -->
  <AppModal ref="modal" class="inventory-storage-detail-modal" :title="location?.Title || 'Lagerpunkt'" @close="close">
    <template v-if="location">
      <div class="inventory-storage-detail-modal_head">
        <p class="inventory-storage-detail-modal_kicker">
          {{ [location.Type?.Title, ownerLabel].filter(Boolean).join(' · ') }}
        </p>
        <!-- Teilen-Link/QR-Code und NFC-Tag: führen auf die Seite des Lagerpunkts, auch ohne Anmeldung -->
        <div v-if="location.CanEdit" class="inventory-storage-detail-modal_toolbar">
          <AppIconButton variant="neutral" aria-label="Teilen" title="Öffentlichen Link teilen" @click="openShare">
            <span class="icon-mask" :style="shareIconStyle" />
          </AppIconButton>
          <AppIconButton
            v-if="nfcSupported"
            variant="neutral"
            aria-label="NFC-Tag beschreiben"
            title="NFC-Tag beschreiben"
            :disabled="nfcLoading"
            @click="openNfc"
          >
            <span class="icon-mask" :style="nfcIconStyle" />
          </AppIconButton>
        </div>
      </div>

      <!-- Wo der Lagerpunkt selbst lagert — übergeordnete anklickbar -->
      <nav v-if="ancestors.length" class="inventory-storage-detail-modal_path" aria-label="Lagert in">
        <span class="inventory-storage-detail-modal_path-label">Lagert in:</span>
        <template v-for="(ancestor, index) in ancestors" :key="ancestor.ID">
          <span v-if="index" aria-hidden="true">›</span>
          <button type="button" class="inventory-storage-detail-modal_path-link" @click="open(ancestor.ID)">{{ ancestor.Title }}</button>
        </template>
      </nav>

      <p v-if="location.Description" class="inventory-storage-detail-modal_description"><AppLinkifiedText :text="location.Description" /></p>

      <dl v-if="facts.length || location.SharedWith?.length" class="inventory-storage-detail-modal_facts">
        <div v-for="fact in facts" :key="fact.label">
          <dt>{{ fact.label }}</dt>
          <dd>{{ fact.value }}</dd>
        </div>
        <div v-if="location.SharedWith?.length">
          <dt>Sichtbar für</dt>
          <dd>{{ location.SharedWith.map(o => o.Title).join(', ') }}</dd>
        </div>
      </dl>

      <template v-if="children.length">
        <h3 class="hl3 inventory-storage-detail-modal_subtitle">Enthaltene Lagerpunkte</h3>
        <ul class="inventory-storage-detail-modal_children">
          <li v-for="child in children" :key="child.ID">
            <button type="button" class="inventory-storage-detail-modal_child" @click="open(child.ID)">
              <span class="inventory-storage-detail-modal_child-title">{{ child.Title }}</span>
              <span v-if="child.Type" class="inventory-storage-detail-modal_child-type">{{ child.Type.Title }}</span>
              <span v-if="store.totalContents(child.ID)" class="inventory-storage-node_count">{{ store.totalContents(child.ID) }}</span>
            </button>
          </li>
        </ul>
      </template>

      <h3 class="hl3 inventory-storage-detail-modal_subtitle">Inhalt</h3>
      <InventoryStorageContents
        v-if="location.Items?.length || location.Rooms?.length"
        :items="location.Items ?? []"
        :rooms="location.Rooms ?? []"
        :draggable="false"
        @open-item="item => { close(); emit('open-item', item) }"
        @open-room="room => { close(); emit('open-room', room) }"
      />
      <p v-else class="inventory-storage-detail-modal_empty">Hier lagern direkt keine Objekte, Fahrzeuge oder Räume.</p>
    </template>

    <p v-else class="inventory-storage-detail-modal_empty">Dieser Lagerpunkt wurde nicht gefunden.</p>

    <template v-if="location && (location.CanDelete || location.CanEdit || canCreate)" #actions>
      <AppButton v-if="location.CanDelete" variant="danger" @click="emit('delete', location)">Löschen</AppButton>
      <AppButton v-if="canCreate" variant="secondary" @click="emit('add-inside', location)">+ Lagerpunkt hierin</AppButton>
      <AppButton v-if="location.CanEdit" variant="primary" @click="emit('edit', location)">Bearbeiten</AppButton>
    </template>
  </AppModal>

  <ShareLinkModal ref="shareModal" />
  <WriteNFCModal ref="nfcModal" />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useStorageStore } from '@stores/storage'
import { formatFieldValue } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import ShareLinkModal from '@components/ui/ShareLinkModal.vue'
import WriteNFCModal, { isNfcWriteSupported } from '@components/ui/WriteNFCModal.vue'
import actionQrcode from '../../../../icons/actions/action_qrcode.svg'
import actionNfc from '../../../../icons/actions/action_nfc.svg'
import InventoryStorageContents from '@components/inventory/InventoryStorageContents.vue'

defineProps({
  // ob der Nutzer Lagerpunkte anlegen darf (für "+ Lagerpunkt hierin")
  canCreate: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'delete', 'add-inside', 'open-item', 'open-room'])

const store = useStorageStore()
const modal = ref(null)
const locationId = ref(null)

// Live aus dem Store — nach Bearbeiten/Verschieben sofort aktuell
const location = computed(() => (locationId.value ? store.byId.get(locationId.value) ?? null : null))

const ownerLabel = computed(() => {
  const l = location.value
  if (!l) return ''
  if (l.IsPrivate) return l.IsMine ? 'Privat (du)' : `Privat: ${l.OwnerName}`
  return l.OwnerName
})

// Übergeordnete Lagerpunkte von oben nach unten
const ancestors = computed(() => {
  const result = []
  const seen = new Set()
  let parent = location.value?.ParentID ? store.byId.get(location.value.ParentID) : null
  while (parent && !seen.has(parent.ID)) {
    seen.add(parent.ID)
    result.unshift(parent)
    parent = parent.ParentID ? store.byId.get(parent.ParentID) : null
  }
  return result
})

const children = computed(() => (location.value ? store.childrenOf(location.value.ID) : []))

const facts = computed(() =>
  (location.value?.Fields ?? [])
    .map(field => ({ label: field.Label, value: formatFieldValue(field, location.value.Values?.[field.ID]) }))
    .filter(fact => fact.value)
)

const shareModal = ref(null)
const nfcModal = ref(null)
const nfcLoading = ref(false)
const shareIconStyle = { maskImage: `url("${actionQrcode}")`, WebkitMaskImage: `url("${actionQrcode}")` }
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }
// Web NFC nur in Chrome auf Android — sonst bleibt der Button unsichtbar
const nfcSupported = isNfcWriteSupported()

function openShare() {
  const id = location.value.ID
  shareModal.value?.open({ title: location.value.Title, heading: 'Lagerpunkt teilen', request: revoke => store.shareLocation(id, revoke) })
}

// Auf den Tag kommt der öffentliche Teilen-Link (wird bei Bedarf angelegt)
async function openNfc() {
  nfcLoading.value = true
  try {
    const response = await store.shareLocation(location.value.ID)
    if (!response.success) {
      alert(response.error || 'Link konnte nicht erstellt werden.')
      return
    }
    nfcModal.value?.open({ title: location.value.Title, url: response.data.url })
  } finally {
    nfcLoading.value = false
  }
}

/** Lagerpunkt anzeigen; ist das Modal schon offen, wird nur der Inhalt gewechselt */
async function open(id) {
  locationId.value = id
  if (!store.locations.length) await store.fetchLocations()
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

defineExpose({ open, close })
</script>
