<template>
  <AppModal
    ref="modal"
    :class="`event-modal--${event.Status}`"
    @close="$emit('close')"
  >
    <template #header>
      <EventDialogHeader :event="event" />
    </template>

    <div class="dialog-infobox">
      <EventAdminSection
        :event="event"
        :can-manage-content="canManageContent"
        @edit-appointment="$emit('edit-appointment', event)"
      />

      <EventInfo :event="event" />

      <div v-if="event.ImageURL" class="event-image">
        <img :src="event.ImageURL" :alt="event.Title">
      </div>

      <EventParticipationForm
        :event="event"
        @participation-changed="$emit('participation-changed', ...arguments)"
        @time-changed="$emit('time-changed', ...arguments)"
        @notes-changed="$emit('notes-changed', ...arguments)"
        @ride-changed="$emit('ride-changed', ...arguments)"
        @show-status="({ text, type }) => showStatusMessage(text, type)"
      />

      <EventMealsSection
        :event="event"
        :can-manage-content="canManageContent"
        @food-changed="$emit('food-changed', ...arguments)"
        @show-status="({ text, type }) => showStatusMessage(text, type)"
      />

      <EventAgendaSection
        :event="event"
        :can-manage-content="canManageContent"
        @show-status="({ text, type }) => showStatusMessage(text, type)"
      />

      <EventParticipantsList :event="event" />
    </div>

    <Transition name="fade">
      <div v-if="statusMessage" :class="['status-message', `status-message--${statusMessage.type}`]">
        {{ statusMessage.text }}
      </div>
    </Transition>
  </AppModal>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useOrganizationsStore } from '@stores/organizations'
import AppModal from '@components/AppModal.vue'
import EventDialogHeader from './EventDialogHeader.vue'
import EventInfo from './EventInfo.vue'
import EventParticipationForm from './EventParticipationForm.vue'
import EventMealsSection from './EventMealsSection.vue'
import EventAgendaSection from './EventAgendaSection.vue'
import EventParticipantsList from './EventParticipantsList.vue'
import EventAdminSection from './EventAdminSection.vue'

const props = defineProps({
  event: { type: Object, required: true }
})

const emit = defineEmits(['close', 'participation-changed', 'time-changed', 'notes-changed', 'ride-changed', 'food-changed', 'edit-appointment'])

const orgsStore = useOrganizationsStore()
const modal = ref(null)
const statusMessage = ref(null)

const canManageContent = computed(() => {
  const eventOrgIds = props.event.OrganizationIDs ?? []
  return orgsStore.organizations.some(o =>
    eventOrgIds.includes(o.ID) && o.Permissions?.includes('CALENDAR_MANAGE')
  )
})

function showStatusMessage(text, type = 'success') {
  statusMessage.value = { text, type }
  setTimeout(() => { statusMessage.value = null }, 3000)
}

onMounted(() => {
  modal.value?.open()
})

onUnmounted(() => {
  modal.value?.close()
})
</script>
