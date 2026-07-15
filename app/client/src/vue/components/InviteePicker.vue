<template>
  <label class="field field--organizations">
    Organisation(en) *
    <div class="multiselect-group">
      <label
      v-for="org in availableOrgs"
      :key="org.ID"
      class="checkbox-label"
      >
        <input type="checkbox" :value="org.ID" v-model="localOrgIds" />
        {{ org.Title }}
      </label>
    </div>
  </label>

  <div class="field field--invited-members">
    <span class="invited-members-header">
      Eingeladene Personen
      <AppButton
        type="button"
        size="small"
        variant="secondary"
        :disabled="!orgMembersForSelectedOrgs.length"
        @click="toggleAllInvited"
      >{{ allInvitedSelected ? 'Alle abwählen' : 'Alle auswählen' }}</AppButton>
    </span>
    <p v-if="loadingMembers" class="invited-members-loading">Lade Mitglieder…</p>
    <p v-else-if="!orgMembersForSelectedOrgs.length" class="invited-members-loading">Bitte zuerst eine Organisation wählen.</p>
    <div v-else class="member-search">
      <div v-if="selectedInvitedMembers.length" class="member-chip-list">
        <span v-for="m in selectedInvitedMembers" :key="m.ID" class="member-chip">
          {{ m.Name }}
          <button
            type="button"
            class="member-chip_remove"
            aria-label="Entfernen"
            @click="removeInvitedMember(m.ID)"
          >×</button>
        </span>
      </div>
      <div class="member-search-input-wrap">
        <input
          type="text"
          v-model="memberSearchQuery"
          placeholder="Name eingeben, um Personen hinzuzufügen…"
          @focus="memberDropdownOpen = true"
          @blur="memberDropdownOpen = false"
          @keydown.enter.prevent="addFirstFilteredMember"
          @keydown.escape="memberDropdownOpen = false"
        />
        <ul v-if="memberDropdownOpen && filteredMemberOptions.length" class="member-search-dropdown">
          <li v-for="m in filteredMemberOptions" :key="m.ID">
            <button type="button" @mousedown.prevent="addInvitedMember(m.ID)">{{ m.Name }}</button>
          </li>
        </ul>
        <p v-else-if="memberDropdownOpen && memberSearchQuery && !filteredMemberOptions.length" class="member-search-empty">
          Keine Treffer
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/AppButton.vue'

const props = defineProps({
  organizationIds: { type: Array, required: true },
  invitedMemberIds: { type: Array, required: true },
  availableOrgs: { type: Array, default: () => [] },
  // Nach dem Laden der Mitgliederliste automatisch alle als eingeladen markieren
  // (Neuanlage). Beim Bearbeiten false, damit die bestehende Auswahl erhalten bleibt.
  autoSelectAll: { type: Boolean, default: true },
})

const emit = defineEmits(['update:organizationIds', 'update:invitedMemberIds'])

const eventsStore = useEventsStore()

const localOrgIds = computed({
  get: () => props.organizationIds,
  set: (val) => emit('update:organizationIds', val),
})

const localInvitedIds = computed({
  get: () => props.invitedMemberIds,
  set: (val) => emit('update:invitedMemberIds', val),
})

const orgMembersForSelectedOrgs = ref([])
const loadingMembers = ref(false)
const memberSearchQuery = ref('')
const memberDropdownOpen = ref(false)

const allInvitedSelected = computed(() =>
  orgMembersForSelectedOrgs.value.length > 0 &&
  props.invitedMemberIds.length === orgMembersForSelectedOrgs.value.length
)

function toggleAllInvited() {
  localInvitedIds.value = allInvitedSelected.value
    ? []
    : orgMembersForSelectedOrgs.value.map(m => m.ID)
}

async function loadMembers() {
  loadingMembers.value = true
  memberSearchQuery.value = ''
  memberDropdownOpen.value = false
  try {
    orgMembersForSelectedOrgs.value = await eventsStore.fetchCalendarMembers(props.organizationIds)
  } finally {
    loadingMembers.value = false
  }
  if (props.autoSelectAll) {
    localInvitedIds.value = orgMembersForSelectedOrgs.value.map(m => m.ID)
  }
}

const selectedInvitedMembers = computed(() =>
  orgMembersForSelectedOrgs.value.filter(m => props.invitedMemberIds.includes(m.ID))
)

const filteredMemberOptions = computed(() => {
  const query = memberSearchQuery.value.trim().toLowerCase()
  return orgMembersForSelectedOrgs.value
    .filter(m => !props.invitedMemberIds.includes(m.ID))
    .filter(m => !query || m.Name.toLowerCase().includes(query))
    .slice(0, 30)
})

function addInvitedMember(id) {
  if (!props.invitedMemberIds.includes(id)) {
    localInvitedIds.value = [...props.invitedMemberIds, id]
  }
  memberSearchQuery.value = ''
}

function removeInvitedMember(id) {
  localInvitedIds.value = props.invitedMemberIds.filter(i => i !== id)
}

function addFirstFilteredMember() {
  if (filteredMemberOptions.value.length) {
    addInvitedMember(filteredMemberOptions.value[0].ID)
  }
}

watch(() => [...props.organizationIds], () => {
  loadMembers()
}, { immediate: true })

function reset() {
  orgMembersForSelectedOrgs.value = []
  memberSearchQuery.value = ''
  memberDropdownOpen.value = false
}

defineExpose({ reset })
</script>
