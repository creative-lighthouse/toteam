<template>
  <AppModal ref="modal" class="poll-dialog" @close="$emit('close')">
    <template #header>
      <AppOrgLogo
        v-if="primaryOrg"
        :src="primaryOrg.LogoURL"
        :name="primaryOrg.Title"
        alt=""
        :size="32"
      />
      <div class="event_title">
        <h2 class="hl2">{{ event.Title }}</h2>
        <p class="poll-badge" title="Terminfindung">
          <span class="poll-badge_icon" :style="scheduleIconStyle" aria-hidden="true"></span>
        </p>
      </div>
    </template>

        <div class="dialog-infobox">
          <div v-if="canManageContent" class="event-manage-actions">
            <AppIconButton
              variant="primary"
              aria-label="Terminfindung bearbeiten"
              @click="$emit('edit-poll', event)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
          </div>

          <div v-if="event.Location || event.Description" class="event-info">
            <p v-if="event.Location">
              <strong>Ort: </strong>
              <a
                :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(event.Location)}`"
                target="_blank"
                rel="noopener"
                class="location-link"
              >{{ event.Location }}</a>
            </p>
            <div v-if="event.Description" class="event-description">
              <strong>Beschreibung:</strong>
              <p class="event-description_text"><AppLinkifiedText :text="event.Description" /></p>
            </div>
          </div>

          <div class="poll-options-section">
            <h3 class="event-participation_title">Terminoptionen</h3>

            <!-- Je Option eine Zeile: Datum (klappt Teilnehmer auf) und drei Antwort-Buttons mit Anzahl -->
            <div class="poll-table" role="list" aria-label="Terminoptionen">
              <div
                v-for="option in sortedOptions"
                :key="option.OptionID"
                class="poll-table_row"
                :class="{ 'poll-table_row--open': expandedId === option.OptionID }"
                role="listitem"
                @click="toggle(option)"
              >
                <button
                  type="button"
                  class="poll-table_date"
                  :aria-expanded="expandedId === option.OptionID"
                  :title="expandedId === option.OptionID ? 'Teilnehmer ausblenden' : 'Teilnehmer anzeigen'"
                >
                  <span class="poll-table_chevron" aria-hidden="true"></span>
                  <span class="poll-table_date-text">
                    <strong>{{ option.RenderDate }}</strong>
                    <span v-if="option.RenderTime" class="poll-table_time">{{ option.RenderTime }}</span>
                  </span>
                </button>

                <!-- Ein Tab-Stopp je Option; innerhalb der Gruppe per Pfeiltasten wechseln,
                     Leertaste/Enter stimmt ab -->
                <div
                  class="poll-table_votes"
                  role="radiogroup"
                  :aria-label="`Deine Antwort für ${option.RenderDate}${option.RenderTime ? ', ' + option.RenderTime : ''}`"
                  :aria-busy="votingOptionId === option.OptionID"
                  @click.stop
                  @keydown="onVoteKeydown($event, option)"
                >
                  <button
                    v-for="choice in VOTE_OPTIONS"
                    :key="choice.value"
                    type="button"
                    role="radio"
                    class="poll-table_vote"
                    :class="[`poll-table_vote--${choice.tone}`, { 'poll-table_vote--active': option.UserVote === choice.value }]"
                    :aria-checked="option.UserVote === choice.value"
                    :aria-label="`${choice.label}, ${count(option, choice.value)} ${count(option, choice.value) === 1 ? 'Stimme' : 'Stimmen'}`"
                    :aria-disabled="votingOptionId === option.OptionID"
                    :tabindex="choice.value === focusableVote(option) ? 0 : -1"
                    :title="`${choice.label} (${count(option, choice.value)})`"
                    @click="vote(option, choice.value)"
                  >
                    <img :src="choice.icon" alt="">
                    <span aria-hidden="true">{{ count(option, choice.value) }}</span>
                  </button>
                </div>

                <div
                  v-if="expandedId === option.OptionID"
                  v-roving-focus="{ selector: '.participant', label: `Antworten für ${option.RenderDate}` }"
                  class="poll-table_participants"
                  @click.stop
                >
                  <ParticipantCard
                    v-for="p in option.Participations"
                    :key="p.ID"
                    v-context-menu="p.Username ? (e => openParticipantMenu(e, p)) : null"
                    :participation="p"
                  />
                  <p v-if="!option.Participations?.length" class="event-section-empty">Noch keine Antworten.</p>
                </div>
              </div>
            </div>
          </div>

        </div>

    <!-- Im Dialog statt nach body, sonst läge das Menü hinter dem Top Layer -->
    <ContextMenu ref="participantMenu" />

    <template v-if="canManageContent && sortedOptions.length" #actions>
      <AppButton variant="primary" @click="openFinalize">Termin festlegen</AppButton>
    </template>

    <Transition name="fade">
      <div v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]">
        {{ statusMessage.text }}
      </div>
    </Transition>
  </AppModal>

  <PollFinalizeModal ref="finalizeModal" @finalized="emit('finalized')" />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOrganizationsStore } from '@stores/organizations'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/ui/AppButton.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppModal from '@components/ui/AppModal.vue'
import ParticipantCard from '@components/calendar/ParticipantCard.vue'
import ContextMenu from '@components/ui/ContextMenu.vue'
import { vContextMenu, profileMenuItem } from '@utils/contextMenu'
import { vRovingFocus } from '@utils/rovingFocus'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import PollFinalizeModal from '@components/calendar/PollFinalizeModal.vue'
import ScheduleIcon from '../../../../icons/actions/action_schedule.svg'
import AcceptIcon from '../../../../icons/states/participation_accept.svg'
import MaybeIcon from '../../../../icons/states/participation_maybe.svg'
import DeclineIcon from '../../../../icons/states/participation_decline.svg'

const scheduleIconStyle = {
  maskImage: `url("${ScheduleIcon}")`,
  WebkitMaskImage: `url("${ScheduleIcon}")`,
}

const props = defineProps({
  event: { type: Object, required: true }
})

const emit = defineEmits(['close', 'edit-poll', 'finalized'])

const router = useRouter()
const participantMenu = ref(null)

function openParticipantMenu(event, participation) {
  const item = profileMenuItem(router, participation)
  if (item) participantMenu.value?.open(event, [item])
}

// Erste Organisation der Terminfindung (ohne Logo zeigt AppOrgLogo die Initiale — wie in der EventCard)
const primaryOrg = computed(() =>
  props.event.OrganizationLogos?.[0]
    || (props.event.OrganizationLogoURL ? { LogoURL: props.event.OrganizationLogoURL, Title: '' } : null)
)

const orgsStore = useOrganizationsStore()
const eventsStore = useEventsStore()
const modal = ref(null)
const statusMessage = ref(null)
const votingOptionId = ref(null)

const VOTE_OPTIONS = [
  { value: 'Decline', label: 'Absagen', tone: 'negative', icon: DeclineIcon },
  { value: 'Maybe', label: 'Vielleicht', tone: 'warning', icon: MaybeIcon },
  { value: 'Accept', label: 'Zusagen', tone: 'positive', icon: AcceptIcon },
]

// Anzahl direkt aus den Participations zählen statt aus VotedYes/-Maybe/-No:
// die Participations aktualisiert der Store nach dem Abstimmen sofort, die
// Zähler vom Server erst beim nächsten Laden
function count(option, type) {
  return (option.Participations || []).filter(p => p.Type === type).length
}

// Teilnehmerliste einer Option aufgeklappt (höchstens eine gleichzeitig)
const expandedId = ref(null)
function toggle(option) {
  expandedId.value = expandedId.value === option.OptionID ? null : option.OptionID
}

const finalizeModal = ref(null)
function openFinalize() {
  finalizeModal.value?.open({ pollId: props.event.PollID, options: sortedOptions.value })
}

const canManageContent = computed(() => {
  const orgIds = props.event.OrganizationIDs ?? []
  return orgsStore.organizations.some(o =>
    orgIds.includes(o.ID) && o.Permissions?.includes('CALENDAR_MANAGE')
  )
})

const sortedOptions = computed(() =>
  [...(props.event.PollOptions ?? [])].sort((a, b) => {
    const aKey = `${a.DateStart ?? ''} ${a.TimeStart ?? ''}`
    const bKey = `${b.DateStart ?? ''} ${b.TimeStart ?? ''}`
    return aKey.localeCompare(bKey)
  })
)

function showStatusMessage(text, type = 'success') {
  statusMessage.value = { text, type }
  setTimeout(() => { statusMessage.value = null }, 3000)
}


// Roving Tabindex: nur die gewählte Antwort (sonst die erste) ist per Tab erreichbar
function focusableVote(option) {
  return VOTE_OPTIONS.some(c => c.value === option.UserVote) ? option.UserVote : VOTE_OPTIONS[0].value
}

// Pfeiltasten/Pos1/Ende verschieben nur den Fokus — abgestimmt wird erst mit
// Leertaste/Enter, damit nicht jeder Tastendruck eine Stimme abschickt
function onVoteKeydown(event, option) {
  const buttons = [...event.currentTarget.querySelectorAll('[role="radio"]')]
  const current = buttons.indexOf(document.activeElement)
  if (current === -1) return
  const last = buttons.length - 1
  const targets = {
    ArrowRight: current === last ? 0 : current + 1,
    ArrowDown: current === last ? 0 : current + 1,
    ArrowLeft: current === 0 ? last : current - 1,
    ArrowUp: current === 0 ? last : current - 1,
    Home: 0,
    End: last,
  }
  if (!(event.key in targets)) return
  event.preventDefault()
  buttons.forEach((b, i) => { b.tabIndex = i === targets[event.key] ? 0 : -1 })
  buttons[targets[event.key]].focus()
}

async function vote(option, type) {
  // aria-disabled statt disabled, damit der Fokus beim Abstimmen nicht verloren geht
  if (votingOptionId.value === option.OptionID || option.UserVote === type) return
  votingOptionId.value = option.OptionID
  try {
    await eventsStore.voteOnPollOption(option.OptionID, type)
  } catch (err) {
    showStatusMessage(err.message || 'Fehler beim Abstimmen', 'error')
  } finally {
    votingOptionId.value = null
  }
}

onMounted(() => {
  modal.value?.open()
})

onUnmounted(() => {
  modal.value?.close()
})
</script>
