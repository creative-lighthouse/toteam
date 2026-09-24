<template>
  <div class="person-filter">
    <AppIconButton
      :variant="store.filterPersonId ? 'primary' : 'neutral'"
      aria-label="Aufgaben nach Person filtern"
      @click.stop="toggleOpen"
    >
      <span class="icon-mask" :style="personSearchIconStyle"></span>
    </AppIconButton>

    <div v-if="open" class="person-filter_backdrop" @click="close"></div>

    <div v-if="open" class="person-filter_dropdown" @click.stop>
      <MemberPicker
        :members="store.assignableMembers"
        :model-value="store.filterPersonId"
        @update:model-value="select"
        searchable
        pin-self
        :self-id="authStore.currentUser?.ID"
      />

      <button
        v-if="store.filterPersonId"
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
  store.setPersonFilter(memberId)
  close()
}

function clearFilter() {
  store.setPersonFilter(null)
  close()
}

onMounted(() => {
  store.fetchAssignableMembers()
})
</script>
