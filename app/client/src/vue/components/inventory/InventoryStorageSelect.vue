<template>
  <!-- Auswahl eines Lagerpunkts (Tab "Lager") als eingerückte Baumliste, z.B. im
       Objekt- und Raum-Formular; rendert sich als kompletter .field-Block -->
  <label class="field">
    {{ label }}
    <select :value="modelValue ?? ''" @change="emit('update:modelValue', $event.target.value ? parseInt($event.target.value) : null)">
      <option value="">{{ emptyLabel }}</option>
      <!-- Zugeordnet, aber für mich nicht sichtbar (z.B. privater Lagerpunkt anderer) — bleibt beim Speichern erhalten -->
      <option v-if="unknownSelected" :value="modelValue">Lagerpunkt ohne Zugriff</option>
      <option v-for="{ location, depth } in options" :key="location.ID" :value="location.ID">
        {{ indent(depth) }}{{ location.Title }}{{ location.Type ? ` (${location.Type.Title})` : '' }}
      </option>
    </select>
  </label>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useStorageStore } from '@stores/storage'

const props = defineProps({
  modelValue: { type: Number, default: null },
  label: { type: String, default: 'Lagerort' },
  emptyLabel: { type: String, default: 'Nicht zugeordnet' },
  // Diesen Lagerpunkt samt Inhalt ausblenden (beim Bearbeiten eines Lagerpunkts)
  excludeId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

const store = useStorageStore()

const options = computed(() => store.flatTree(props.excludeId))
const unknownSelected = computed(() =>
  !!props.modelValue && !store.loading && !options.value.some(o => o.location.ID === props.modelValue)
)

// Eingerückt per geschützten Leerzeichen — <option> kennt kein CSS-Padding
function indent(depth) {
  return depth ? '   '.repeat(depth) + '└ ' : ''
}

onMounted(() => {
  if (!store.locations.length) store.fetchLocations()
})
</script>
