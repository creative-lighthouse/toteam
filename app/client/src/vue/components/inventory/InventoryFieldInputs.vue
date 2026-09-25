<!--
  Eingabefelder für die Zusatzfelder einer Art (Objekte und Räume). Rendert sich
  ohne eigenen Wrapper als .field-Blöcke direkt ins umgebende .modalform-Grid.

    <InventoryFieldInputs v-model="form.values" :fields="type.Fields" />

  v-model: { "<Feld-ID>": "<Wert>" } — Werte als Strings (Ja/Nein als "1"/"0").
  Mit `individual-hint` werden "pro Objekt"-Felder markiert (z.B. beim Bearbeiten
  einer ganzen Gruppe, wo sie nur für das aktuelle Objekt gelten).
-->
<template>
  <template v-for="field in fields" :key="field.ID">
    <AppToggle
      v-if="field.Input === 'boolean'"
      :model-value="modelValue[field.ID] === '1'"
      :label="field.Label"
      field-class="field field--3 toggle-field"
      @update:model-value="v => set(field, v ? '1' : '0')"
    />
    <label v-else :class="field.Input === 'textarea' ? 'field' : 'field field--3'">
      {{ field.Label }}{{ field.Unit ? ` (${field.Unit})` : '' }}
      <span v-if="field.Individual && individualHint" class="inventory-field-inputs_individual">{{ individualHint }}</span>
      <textarea
        v-if="field.Input === 'textarea'"
        :value="modelValue[field.ID] ?? ''"
        rows="3"
        @input="set(field, $event.target.value)"
      />
      <input
        v-else
        :value="modelValue[field.ID] ?? ''"
        :type="field.Input === 'date' ? 'date' : 'text'"
        :inputmode="field.Input === 'number' ? 'decimal' : undefined"
        autocomplete="off"
        @input="set(field, $event.target.value)"
      >
    </label>
  </template>
</template>

<script setup>
import AppToggle from '@components/ui/AppToggle.vue'

const props = defineProps({
  // [{ ID, Label, Unit, Input, Individual }]
  fields: { type: Array, required: true },
  modelValue: { type: Object, default: () => ({}) },
  individualHint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

function set(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field.ID]: value })
}
</script>
