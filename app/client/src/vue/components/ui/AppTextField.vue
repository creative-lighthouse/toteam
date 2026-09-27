<!--
  Text-Eingabefeld für Modal-Formulare: Beschriftung, Eingabe und — bei
  gesetztem `maxlength` — ein kleiner Zeichenzähler direkt darunter ("12/40").
  Rendert sich wie AppToggle als kompletter .field-Block, einfach direkt als
  Kind eines .modalform verwenden:

    <AppTextField v-model="form.title" label="Titel *" :maxlength="40" placeholder="z.B. Mittagessen" required />
    <AppTextField v-model="form.notes" label="Notiz" multiline :rows="3" :maxlength="500" />

  Weitere Attribute (placeholder, required, autocomplete, …) landen auf dem
  <input>/<textarea>. Breite im Grid per `field-class`, z. B. "field field--3".
-->
<template>
  <div :class="fieldClass">
    <label v-if="label" :for="inputId">{{ label }}</label>
    <textarea
      v-if="multiline"
      :id="inputId"
      v-bind="$attrs"
      :value="modelValue"
      :rows="rows"
      :maxlength="maxlength || undefined"
      :aria-describedby="maxlength ? countId : undefined"
      @input="$emit('update:modelValue', $event.target.value)"
    ></textarea>
    <input
      v-else
      :id="inputId"
      v-bind="$attrs"
      :type="type"
      :value="modelValue"
      :maxlength="maxlength || undefined"
      :aria-describedby="maxlength ? countId : undefined"
      @input="$emit('update:modelValue', $event.target.value)"
    >
    <span
      v-if="maxlength"
      :id="countId"
      class="app-text-field_count"
      :class="{ 'app-text-field_count--limit': length >= maxlength }"
    >
      {{ length }}/{{ maxlength }}
      <span class="app-text-field_count-sr">Zeichen</span>
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  maxlength: { type: Number, default: null },
  multiline: { type: Boolean, default: false },
  rows: { type: Number, default: 3 },
  fieldClass: { type: String, default: 'field' },
})

defineEmits(['update:modelValue'])

const uid = Math.random().toString(36).slice(2)
const inputId = `app-text-field-${uid}`
const countId = `app-text-field-count-${uid}`
const length = computed(() => (props.modelValue ?? '').length)
</script>
