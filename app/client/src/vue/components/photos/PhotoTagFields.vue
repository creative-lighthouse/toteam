<!--
  Tags für mehrere Fotos auf einmal wählen (Hochladen, Mehrfachauswahl): Personen,
  Charaktere, Räume und Schlagworte, je als PhotoTagSection. Gespeichert wird hier nichts —
  der Wert ist die Auswahl (siehe utils/photoTagging.js).

    <PhotoTagFields v-model="tagging" :options="store.optionsByOrg[orgId]" :event-ids="[eventId]" />
-->
<template>
  <div class="photo-tag-fields">
    <PhotoTagSection
      title="Personen"
      with-avatars
      add-label="Person markieren"
      placeholder="Name suchen…"
      :items="modelValue.people"
      :options="(options?.people ?? []).map(p => ({ id: p.ID, label: p.Name, avatar: p.Avatar, hint: p.IsGuest ? 'Gast' : '' }))"
      can-add
      :removable="() => true"
      :disabled="disabled"
      @add="add('people', $event)"
      @remove="remove('people', $event)"
    />
    <PhotoTagSection
      v-if="options?.characters.length"
      title="Charaktere"
      add-label="Charakter hinzufügen"
      placeholder="Charakter suchen…"
      :items="modelValue.characters"
      :options="options.characters.map(c => ({ id: c.ID, label: c.Title, hint: c.Script || '' }))"
      can-add
      :removable="() => true"
      :disabled="disabled"
      @add="add('characters', $event)"
      @remove="remove('characters', $event)"
    />
    <PhotoTagSection
      v-if="roomOptions.length"
      title="Räume"
      add-label="Raum hinzufügen"
      placeholder="Raum suchen…"
      :items="modelValue.rooms"
      :options="roomOptions"
      can-add
      :removable="() => true"
      :disabled="disabled"
      @add="add('rooms', $event)"
      @remove="remove('rooms', $event)"
    />
    <PhotoTagSection
      title="Schlagworte"
      add-label="Schlagwort hinzufügen"
      placeholder="Schlagwort suchen oder neu…"
      allow-create
      :items="modelValue.tags"
      :options="(options?.tags ?? []).map(t => ({ id: t.ID, label: t.Title }))"
      can-add
      :removable="() => true"
      :disabled="disabled"
      @add="add('tags', $event.create ? { id: `new-${$event.create}`, label: $event.create } : $event)"
      @remove="remove('tags', $event)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import PhotoTagSection from '@components/photos/PhotoTagSection.vue'

const props = defineProps({
  // { people, characters, rooms, tags } — siehe utils/photoTagging.js
  modelValue: { type: Object, required: true },
  // Auswahl der Organisation aus GET /photos/options
  options: { type: Object, default: null },
  // Events der Fotos: nur deren Lageplan-Räume sind wählbar
  eventIds: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const roomOptions = computed(() => {
  const events = new Set(props.eventIds)
  return [
    ...(props.options?.rooms ?? []).map(r => ({ id: `r${r.ID}`, label: r.Title, hint: 'Inventar' })),
    ...(props.options?.areas ?? [])
      .filter(a => events.has(a.EventID))
      .map(a => ({ id: `a${a.ID}`, label: a.Title, hint: a.Plan ? `Lageplan: ${a.Plan}` : 'Lageplan' })),
  ]
})

function add(kind, item) {
  if (props.modelValue[kind].some(i => i.id === item.id)) return
  emit('update:modelValue', { ...props.modelValue, [kind]: [...props.modelValue[kind], { ...item }] })
}

function remove(kind, item) {
  emit('update:modelValue', { ...props.modelValue, [kind]: props.modelValue[kind].filter(i => i.id !== item.id) })
}
</script>
