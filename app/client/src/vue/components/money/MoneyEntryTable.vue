<!--
  Tabellenansicht des Buchungsverlaufs: Datum, Grund, Person, Budget, Status, Beleg, Betrag.
  Ganze Zeile klickbar (bzw. per Enter/Leertaste), öffnet die Buchung. Rechts ein
  Bearbeiten-Button, der nur beim Überfahren/Fokussieren der Zeile erscheint (auf
  Touch-Geräten immer) — nur, wo man die Buchung bearbeiten darf. Vorne die Kästchen der
  Mehrfachauswahl (im Kopf: alle geladenen), Zustand kommt über `selected` von außen.
-->
<template>
  <div class="money-entry-table">
    <table>
      <thead>
        <tr>
          <th scope="col" class="money-entry-table_col-select">
            <AppSelectCheckbox
              :checked="allSelected"
              :label="allSelected ? 'Auswahl aufheben' : 'Alle geladenen Buchungen auswählen'"
              @toggle="$emit('toggle-all')"
            />
          </th>
          <th scope="col">Datum</th>
          <th scope="col">Grund</th>
          <th scope="col">Person</th>
          <th scope="col">Budget</th>
          <th scope="col">Status</th>
          <th scope="col">Beleg</th>
          <th scope="col" class="money-entry-table_col-amount">Betrag</th>
          <th scope="col" class="money-entry-table_col-actions"><span class="money-entry-table_sr-only">Aktionen</span></th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="entry in entries"
          :key="entry.ID"
          class="money-entry-table_row"
          :class="{ 'money-entry-table_row--selected': selected.has(entry.ID) }"
          tabindex="0"
          @click="$emit('open', entry)"
          @keydown.enter.prevent="$emit('open', entry)"
          @keydown.space.prevent="$emit('open', entry)"
        >
          <td class="money-entry-table_col-select" @click.stop @keydown.stop>
            <AppSelectCheckbox
              :checked="selected.has(entry.ID)"
              :label="`${entry.ChangeReason} auswählen`"
              @toggle="$emit('toggle-select', entry, $event)"
            />
          </td>
          <td class="money-entry-table_date">{{ formatDate(entry.ChangeDate) }}</td>
          <td class="money-entry-table_reason">{{ entry.ChangeReason }}</td>
          <td>
            <span v-if="entry.User" class="money-entry-table_person">
              <AppAvatar :src="entry.User.Avatar" alt="" img-class="money-entry-table_avatar" />
              {{ entry.User.Name }}
            </span>
          </td>
          <td>
            <span v-if="entry.Budget" class="money-entry_budget-tag">{{ entry.Budget.Title }}</span>
          </td>
          <td class="money-entry-table_status">
            <span v-if="!entry.Approved" class="money-entry_pending-badge">Ausstehend</span>
            <MoneyEntrySettleBadge :entry="entry" :can-settle="canSettle" @settle="$emit('settle', $event)" />
          </td>
          <td>
            <a
              v-if="entry.ReceiptURL"
              class="money-entry_receipt"
              :href="entry.ReceiptURL"
              :data-type="entry.ReceiptURL.toLowerCase().endsWith('.pdf') ? 'external' : 'image'"
              @click.stop
            >Beleg</a>
          </td>
          <td
            class="money-entry-table_col-amount money-entry_amount"
            :class="entry.ChangeType === 'Deposit' ? 'money-entry_amount--deposit' : 'money-entry_amount--withdrawal'"
          >
            {{ entry.ChangeType === 'Deposit' ? '+' : '-' }} {{ formatCurrency(entry.ChangeAmount) }}
          </td>
          <td class="money-entry-table_col-actions">
            <AppIconButton
              v-if="canEdit(entry)"
              variant="primary"
              class="money-entry-table_edit"
              :aria-label="`${entry.ChangeReason} bearbeiten`"
              title="Bearbeiten"
              @click.stop="$emit('edit', entry)"
              @keydown.enter.stop
              @keydown.space.stop
            >
              <span class="icon-mask" :style="editIconStyle" />
            </AppIconButton>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppSelectCheckbox from '@components/ui/AppSelectCheckbox.vue'
import MoneyEntrySettleBadge from '@components/money/MoneyEntrySettleBadge.vue'
import actionEdit from '../../../../icons/actions/action_edit.svg'

const props = defineProps({
  entries: { type: Array, required: true },
  canSettle: { type: Boolean, default: false },
  // Darf alle Buchungen bearbeiten (Freigebende); sonst nur eigene, noch nicht freigegebene
  canManage: { type: Boolean, default: false },
  // IDs der ausgewählten Buchungen (Mehrfachauswahl)
  selected: { type: Set, default: () => new Set() },
})
defineEmits(['open', 'settle', 'edit', 'toggle-select', 'toggle-all'])

const allSelected = computed(() => props.entries.length > 0 && props.entries.every(e => props.selected.has(e.ID)))

const authStore = useAuthStore()
const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }

// Gleiche Regel wie auf der Detailseite (MoneyEntryDetail) und im Backend (entryUpdate)
function canEdit(entry) {
  if (props.canManage) return true
  return !entry.Approved && entry.User?.ID === authStore.user?.ID
}

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(dateStr))
}
</script>
