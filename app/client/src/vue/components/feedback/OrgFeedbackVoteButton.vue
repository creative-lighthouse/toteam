<template>
  <!-- "+1": sich dem Feedback anschließen — ein erneuter Klick nimmt das +1 zurück -->
  <AppCountButton
    class="org-feedback-vote-button"
    label="+1"
    :count="feedback.VoteCount ?? 0"
    count-label="Personen schließen sich an"
    :active="!!feedback.HasVoted"
    :disabled="busy"
    :title="feedback.HasVoted ? 'Dein +1 zurücknehmen' : 'Diesem Feedback anschließen'"
    :count-clickable="showVoters"
    count-title="Wer hat +1 gegeben?"
    @click.stop="toggle"
    @count-click.stop="emit('show-voters')"
  />
</template>

<script setup>
import { ref } from 'vue'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import AppCountButton from '@components/ui/AppCountButton.vue'

const props = defineProps({
  // Feedback mit ID, VoteCount, HasVoted
  feedback: { type: Object, required: true },
  // Zahl anklickbar machen (meldet show-voters), z.B. im Detail-Modal
  showVoters: { type: Boolean, default: false },
})

// update: neuer Stand { VoteCount, HasVoted }; show-voters: Klick auf die Zahl
const emit = defineEmits(['update', 'error', 'show-voters'])

const store = useOrgFeedbackStore()
const busy = ref(false)

async function toggle() {
  if (busy.value) return
  busy.value = true
  try {
    emit('update', await store.setVote(props.feedback.ID, !props.feedback.HasVoted))
  } catch (e) {
    emit('error', e.message)
  } finally {
    busy.value = false
  }
}
</script>
