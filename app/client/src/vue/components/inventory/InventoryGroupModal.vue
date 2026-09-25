<template>
  <AppModal ref="modal" class="inventory-group-modal" :title="first ? `${activeItems.length}× ${first.Title}` : 'Objekte'" @close="close">
    <template v-if="first">
      <p class="inventory-group-modal_summary">
        <span v-if="first.Type">{{ first.Type.Title }} · </span>
        {{ availableCount }} von {{ activeItems.length }} einsatzbereit<template v-if="rentedCount"> · {{ rentedCount }} ausgeliehen</template><template v-if="retiredCount"> · {{ retiredCount }} ausgemustert</template>
      </p>

      <ul class="inventory-group-modal_list">
        <li v-for="item in items" :key="item.ID">
          <button type="button" class="inventory-group-modal_item" @click="emit('open-item', item)">
            <span class="inventory-group-modal_number">{{ item.InventoryNumber }}</span>
            <span class="inventory-group-modal_facts">{{ individualFacts(item) }}</span>
            <span
              v-for="badge in groupStatusBadges([item])"
              :key="badge.status"
              class="inventory-status-badge"
              :class="`inventory-status-badge--${badge.status}`"
            >{{ badge.label }}</span>
          </button>
        </li>
      </ul>

      <form v-if="canCreate" class="inventory-group-modal_add" @submit.prevent="duplicate">
        <label for="inventory-group-add-count">Weitere gleiche Objekte anlegen:</label>
        <input id="inventory-group-add-count" v-model.number="addCount" type="number" min="1" max="500" class="input">
        <AppButton type="submit" variant="secondary" size="small" :disabled="adding || !(addCount >= 1)">Hinzufügen</AppButton>
      </form>

      <div v-if="error" class="app-modal_error">{{ error }}</div>
    </template>

    <template v-if="first" #actions>
      <AppButton v-if="first.CanEdit" variant="secondary" @click="emit('edit-group', first)">Alle bearbeiten</AppButton>
      <AppButton v-if="canRent" variant="primary" @click="emit('rent', first, 1)">{{ first.IsMine ? 'Verleihen' : 'Ausleihen' }}</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useInventoryStore } from '@stores/inventory'
import { formatFieldValue, groupStatusBadges } from '@utils/inventory'
import AppButton from '@components/ui/AppButton.vue'
import AppModal from '@components/ui/AppModal.vue'

const emit = defineEmits(['open-item', 'edit-group', 'rent'])
const store = useInventoryStore()

const modal = ref(null)
const groupKey = ref(null)
const error = ref(null)
const adding = ref(false)
const addCount = ref(1)

// Immer aus dem Store, damit Änderungen an einzelnen Objekten sofort sichtbar sind
const items = computed(() => (groupKey.value ? store.itemsOfGroup(groupKey.value) : []))
const first = computed(() => items.value[0] ?? null)

// Ausgemusterte zählen nicht zur Gruppe, werden aber (am Ende) weiter aufgeführt
const activeItems = computed(() => items.value.filter(i => i.Status !== 'retired'))
const retiredCount = computed(() => items.value.length - activeItems.value.length)

const availableCount = computed(() => items.value.filter(i => i.Status === 'available').length)
const rentedCount = computed(() => items.value.filter(i => i.IsRentedOut).length)
// Felder, die je Objekt verschieden sind (z.B. Seriennummer, Prüfdatum) — leere als „–“,
// damit z.B. noch ungeprüfte Objekte auffallen
function individualFacts(item) {
  return (item.Fields || [])
    .filter(field => field.Individual)
    .map(field => `${field.Label}: ${formatFieldValue(field, item.Values?.[field.ID]) || '–'}`)
    .join(' · ')
}

const canCreate = computed(() => {
  if (!first.value) return false
  return first.value.IsPrivate ? first.value.IsMine : !!store.orgById(first.value.OrganizationID)?.CanCreate
})

const canRent = computed(() =>
  availableCount.value > 0
  && (
    first.value.CanRentPrivately
    || (first.value.RentableOrgIDs || []).some(id => first.value.IsMine || store.orgById(id)?.CanRequest)
  )
)

function open(key) {
  groupKey.value = key
  error.value = null
  addCount.value = 1
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function duplicate() {
  adding.value = true
  error.value = null
  try {
    const response = await store.duplicateItem(items.value[items.value.length - 1].ID, addCount.value)
    if (!response.success) error.value = response.error || 'Objekte konnten nicht angelegt werden.'
    else addCount.value = 1
  } finally {
    adding.value = false
  }
}

defineExpose({ open, close })
</script>
