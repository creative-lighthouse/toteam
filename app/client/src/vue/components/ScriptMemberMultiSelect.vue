<template>
  <div class="script-member-multiselect">
    <div v-if="selectedMembers.length" class="member-chip-list">
      <span v-for="m in selectedMembers" :key="m.ID" class="member-chip">
        {{ m.Name }}
        <button
          type="button"
          class="member-chip_remove"
          aria-label="Entfernen"
          @click="remove(m.ID)"
        >×</button>
      </span>
    </div>

    <div class="member-search-input-wrap">
      <input
        type="text"
        class="input"
        v-model="query"
        :placeholder="placeholder"
        @focus="open = true"
        @blur="open = false"
        @keydown.enter.prevent="addFirstMatch"
        @keydown.escape="open = false"
      />
      <ul v-if="open && filteredOptions.length" class="member-search-dropdown">
        <li v-for="m in filteredOptions" :key="m.ID">
          <button type="button" @mousedown.prevent="add(m.ID)">{{ m.Name }}</button>
        </li>
      </ul>
      <p v-else-if="open && query && !filteredOptions.length" class="member-search-empty">
        Keine Treffer
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  members: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Name eingeben, um Mitglieder hinzuzufügen…' },
})
const emit = defineEmits(['update:modelValue'])

const query = ref('')
const open = ref(false)

const selectedMembers = computed(() =>
  props.members.filter(m => props.modelValue.includes(m.ID))
)

const filteredOptions = computed(() => {
  const q = query.value.trim().toLowerCase()
  return props.members
    .filter(m => !props.modelValue.includes(m.ID))
    .filter(m => !q || m.Name.toLowerCase().includes(q))
    .slice(0, 30)
})

function add(id) {
  if (!props.modelValue.includes(id)) {
    emit('update:modelValue', [...props.modelValue, id])
  }
  query.value = ''
}

function remove(id) {
  emit('update:modelValue', props.modelValue.filter(i => i !== id))
}

function addFirstMatch() {
  if (filteredOptions.value.length) {
    add(filteredOptions.value[0].ID)
  }
}
</script>
