<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="event-modal poll-dialog" @cancel.prevent="$emit('close')">
      <div class="dialog-content" @click.stop>

        <div class="dialog-header">
          <AppOrgLogo
            v-if="event.OrganizationLogoURL"
            :src="event.OrganizationLogoURL"
            alt=""
            :size="32"
          />
          <div class="event_title">
            <h2 class="hl2">{{ event.Title }}</h2>
            <p class="poll-badge" title="Terminfindung">
              <span class="poll-badge_icon" :style="scheduleIconStyle" aria-hidden="true"></span>
            </p>
          </div>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="$emit('close')">✕</AppIconButton>
        </div>

        <div class="dialog-infobox">
          <div v-if="canManageContent" class="event-manage-actions">
            <AppIconButton
              variant="primary"
              :disabled="finalizing"
              aria-label="Terminfindung bearbeiten"
              @click="$emit('edit-poll', event)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </AppIconButton>
            <AppIconButton
              variant="danger"
              :disabled="finalizing"
              aria-label="Terminfindung löschen"
              @click="deletePoll"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
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
              <p class="event-description_text">{{ event.Description }}</p>
            </div>
          </div>

          <div class="poll-options-section">
            <h3 class="event-participation_title">Terminoptionen</h3>

            <div v-for="option in sortedOptions" :key="option.OptionID" class="poll-option">
              <div class="poll-option_header">
                <div class="poll-option_datetime">
                  <strong>{{ option.RenderDate }}</strong>
                  <span class="poll-option_time">{{ option.RenderTime }}</span>
                </div>
                <div class="poll-option_counts">
                  <span class="poll-count poll-count--yes">✓ {{ option.VotedYes }}</span>
                  <span class="poll-count poll-count--maybe">? {{ option.VotedMaybe }}</span>
                  <span class="poll-count poll-count--no">✗ {{ option.VotedNo }}</span>
                </div>
              </div>

              <AppButtonGroup
                :options="participationOptions"
                :model-value="option.UserVote"
                :disabled="votingOptionId === option.OptionID"
                @select="(type) => vote(option, type)"
              />

              <button
                type="button"
                class="poll-option_toggle-participants"
                @click="toggleExpanded(option.OptionID)"
              >{{ expandedOptionId === option.OptionID ? 'Teilnehmer ausblenden' : 'Teilnehmer anzeigen' }}</button>

              <div v-if="expandedOptionId === option.OptionID" class="poll-option_participants">
                <ParticipantCard
                  v-for="p in option.Participations"
                  :key="p.ID"
                  :participation="p"
                />
                <p v-if="!option.Participations?.length" class="event-section-empty">Noch keine Antworten.</p>
              </div>

              <div v-if="canManageContent" class="poll-option_finalize">
                <AppButton
                  variant="primary"
                  size="small"
                  :disabled="finalizing"
                  @click="finalize(option)"
                >Diese Option festlegen</AppButton>
              </div>
            </div>
          </div>

        </div>

        <Transition name="fade">
          <div v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]">
            {{ statusMessage.text }}
          </div>
        </Transition>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import { useEventsStore } from '@stores/events'
import AppButton from '@components/AppButton.vue'
import AppIconButton from '@components/AppIconButton.vue'
import AppButtonGroup from '@components/AppButtonGroup.vue'
import ParticipantCard from '@components/ParticipantCard.vue'
import AppOrgLogo from '@components/AppOrgLogo.vue'
import ScheduleIcon from '../../../icons/actions/action_schedule.svg'

const scheduleIconStyle = {
  maskImage: `url("${ScheduleIcon}")`,
  WebkitMaskImage: `url("${ScheduleIcon}")`,
}

const props = defineProps({
  event: { type: Object, required: true }
})

const emit = defineEmits(['close', 'edit-poll', 'finalized', 'deleted'])

const orgsStore = useOrganizationsStore()
const eventsStore = useEventsStore()
const dialogEl = ref(null)
const statusMessage = ref(null)
const votingOptionId = ref(null)
const finalizing = ref(false)
const expandedOptionId = ref(null)

const participationOptions = [
  { value: 'Decline', label: 'Absagen', tone: 'negative' },
  { value: 'Maybe', label: 'Vielleicht', tone: 'warning' },
  { value: 'Accept', label: 'Zusagen', tone: 'positive' },
]

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

function toggleExpanded(optionId) {
  expandedOptionId.value = expandedOptionId.value === optionId ? null : optionId
}

async function vote(option, type) {
  votingOptionId.value = option.OptionID
  try {
    await eventsStore.voteOnPollOption(option.OptionID, type)
  } catch (err) {
    showStatusMessage(err.message || 'Fehler beim Abstimmen', 'error')
  } finally {
    votingOptionId.value = null
  }
}

async function finalize(option) {
  if (!confirm(`"${option.RenderDate}" als Termin festlegen? Die Terminfindung wird danach gelöscht.`)) return
  finalizing.value = true
  try {
    await eventsStore.finalizePoll(props.event.PollID, option.OptionID)
    emit('finalized')
  } catch (err) {
    showStatusMessage(err.message || 'Fehler beim Festlegen', 'error')
  } finally {
    finalizing.value = false
  }
}

async function deletePoll() {
  if (!confirm('Terminfindung wirklich löschen?')) return
  finalizing.value = true
  try {
    await eventsStore.deleteSchedulingPoll(props.event.PollID)
    emit('deleted')
  } catch (err) {
    showStatusMessage(err.message || 'Fehler beim Löschen', 'error')
    finalizing.value = false
  }
}

onMounted(() => {
  dialogEl.value?.showModal()
})

onUnmounted(() => {
  dialogEl.value?.close()
})
</script>
