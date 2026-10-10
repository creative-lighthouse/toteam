<template>
  <!-- Ein Lagerpunkt im Lager-Baum: Kopfzeile zum Auf-/Zuklappen, darunter die
       enthaltenen Lagerpunkte (rekursiv) sowie Objekte, Fahrzeuge und Räume -->
  <li
    v-if="visible"
    class="inventory-storage-node"
    :class="{ 'inventory-storage-node--open': isOpen, 'inventory-storage-node--dragging': drag.dragging.value?.kind === 'location' && drag.dragging.value.entity.ID === location.ID }"
  >
    <div
      class="inventory-storage-node_header"
      :class="dropClass"
      :data-drop-zone="`loc:${location.ID}`"
      @pointerdown="location.CanEdit && drag.start($event, { kind: 'location', entity: location })"
    >
      <span
        v-if="location.CanEdit && drag.enabled.value"
        class="inventory-storage-node_handle"
        data-drag-handle
        title="Ziehen zum Verschieben"
        aria-hidden="true"
      />
      <button
        type="button"
        class="inventory-storage-node_toggle"
        :aria-expanded="isOpen"
        @click="emit('toggle', location.ID)"
      >
        <span class="inventory-storage-node_chevron" :class="{ 'inventory-storage-node_chevron--empty': isEmpty }" aria-hidden="true" />
        <span class="inventory-storage-node_info">
          <span class="inventory-storage-node_title">{{ location.Title }}</span>
          <span class="inventory-storage-node_meta">
            <span v-for="(part, i) in metaParts" :key="i">{{ i ? '· ' : '' }}{{ part }}</span>
          </span>
        </span>
        <span v-if="total" class="inventory-storage-node_count" :title="`${total} Objekte/Räume (inkl. enthaltener Lagerpunkte)`">{{ total }}</span>
      </button>

      <span class="inventory-storage-node_actions">
        <AppIconButton variant="neutral" :aria-label="`„${location.Title}“ ansehen`" title="Ansehen" @click="emit('view', location)">
          <span class="icon-mask" :style="viewIconStyle" />
        </AppIconButton>
        <AppIconButton v-if="location.CanEdit" variant="primary" :aria-label="`„${location.Title}“ bearbeiten`" title="Bearbeiten" @click="emit('edit', location)">
          <span class="icon-mask" :style="editIconStyle" />
        </AppIconButton>
        <AppIconButton v-if="location.CanDelete" variant="danger" :aria-label="`„${location.Title}“ löschen`" title="Löschen" @click="emit('delete', location)">
          <span class="icon-mask" :style="trashIconStyle" />
        </AppIconButton>
      </span>
    </div>

    <div v-if="isOpen" class="inventory-storage-node_body">
      <p v-if="location.Description" class="inventory-storage-node_description">{{ location.Description }}</p>
      <p v-if="facts.length" class="inventory-storage-node_facts">
        <span v-for="fact in facts" :key="fact.label"><strong>{{ fact.label }}:</strong> {{ fact.value }}</span>
      </p>

      <ul v-if="children.length" class="inventory-storage-tree">
        <InventoryStorageNode
          v-for="child in children"
          :key="child.ID"
          :location="child"
          :expanded-ids="expandedIds"
          @toggle="id => emit('toggle', id)"
          @view="l => emit('view', l)"
          @edit="l => emit('edit', l)"
          @delete="l => emit('delete', l)"
          @open-item="i => emit('open-item', i)"
          @open-room="r => emit('open-room', r)"
        />
      </ul>

      <InventoryStorageContents
        :items="location.Items ?? []"
        :rooms="location.Rooms ?? []"
        @open-item="i => emit('open-item', i)"
        @open-room="r => emit('open-room', r)"
      />

      <p v-if="isEmpty" class="inventory-storage-node_empty">Hier lagert noch nichts.</p>
    </div>
  </li>
</template>

<script setup>
import { computed, inject } from 'vue'
import { useStorageStore } from '@stores/storage'
import { formatFieldValue } from '@utils/inventory'
import AppIconButton from '@components/ui/AppIconButton.vue'
import InventoryStorageContents from '@components/inventory/InventoryStorageContents.vue'
import actionEye from '../../../../icons/actions/action_eye.svg'
import actionEdit from '../../../../icons/actions/action_edit.svg'
import actionTrash from '../../../../icons/actions/action_trash.svg'

const props = defineProps({
  location: { type: Object, required: true },
  // aufgeklappte Lagerpunkte (IDs)
  expandedIds: { type: Set, required: true },
})

const emit = defineEmits(['toggle', 'view', 'edit', 'delete', 'open-item', 'open-room'])

const store = useStorageStore()

// Drag & Drop aus dem InventoryStorageTab: start, dragging, dropTarget
const drag = inject('storageDrag')
const dropClass = computed(() => {
  const target = drag.dropTarget.value
  return target?.targetId === props.location.ID ? `inventory-storage-node_header--drop-${target.position}` : null
})

const maskStyle = icon => ({ maskImage: `url("${icon}")`, WebkitMaskImage: `url("${icon}")` })
const viewIconStyle = maskStyle(actionEye)
const editIconStyle = maskStyle(actionEdit)
const trashIconStyle = maskStyle(actionTrash)

// Bei aktiver Suche nur Treffer (und ihre übergeordneten Lagerpunkte) zeigen
const visible = computed(() => !store.matchingIds || store.matchingIds.has(props.location.ID))
const isOpen = computed(() => props.expandedIds.has(props.location.ID))

const children = computed(() => store.childrenOf(props.location.ID))
const total = computed(() => store.totalContents(props.location.ID))

const isEmpty = computed(() => !children.value.length && !props.location.Items?.length && !props.location.Rooms?.length)

// Zusatzfelder der Art mit Wert (z.B. Adresse, Maße) — "In Liste" in der Kopfzeile, die übrigen aufgeklappt
const fieldFacts = computed(() =>
  (props.location.Fields ?? [])
    .map(field => ({ field, label: field.Label, value: formatFieldValue(field, props.location.Values?.[field.ID]) }))
    .filter(fact => fact.value)
)
const facts = computed(() => fieldFacts.value.filter(fact => !fact.field.ShowInList))

const metaParts = computed(() => [
  props.location.Type?.Title,
  ...fieldFacts.value.filter(fact => fact.field.ShowInList).map(fact => fact.value),
  props.location.IsPrivate ? (props.location.IsMine ? 'Privat (du)' : `Privat: ${props.location.OwnerName}`) : props.location.OwnerName,
].filter(Boolean))

</script>
