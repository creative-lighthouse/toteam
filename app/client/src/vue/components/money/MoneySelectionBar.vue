<!--
  Leiste der Mehrfachauswahl im Buchungsverlauf (Liste/Tabelle), unten am Bildschirm,
  sobald Buchungen ausgewählt sind: Anzahl, Summe der Ausgaben (und Einnahmen, falls
  welche dabei sind), alle geladenen auswählen, offene begleichen, löschen, Auswahl aufheben.
-->
<template>
  <Transition name="money-selection-bar">
    <div v-if="count" class="money-selection-bar" role="region" aria-label="Auswahl">
      <AppIconButton variant="ghost" aria-label="Auswahl aufheben" title="Auswahl aufheben (Esc)" @click="emit('clear')">✕</AppIconButton>

      <div class="money-selection-bar_summary" role="status">
        <span class="money-selection-bar_count">{{ count }} ausgewählt</span>
        <span class="money-selection-bar_sum">
          Ausgaben <strong>{{ formatCurrency(expenses) }}</strong>
          <template v-if="income > 0"> · Einnahmen <strong>{{ formatCurrency(income) }}</strong></template>
        </span>
      </div>

      <button
        v-if="selectableCount > count"
        type="button"
        class="money-selection-bar_all"
        @click="emit('select-all')"
      >Alle {{ selectableCount }} auswählen</button>

      <div v-if="canSettle || deletableCount" class="money-selection-bar_actions">
        <AppButton
          v-if="canSettle"
          variant="primary"
          size="small"
          :disabled="openAmount <= 0"
          :title="openAmount > 0 ? `${formatCurrency(openAmount)} offen` : 'Keine offenen Beträge in der Auswahl'"
          @click="emit('settle')"
        >Begleichen</AppButton>
        <AppIconButton
          v-if="deletableCount"
          variant="danger"
          :aria-label="deletableCount === 1 ? 'Ausgewählte Buchung löschen' : `${deletableCount} ausgewählte Buchungen löschen`"
          title="Löschen"
          :disabled="busy"
          @click="emit('remove')"
        >
          <span class="icon-mask" :style="trashIconStyle" aria-hidden="true" />
        </AppIconButton>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import actionTrash from '../../../../icons/actions/action_trash.svg'

defineProps({
  count: { type: Number, required: true },
  // Wie viele Buchungen geladen (und damit auswählbar) sind
  selectableCount: { type: Number, default: 0 },
  expenses: { type: Number, default: 0 },
  income: { type: Number, default: 0 },
  // Noch auszuzahlender Rest der ausgewählten Ausgaben
  openAmount: { type: Number, default: 0 },
  canSettle: { type: Boolean, default: false },
  // Wie viele der Ausgewählten man löschen darf (0 = kein Löschen-Button)
  deletableCount: { type: Number, default: 0 },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['select-all', 'settle', 'remove', 'clear'])

const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

function formatCurrency(value) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value || 0)
}
</script>
