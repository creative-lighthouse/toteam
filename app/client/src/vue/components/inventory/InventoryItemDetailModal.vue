<template>
  <AppModal ref="modal" class="inventory-item-detail-modal" :title="item?.Title || 'Objekt'" @close="close">
    <div v-if="loading" class="inventory-item-detail-modal_loading">Lade Objekt…</div>

    <template v-else-if="item">
      <div class="inventory-item-detail-modal_head">
        <span class="inventory-item-detail-modal_number">{{ item.InventoryNumber }}</span>
        <span v-if="item.Type" class="inventory-item-detail-modal_type">{{ item.Type.Title }}</span>
        <span v-if="item.IsRentedOut" class="inventory-status-badge inventory-status-badge--rented_out">Ausgeliehen</span>
        <span class="inventory-status-badge" :class="`inventory-status-badge--${item.Status}`">{{ item.StatusLabel }}</span>

        <!-- Im Inhalt statt in der Aktionsleiste, damit diese auf dem Handy nicht überläuft -->
        <div class="inventory-item-detail-modal_toolbar">
          <AppIconButton variant="neutral" aria-label="Verlauf" title="Verlauf" @click="historyModal?.open()">
            <span class="icon-mask" :style="historyIconStyle" />
          </AppIconButton>
          <AppIconButton v-if="item.CanEdit" variant="neutral" aria-label="Teilen" title="Öffentlichen Link teilen" @click="openShare">
            <span class="icon-mask" :style="shareIconStyle" />
          </AppIconButton>
          <AppIconButton
            v-if="item.CanEdit && nfcSupported"
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

      <ul v-if="item.Images?.length" class="inventory-item-detail-modal_images">
        <li v-for="(img, index) in item.Images" :key="img.ID">
          <a :href="img.URL" target="_blank" rel="noopener" @click.prevent="lightbox?.open(item.Images, index)">
            <img :src="img.Thumbnail" :alt="img.Name" loading="lazy">
          </a>
        </li>
      </ul>

      <p v-if="item.GroupSize > 1" class="inventory-item-detail-modal_group">
        Eines von {{ item.GroupSize }} gleichen Objekten.
        <button type="button" class="inventory-item-detail-modal_group-link" @click="showGroup">Alle anzeigen</button>
      </p>

      <p v-if="item.Description" class="inventory-item-detail-modal_description"><AppLinkifiedText :text="item.Description" /></p>

      <dl class="inventory-item-detail-modal_facts">
        <div>
          <dt>Besitzer</dt>
          <dd class="inventory-item-detail-modal_owner">
            <AppAvatar
              v-if="item.Owner.Member"
              :src="item.Owner.Member.Avatar"
              :alt="item.Owner.Name"
              img-class="inventory-item-detail-modal_owner-avatar"
            />
            <AppOrgLogo v-else-if="item.Organization" :src="item.Organization.LogoURL" :alt="item.Organization.Title" :size="20" />
            {{ item.IsMine ? 'Du (privat)' : item.Owner.Name }}<template v-if="item.IsPrivate && !item.IsMine"> (privat)</template>
          </dd>
        </div>
        <div v-if="item.IsPrivate || item.SharedWith?.length || item.PrivateRentable">
          <dt>Sichtbar/Ausleihbar für</dt>
          <dd>{{ shareLabel }}</dd>
        </div>
        <div v-for="fact in facts" :key="fact.label">
          <dt>{{ fact.label }}</dt>
          <dd>{{ fact.value }}</dd>
        </div>
      </dl>

      <template v-if="item.Documents?.length">
        <h3 class="hl3 inventory-item-detail-modal_subtitle">Dokumente</h3>
        <ul class="inventory-item-detail-modal_documents">
          <li v-for="doc in item.Documents" :key="doc.ID">
            <a :href="doc.URL" target="_blank" rel="noopener">{{ doc.Name }}</a>
            <span v-if="doc.Size" class="inventory-item-detail-modal_doc-size">{{ formatFileSize(doc.Size) }}</span>
          </li>
        </ul>
      </template>

      <template v-if="item.Damages?.length">
        <h3 class="hl3 inventory-item-detail-modal_subtitle">
          Schäden ({{ item.Damages.filter(d => d.IsOpen).length }} offen<template v-if="item.Damages.some(d => !d.IsOpen)">, {{ item.Damages.filter(d => !d.IsOpen).length }} behoben</template>)
        </h3>
        <InventoryDamageList :damages="item.Damages" show-rental @changed="reloadItem" />
      </template>

      <h3 class="hl3 inventory-item-detail-modal_subtitle">Ausleihen</h3>
      <ul v-if="item.Rentals?.length" class="inventory-item-detail-modal_rentals">
        <li v-for="rental in item.Rentals" :key="rental.ID">
          <button type="button" class="inventory-item-detail-modal_rental" @click="openRental(rental)">
            <span class="inventory-rental-badge" :class="`inventory-rental-badge--${rental.Status}`">{{ rental.StatusLabel }}</span>
            <span class="inventory-item-detail-modal_rental-dates">{{ formatDateRange(rental.StartDate, rental.EndDate) }}</span>
            <span v-if="rental.Member" class="inventory-item-detail-modal_rental-member">{{ rental.Member.Name }}</span>
            <!-- Fahrzeuge: Kilometerstand bei Rückgabe und gefahrene Strecke -->
            <span v-if="rental.EndMileage || rental.StartMileage" class="inventory-item-detail-modal_rental-km">
              <template v-if="rental.EndMileage">Rückgabe bei {{ formatKm(rental.EndMileage) }}</template>
              <template v-else>Übergabe bei {{ formatKm(rental.StartMileage) }}</template>
              <strong v-if="rental.StartMileage && rental.EndMileage">{{ formatKm(rental.EndMileage - rental.StartMileage) }} gefahren</strong>
            </span>
          </button>
        </li>
      </ul>
      <p v-else class="inventory-item-detail-modal_empty">Noch keine Ausleihen.</p>
    </template>

    <template v-if="item && (item.CanDelete || item.CanEdit || canRent)" #actions>
      <AppIconButton
        v-if="item.CanDelete"
        variant="danger"
        class="inventory-item-detail-modal_delete"
        aria-label="Objekt löschen"
        title="Löschen"
        @click="remove"
      >
        <span class="icon-mask" :style="trashIconStyle" />
      </AppIconButton>
      <AppButton v-if="item.CanEdit" variant="secondary" @click="emit('edit', item)">Bearbeiten</AppButton>
      <AppButton v-if="canRent" variant="primary" @click="emit('rent', item)">{{ item.IsMine ? 'Verleihen' : 'Ausleihen' }}</AppButton>
    </template>
  </AppModal>

  <ShareLinkModal ref="shareModal" />
  <AppLightbox ref="lightbox" />
  <WriteNFCModal ref="nfcModal" />

  <HistoryModal
    v-if="item"
    ref="historyModal"
    :endpoint="`/inventory/itemHistory/${item.ID}`"
    created-label="hat das Objekt angelegt"
  />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useInventoryStore } from '@stores/inventory'
