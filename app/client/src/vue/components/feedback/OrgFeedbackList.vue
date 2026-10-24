<template>
  <!-- In der Liste steht nur der Titel; alles andere zeigt das Detail-Modal -->
  <ul class="org-feedback-list">
    <li v-for="item in items" :key="item.ID" class="org-feedback-list_item">
      <button type="button" class="org-feedback-list_title" @click="emit('open', item)">
        {{ item.Title }}
      </button>
      <OrgFeedbackVoteButton
        :feedback="item"
        show-voters
        @update="Object.assign(item, $event)"
        @error="emit('error', $event)"
        @show-voters="showVoters(item)"
      />
    </li>
  </ul>

  <MemberListModal ref="votersModal" />
</template>

<script setup>
import { ref } from 'vue'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import OrgFeedbackVoteButton from '@components/feedback/OrgFeedbackVoteButton.vue'
import MemberListModal from '@components/ui/MemberListModal.vue'
import { feedbackVoters } from '@utils/orgFeedback'

defineProps({
  // Kurzform aus der API (ID, Title, VoteCount, HasVoted, …)
  items: { type: Array, required: true },
})

const emit = defineEmits(['open', 'error'])

const store = useOrgFeedbackStore()
const votersModal = ref(null)
const VOTERS_LIST = { title: '+1 von', empty: 'Noch niemand hat +1 gegeben.' }

// Die Kurzform enthält keine Stimmen — die kommen mit den Details
async function showVoters(item) {
  try {
    await votersModal.value?.open(store.fetchDetail(item.ID).then(feedbackVoters), VOTERS_LIST)
  } catch (e) {
    votersModal.value?.close()
    emit('error', e.message)
  }
}
</script>
