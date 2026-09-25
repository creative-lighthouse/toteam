<template>
  <AppModal ref="modal" class="inventory-type-manager-modal" title="Arten verwalten" @close="close">

    <div class="modalform">
      <AppSegmentedToggle
        v-model="appliesTo"
        label="Arten für"
        :options="[{ value: 'item', label: 'Objekte' }, { value: 'vehicle', label: 'Fahrzeuge' }, { value: 'room', label: 'Räume' }]"
      />
      <div v-if="manageableOrgs.length > 1" class="field">
        <label>Organisation</label>
        <OrganizationPicker v-model="selectedOrgId" :orgs="manageableOrgs" />
      </div>
    </div>

    <p v-if="!orgTypes.length" class="inventory-type-manager-modal_empty">
      Für diese Organisation wurden noch keine {{ { item: 'Objekt-Arten', vehicle: 'Fahrzeug-Arten', room: 'Raum-Arten' }[appliesTo] }} angelegt.
    </p>

    <ul v-else class="inventory-type-manager-modal_list">
      <li v-for="type in orgTypes" :key="type.ID" class="inventory-type-manager-modal_item">
        <div class="inventory-type-manager-modal_item-info">
          <span class="inventory-type-manager-modal_item-title">{{ type.Title }}</span>
          <span class="inventory-type-manager-modal_item-meta">
            {{ usageLabel(type) }}<template v-if="fieldLabels(type)"> · {{ fieldLabels(type) }}</template>
          </span>
        </div>
        <AppIconButton variant="primary" aria-label="Bearbeiten" title="Bearbeiten" @click="typeModal?.openForEdit(type)">
          <span class="icon-mask" :style="editIconStyle" />
        </AppIconButton>
      </li>
    </ul>

    <template #actions>
      <AppButton variant="secondary" @click="close">Schließen</AppButton>
      <AppButton variant="primary" :disabled="!selectedOrgId" @click="typeModal?.open(selectedOrgId, appliesTo)">+ Neue Art</AppButton>
    </template>
  </AppModal>

  <InventoryTypeModal ref="typeModal" />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import AppSegmentedToggle from '@components/ui/AppSegmentedToggle.vue'
import InventoryTypeModal from '@components/inventory/InventoryTypeModal.vue'
import actionEdit from '../../../../icons/actions/action_edit.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }

const store = useInventoryStore()

const modal = ref(null)
const typeModal = ref(null)
const selectedOrgId = ref(null)
// Objekt- oder Raum-Arten
const appliesTo = ref('item')

const manageableOrgs = computed(() => store.organizations.filter(o => o.CanManageTypes))
const orgTypes = computed(() => (selectedOrgId.value ? store.typesForOrg(selectedOrgId.value, appliesTo.value) : []))

function usageLabel(type) {
  if (type.AppliesTo === 'room') {
    return `${type.ItemCount} Raum${type.ItemCount === 1 ? '' : 'e'}`
  }
  const count = store.itemCountForType(type.ID)
  if (type.AppliesTo === 'vehicle') return `${count} Fahrzeug${count === 1 ? '' : 'e'}`
  return `${count} Objekt${count === 1 ? '' : 'e'}`
}

function fieldLabels(type) {
  return (type.Fields || []).map(field => field.Label).join(', ')
}

/** @param {'item'|'vehicle'|'room'} kind welche Arten zuerst gezeigt werden (z.B. im Räume-Tab 'room') */
function open(kind = 'item') {
  appliesTo.value = kind
  const orgs = manageableOrgs.value
  // Aktiven Organisationsfilter der Inventarliste übernehmen, sofern dort verwaltbar
  selectedOrgId.value = orgs.some(o => o.ID === store.filterOrganization)
    ? store.filterOrganization
    : (orgs[0]?.ID ?? null)
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

defineExpose({ open, close })
</script>
