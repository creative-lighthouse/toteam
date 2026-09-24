<template>
  <div v-if="groupedParticipations" class="participants-section">
    <h3 class="event-participation_title">Teilnehmer</h3>
    <div class="participants-list">
      <template v-if="groupedParticipations.Accept.length > 0">
        <h5 class="participant-group_title">Zugesagt <span>({{ groupedParticipations.Accept.length }})</span></h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Accept"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
          @contextmenu="onContextMenu($event, p)"
        />
      </template>

      <template v-if="groupedParticipations.Maybe.length > 0">
        <h5 class="participant-group_title">Vielleicht <span>({{ groupedParticipations.Maybe.length }})</span></h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Maybe"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
          @contextmenu="onContextMenu($event, p)"
        />
      </template>

      <template v-if="groupedParticipations.Decline.length > 0">
        <h5 class="participant-group_title">Abgesagt <span>({{ groupedParticipations.Decline.length }})</span></h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Decline"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
          @contextmenu="onContextMenu($event, p)"
        />
      </template>

      <template v-if="membersWithoutResponse.length > 0">
        <h5 class="participant-group_title">Ohne Antwort <span>({{ membersWithoutResponse.length }})</span></h5>
        <ParticipantCard
          v-for="p in membersWithoutResponse"
          :key="p.ID"
          :participation="p"
          @contextmenu="onContextMenu($event, p)"
        />
      </template>
    </div>

    <ContextMenu ref="participantMenu" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import ParticipantCard from '@components/calendar/ParticipantCard.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import { useEventsStore } from '@stores/events'

const props = defineProps({
  event: { type: Object, required: true }
})

const eventsStore = useEventsStore()
const expandedNoteIds = ref(new Set())
const participantMenu = ref(null)

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
    MemberID: m.ID,
    MemberName: m.MemberName,
    ProfileImageURL: m.ProfileImageURL,
    Type: 'Pending',
  }))
)

function onContextMenu(event, participation) {
  if (!props.event.CanRecordRsvp) return

  const options = [
    { value: 'Accept', label: 'Zusagen' },
    { value: 'Maybe', label: 'Vielleicht' },
    { value: 'Decline', label: 'Absagen' },
  ].filter(o => o.value !== participation.Type)

  const menuItems = options.map(o => ({
    label: o.label,
    onClick: () => recordParticipation(participation.MemberID, o.value),
  }))

  if (participation.Type !== 'Pending') {
    menuItems.push({
      label: 'Antwort entfernen',
      danger: true,
      onClick: () => recordParticipation(participation.MemberID, null),
    })
  }

  participantMenu.value?.open(event, menuItems)
}

async function recordParticipation(targetMemberId, type) {
  try {
    await eventsStore.changeParticipationFor(props.event.ID, targetMemberId, type)
  } catch (e) {
    alert('Fehler: ' + e.message)
  }
}

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
