<template>
  <details class="script-role-dropdown" :open="open" @toggle="open = $event.target.open">
    <summary class="script-role-dropdown_summary" :title="summaryTitle">{{ summaryText }}</summary>
    <div class="script-role-dropdown_menu">
      <label class="script-role-dropdown_option script-role-dropdown_option--direction">
        <input
          type="checkbox"
          :checked="direction"
          @change="toggleDirection($event.target.checked)"
        />
        Regieanweisung
      </label>

      <div class="script-role-dropdown_sep" />

      <label
        v-for="role in roles"
        :key="role.ID"
        class="script-role-dropdown_option"
      >
        <input
          type="checkbox"
          :disabled="direction"
          :checked="modelValue.includes(role.ID)"
          @change="toggle(role.ID, $event.target.checked)"
        />
        {{ role.Title }}
      </label>
      <p v-if="roles.length === 0" class="script-role-dropdown_empty">Keine Rollen angelegt</p>
    </div>
  </details>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  roles: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
  direction: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'update:direction'])

const open = ref(false)

const selectedTitles = computed(() =>
  props.roles.filter(r => props.modelValue.includes(r.ID)).map(r => r.Title)
)

// Shown in full where the column width allows — the summary just clips with
// an ellipsis (see CSS) rather than pre-truncating the text here, and the
// full list is always available via the title tooltip.
const summaryText = computed(() => {
  if (props.direction) return 'Regieanweisung'
  if (selectedTitles.value.length === 0) return '+'
  return selectedTitles.value.join(', ')
})

const summaryTitle = computed(() => {
  if (props.direction) return 'Regieanweisung'
  return selectedTitles.value.length ? selectedTitles.value.join(', ') : 'Rolle festlegen'
})

function toggle(roleId, checked) {
  const next = checked
    ? [...props.modelValue, roleId]
    : props.modelValue.filter(id => id !== roleId)
  emit('update:modelValue', next)
}

// A stage direction has no speaker — marking a paragraph as one clears any
// role it had, and roles stay disabled (see template) while it's active so
// the two states can't coexist.
function toggleDirection(checked) {
  emit('update:direction', checked)
  if (checked && props.modelValue.length) {
    emit('update:modelValue', [])
  }
}
</script>