import { formatDateRange, formatFieldValue, formatFileSize } from '@utils/inventory'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppLightbox from '@components/ui/AppLightbox.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppModal from '@components/ui/AppModal.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import HistoryModal from '@components/history/HistoryModal.vue'
import actionHistory from '../../../../icons/actions/action_history.svg'
import actionQrcode from '../../../../icons/actions/action_qrcode.svg'
import actionTrash from '../../../../icons/actions/action_trash.svg'
import actionNfc from '../../../../icons/actions/action_nfc.svg'
import WriteNFCModal, { isNfcWriteSupported } from '@components/ui/WriteNFCModal.vue'
import ShareLinkModal from '@components/ui/ShareLinkModal.vue'
import InventoryDamageList from '@components/inventory/InventoryDamageList.vue'

const emit = defineEmits(['edit', 'deleted', 'rent', 'show-group'])
const router = useRouter()
const store = useInventoryStore()

const historyIconStyle = { maskImage: `url("${actionHistory}")`, WebkitMaskImage: `url("${actionHistory}")` }
const shareIconStyle = { maskImage: `url("${actionQrcode}")`, WebkitMaskImage: `url("${actionQrcode}")` }
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }
const nfcIconStyle = { maskImage: `url("${actionNfc}")`, WebkitMaskImage: `url("${actionNfc}")` }

