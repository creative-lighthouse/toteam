<template>
  <div v-if="groupedParticipations" class="participants-section">
    <h3 class="event-participation_title">Teilnehmer</h3>
    <!-- Ein Tab-Stopp für die ganze Liste, Pfeiltasten wechseln zwischen den Karten -->
    <div v-roving-focus="{ selector: '.participant', label: 'Teilnehmer' }" class="participants-list">
      <template v-if="groupedParticipations.Accept.length > 0">
        <h5 class="participant-group_title">Zugesagt <span>({{ groupedParticipations.Accept.length }})</span></h5>
        <ParticipantCard
          v-for="p in groupedParticipations.Accept"
          :key="p.ID"
          :participation="p"
          :note-expanded="expandedNoteIds.has(p.ID)"
          @toggle-note="toggleNoteExpanded(p.ID)"
          v-context-menu="hasMenu(p) ? (e => onContextMenu(e, p)) : null"
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
          v-context-menu="hasMenu(p) ? (e => onContextMenu(e, p)) : null"
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
          v-context-menu="hasMenu(p) ? (e => onContextMenu(e, p)) : null"
        />
      </template>

      <template v-if="membersWithoutResponse.length > 0">
        <h5 class="participant-group_title">Ohne Antwort <span>({{ membersWithoutResponse.length }})</span></h5>
        <ParticipantCard
          v-for="p in membersWithoutResponse"
          :key="p.ID"
          :participation="p"
          v-context-menu="hasMenu(p) ? (e => onContextMenu(e, p)) : null"
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
import { useRouter } from 'vue-router'
import { useEventsStore } from '@stores/events'
import { vContextMenu, profileMenuItem } from '@utils/contextMenu'
import { vRovingFocus } from '@utils/rovingFocus'

const props = defineProps({
  event: { type: Object, required: true }
})

const eventsStore = useEventsStore()
const router = useRouter()
const expandedNoteIds = ref(new Set())
const participantMenu = ref(null)

// Innerhalb jeder Gruppe alphabetisch nach Namen
const byName = (a, b) => (a.MemberName ?? '').localeCompare(b.MemberName ?? '', 'de', { sensitivity: 'base' })

const groupedParticipations = computed(() => {
  if (!props.event.Participations) return null
  const ofType = type => props.event.Participations.filter(p => p.Type === type).sort(byName)
  return {
    Accept: ofType('Accept'),
    Maybe: ofType('Maybe'),
    Decline: ofType('Decline'),
  }
})

const membersWithoutResponse = computed(() =>
  (props.event.MembersWithoutResponse || []).map(m => ({
    ID: m.ID,
    MemberID: m.ID,
    MemberName: m.MemberName,
    Username: m.Username,
    ProfileImageURL: m.ProfileImageURL,
    Type: 'Pending',
  })).sort(byName)
)

// Menü: "Profil ansehen" für alle, darunter Antworten eintragen (nur mit CALENDAR_RECORD_RSVP)
function hasMenu(participation) {
  return !!participation.Username || props.event.CanRecordRsvp
}

function onContextMenu(event, participation) {
  const menuItems = [profileMenuItem(router, participation)].filter(Boolean)
  if (!props.event.CanRecordRsvp) {
    if (menuItems.length) participantMenu.value?.open(event, menuItems)
    return
  }

  const options = [
    { value: 'Accept', label: 'Zusagen' },
    { value: 'Maybe', label: 'Vielleicht' },
    { value: 'Decline', label: 'Absagen' },
  ].filter(o => o.value !== participation.Type)

  menuItems.push(...options.map(o => ({
    label: o.label,
    onClick: () => recordParticipation(participation.MemberID, o.value),
  })))

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
