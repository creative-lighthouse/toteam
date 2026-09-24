<template>
  <div class="history-entry">
    <AppAvatar :src="entry.Member?.Avatar" :alt="memberName" img-class="history-entry_avatar" />

    <div class="history-entry_body">
      <div class="history-entry_head">
        <span class="history-entry_name">{{ memberName }}</span>
        <span class="history-entry_action">{{ entry.Type === 'created' ? createdLabel : 'hat Änderungen gemacht' }}</span>
        <time class="history-entry_date" :datetime="entry.Date">{{ formatDateTime(entry.Date) }}</time>
      </div>

      <ul v-if="entry.Changes?.length" class="history-entry_changes">
        <li
          v-for="change in entry.Changes"
          :key="change.Field"
          class="history-entry_change"
          :class="{ 'history-entry_change--long': change.Format === 'longtext' }"
        >
          <span class="history-entry_label">{{ change.Label }}:</span>

          <!-- Listen (z. B. Unterstützer, Räume, Unteraufgaben) -->
          <span v-if="change.Kind === 'set'" class="history-entry_set">
            <span v-for="item in change.Added" :key="`a${item.ID}`" class="history-entry_chip history-entry_chip--added">+ {{ item.Label }}</span>
            <span v-for="item in change.Removed" :key="`r${item.ID}`" class="history-entry_chip history-entry_chip--removed">− {{ item.Label }}</span>
          </span>

          <!-- Lange Texte: Vorher/Nachher untereinander -->
          <div v-else-if="change.Format === 'longtext'" class="history-entry_long">
            <div class="history-entry_long-value history-entry_long-value--old">
              <span class="history-entry_long-caption">Vorher</span>
              <p :class="{ 'history-entry_empty': !change.Old }">{{ change.Old || 'leer' }}</p>
            </div>
            <div class="history-entry_long-value history-entry_long-value--new">
              <span class="history-entry_long-caption">Nachher</span>
              <p :class="{ 'history-entry_empty': !change.New }">{{ change.New || 'leer' }}</p>
            </div>
          </div>

          <!-- Einfache Werte: Vorher → Nachher (bei "erstellt"-Einträgen nur der Anfangswert) -->
          <span v-else class="history-entry_value">
            <template v-if="entry.Type !== 'created'">
              <span class="history-entry_old" :class="{ 'history-entry_empty': change.Old == null }">{{ formatValue(change, change.Old) }}</span>
              <span class="history-entry_arrow" aria-label="geändert zu">→</span>
            </template>
            <span class="history-entry_new" :class="{ 'history-entry_empty': change.New == null }">{{ formatValue(change, change.New) }}</span>
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import AppAvatar from '@components/ui/AppAvatar.vue'

const props = defineProps({
  // Ein Eintrag aus der History-API (siehe App\History\HistoryEntry::toApi())
  entry: { type: Object, required: true },
  // Text für "erstellt"-Einträge, abhängig vom Datenmodell (z. B. "hat die Aufgabe erstellt")
  createdLabel: { type: String, default: 'hat den Eintrag erstellt' },
})

const memberName = computed(() => props.entry.Member?.Name || 'Unbekannt')

function formatDateTime(iso) {
  return new Date(iso).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function formatValue(change, value) {
  if (value == null) return 'leer'
  if (change.Format === 'date') {
    return new Date(value).toLocaleDateString('de-DE')
  }
  if (change.Format === 'datetime') {
    // "YYYY-MM-DD HH:MM:SS" — reine Datumsangaben (00:00 Uhr) ohne Uhrzeit anzeigen
    const date = new Date(value.replace(' ', 'T'))
    return value.endsWith('00:00:00')
      ? date.toLocaleDateString('de-DE')
      : date.toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  }
  return value
}
</script>