// Web NFC nur in Chrome auf Android — auf iOS/Desktop bleibt der Button unsichtbar
const nfcSupported = isNfcWriteSupported()

const modal = ref(null)
const lightbox = ref(null)
const historyModal = ref(null)
const shareModal = ref(null)
const nfcModal = ref(null)
const nfcLoading = ref(false)

function openShare() {
  const id = item.value.ID
  shareModal.value?.open({ title: item.value.Title, heading: 'Objekt teilen', request: revoke => store.shareItem(id, revoke) })
}

// Auf den Tag kommt der öffentliche Teilen-Link (wird bei Bedarf angelegt)
async function openNfc() {
  nfcLoading.value = true
  try {
    const response = await store.shareItem(item.value.ID)
    if (!response.success) {
      alert(response.error || 'Link konnte nicht erstellt werden.')
      return
    }
    item.value = { ...item.value, ShareURL: response.data.url }
    nfcModal.value?.open({ title: item.value.Title, url: response.data.url })
  } finally {
    nfcLoading.value = false
  }
}
const item = ref(null)
const loading = ref(false)

// Nur die Felder, die die Art vorsieht und die auch ausgefüllt sind
const facts = computed(() => {
  if (!item.value) return []
  const mileage = item.value.Kind === 'vehicle' && item.value.Mileage
    ? [{ label: 'Kilometerstand', value: `${new Intl.NumberFormat('de-DE').format(item.value.Mileage)} km` }]
    : []
  return [
    ...mileage,
    ...(item.value.Fields || [])
      .map(field => ({ label: field.Label, value: formatFieldValue(field, item.value.Values?.[field.ID]) }))
      .filter(fact => fact.value !== ''),
  ]
})

const shareLabel = computed(() => {
  const parts = (item.value?.SharedWith || []).map(o => o.Title)
  if (item.value?.PrivateRentable) parts.push('Privat')
  return parts.length ? parts.join(', ') : 'Nur dich'
})

// Ausleihbar im Kontext der Besitzer-Organisation bzw. einer Freigabe oder für
// private Zwecke; eigenes privates Equipment darf man ohne Antrags-Berechtigung verleihen
const canRent = computed(() =>
  item.value?.Status === 'available'
  && (
    item.value.CanRentPrivately
    || (item.value.RentableOrgIDs || []).some(id => item.value.IsMine || store.orgById(id)?.CanRequest)
  )
)

async function open(itemId) {
  item.value = store.items.find(i => i.ID === itemId) ?? null
  loading.value = !item.value
  modal.value?.open()
  const detail = await store.fetchItemDetail(itemId)
  if (detail) item.value = detail
  loading.value = false
}

function close() {
  modal.value?.close()
}

const kmFormat = new Intl.NumberFormat('de-DE')
function formatKm(km) {
  return `${kmFormat.format(km)} km`
}

// Nach "behoben": Detail und Liste neu laden (der Zustand kann sich geändert haben)
async function reloadItem() {
  const detail = await store.fetchItemDetail(item.value.ID)
  if (detail) item.value = detail
  store.fetchItems(true)
}

function showGroup() {
  close()
  emit('show-group', item.value)
}

function openRental(rental) {
  close()
  router.push({ name: 'InventoryRentals', params: { id: rental.ID } })
}

async function remove() {
  if (!item.value) return
  if (!confirm(`„${item.value.Title}“ wirklich aus dem Inventar löschen?`)) return
  const response = await store.deleteItem(item.value.ID)
  if (response.success) {
    emit('deleted', item.value.ID)
    close()
  } else {
    alert(response.error || 'Fehler beim Löschen des Objekts.')
  }
}

defineExpose({ open, close })
</script>
