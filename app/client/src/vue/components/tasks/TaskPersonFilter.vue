<template>
  <div class="person-filter">
    <AppIconButton
      :variant="modelValue ? 'primary' : 'neutral'"
      aria-label="Aufgaben nach Person filtern"
      @click.stop="toggleOpen"
    >
      <span class="icon-mask" :style="personSearchIconStyle"></span>
    </AppIconButton>

    <div v-if="open" class="person-filter_backdrop" @click="close"></div>

    <div v-if="open" class="person-filter_dropdown" @click.stop>
      <MemberPicker
        :members="store.assignableMembers"
        :model-value="modelValue"
        @update:model-value="select"
        searchable
        autofocus
        pin-self
        :self-id="authStore.currentUser?.ID"
      />

      <button
        v-if="modelValue"
        type="button"
        class="person-filter_clear"
        @click="clearFilter"
      >
        Filter zurücksetzen
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useTasksStore } from '@stores/tasks'
import { useAuthStore } from '@stores/auth'
import AppIconButton from '@components/ui/AppIconButton.vue'
import MemberPicker from '@components/ui/MemberPicker.vue'
import PersonSearchIcon from '../../../../icons/person_search.svg'

// Gefilterte Person (Member-ID) — Übersicht: Filter im tasks-Store, Detailseite: lokaler Filter
defineProps({
  modelValue: { type: Number, default: null },
})
const emit = defineEmits(['update:modelValue'])

const store = useTasksStore()
const authStore = useAuthStore()

const personSearchIconStyle = { maskImage: `url("${PersonSearchIcon}")`, WebkitMaskImage: `url("${PersonSearchIcon}")` }

const open = ref(false)

function toggleOpen() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function select(memberId) {
  emit('update:modelValue', memberId)
  close()
}

function clearFilter() {
  emit('update:modelValue', null)
  close()
}

onMounted(() => {
  store.fetchAssignableMembers()
})
</script>
