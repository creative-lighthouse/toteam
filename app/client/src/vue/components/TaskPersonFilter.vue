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
      <input
        ref="searchInput"
        type="text"
        class="person-filter_search input"
        v-model="query"
        placeholder="Person suchen…"
        @keydown.escape="close"
      />

      <ul class="person-filter_list">
        <li v-for="p in filteredOptions" :key="p.ID">
          <button
            type="button"
            class="person-filter_option"
            :class="{ 'person-filter_option--selected': p.ID === store.filterPersonId }"
            @click="select(p.ID)"
          >
            <AppAvatar :src="p.Avatar" :alt="p.Name" img-class="person-filter_avatar" />
            <span class="person-filter_name">{{ p.Name }}<template v-if="isSelf(p)"> (Du)</template></span>
          </button>
        </li>
        <li v-if="!filteredOptions.length" class="person-filter_empty">Keine Treffer</li>
      </ul>

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
import { ref, computed, nextTick, onMounted } from 'vue'
import { useTasksStore } from '@stores/tasks'
import { useAuthStore } from '@stores/auth'
import AppIconButton from '@components/AppIconButton.vue'
import AppAvatar from '@components/AppAvatar.vue'
import PersonSearchIcon from '../../../icons/person_search.svg'

const store = useTasksStore()
const authStore = useAuthStore()

const personSearchIconStyle = { maskImage: `url("${PersonSearchIcon}")`, WebkitMaskImage: `url("${PersonSearchIcon}")` }

const open = ref(false)
const query = ref('')
const searchInput = ref(null)

function isSelf(p) {
  return p.ID === authStore.currentUser?.ID
}

// Myself always pinned first, everyone else alphabetically after.
const sortedMembers = computed(() => {
  const members = store.assignableMembers
  const self = members.find(isSelf)
  const others = members.filter(m => !isSelf(m)).sort((a, b) => a.Name.localeCompare(b.Name))
  return self ? [self, ...others] : others
})

const filteredOptions = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return sortedMembers.value
  return sortedMembers.value.filter(p => p.Name?.toLowerCase().includes(q))
})

async function toggleOpen() {
  open.value = !open.value
  if (open.value) {
    query.value = ''
    await nextTick()
    searchInput.value?.focus()
  }
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
