<template>
  <div v-if="groupedParticipations" class="participants-section">
    <h3 class="event-participation_title">Teilnehmer</h3>
    <div class="participants-list">
      <template v-if="groupedParticipations.Accept.length > 0">
        <h5 class="participant-group_title">Zugesagt ({{ groupedParticipations.Accept.length }})</h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Accept"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
        />
      </template>

      <template v-if="groupedParticipations.Maybe.length > 0">
        <h5 class="participant-group_title">Vielleicht ({{ groupedParticipations.Maybe.length }})</h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Maybe"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
        />
      </template>

      <template v-if="groupedParticipations.Decline.length > 0">
        <h5 class="participant-group_title">Abgesagt ({{ groupedParticipations.Decline.length }})</h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Decline"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
        />
      </template>

      <template v-if="membersWithoutResponse.length > 0">
        <h5 class="participant-group_title">Ohne Antwort ({{ membersWithoutResponse.length }})</h5>
        <ParticipantCard
          v-for="p in membersWithoutResponse"
          :key="p.ID"
          :participation="p"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import ParticipantCard from '@components/ParticipantCard.vue'

const props = defineProps({
  event: { type: Object, required: true }
})

const expandedNoteIds = ref(new Set())

const groupedParticipations = computed(() => {
  if (!props.event.Participations) return null
  return {
    Accept: props.event.Participations.filter(p => p.Type === 'Accept'),
    Maybe: props.event.Participations.filter(p => p.Type === 'Maybe'),
    Decline: props.event.Participations.filter(p => p.Type === 'Decline'),
  }
})

const membersWithoutResponse = computed(() =>
  (props.event.MembersWithoutResponse || []).map(m => ({
    ID: m.ID,
    MemberName: m.MemberName,
    ProfileImageURL: m.ProfileImageURL,
    Type: 'Pending',
  }))
)

function toggleNoteExpanded(participationId) {
  const next = new Set(expandedNoteIds.value)
  if (next.has(participationId)) {
    next.delete(participationId)
  } else {
    next.add(participationId)
  }
  expandedNoteIds.value = next
}
</script>
