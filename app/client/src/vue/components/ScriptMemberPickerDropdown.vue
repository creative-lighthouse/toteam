<template>
  <select
    class="input script-member-picker"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel"
    @change="onChange($event.target.value)"
  >
    <option value="">— Person wählen —</option>
    <option v-for="m in orderedMembers" :key="m.ID" :value="m.ID">
      {{ m.ID === authStore.user?.ID ? `${m.Name} (Ich)` : m.Name }}
    </option>
  </select>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@stores/auth'

const props = defineProps({
  members: { type: Array, default: () => [] },
  modelValue: { type: Number, default: null },
  ariaLabel: { type: String, default: 'Person auswählen' },
})
const emit = defineEmits(['update:modelValue'])
const authStore = useAuthStore()

// The current user is always pinned to the top of the list.
const orderedMembers = computed(() => {
  const selfId = authStore.user?.ID
  const self = props.members.filter(m => m.ID === selfId)
  const others = props.members.filter(m => m.ID !== selfId)
  return [...self, ...others]
})

function onChange(value) {
  emit('update:modelValue', value ? parseInt(value) : null)
}
</script>
