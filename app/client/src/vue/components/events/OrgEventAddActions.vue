<template>
  <!-- "Hinzufügen" auf der Event-Seite: Inhalte, die man dem Event zuordnen kann.
       Sobald es einen davon gibt, erscheint seine eigene Karte (Termine, Skripte, …). -->
  <section class="org-event-add-actions">
    <h2 class="hl3 org-event-add-actions_title">Hinzufügen</h2>
    <ul class="org-event-add-actions_list">
      <li v-for="action in actions" :key="action.key">
        <button type="button" class="org-event-add-actions_option" @click="emit('select', action.key)">
          <span class="org-event-add-actions_option-title">+ {{ action.label }}</span>
          <span v-if="action.hint" class="org-event-add-actions_option-hint">{{ action.hint }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup>
defineProps({
  // [{ key, label, hint? }] — welche Aktionen der Nutzer hier hat, entscheidet die Event-Seite
  actions: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])
</script>
