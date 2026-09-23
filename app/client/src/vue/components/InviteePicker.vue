<template>
  <div v-if="availableOrgs.length > 1" class="field field--organizations">
    <label>Organisation(en) *</label>
    <OrganizationPicker v-model="localOrgIds" :orgs="availableOrgs" multiple />
  </div>

  <div v-if="organizationIds.length" class="field field--invited-members">
    <template v-if="loadingMembers">
      <label>Eingeladene Personen</label>
      <p class="invited-members-loading">Lade Mitglieder…</p>
    </template>
    <MemberPicker
      v-else
      v-model="localInvitedIds"
      :members="orgMembersForSelectedOrgs"
      multiple
      show-select-all
      label="Eingeladene Personen"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useEventsStore } from '@stores/events'
import OrganizationPicker from '@components/OrganizationPicker.vue'
import MemberPicker from '@components/MemberPicker.vue'

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

async function loadMembers() {
  loadingMembers.value = true
  try {
    orgMembersForSelectedOrgs.value = await eventsStore.fetchCalendarMembers(props.organizationIds)
  } finally {
    loadingMembers.value = false
  }
  if (props.autoSelectAll) {
    localInvitedIds.value = orgMembersForSelectedOrgs.value.map(m => m.ID)
  }
}

watch(() => [...props.organizationIds], () => {
  loadMembers()
}, { immediate: true })

function reset() {
  orgMembersForSelectedOrgs.value = []
}

defineExpose({ reset })
</script>
