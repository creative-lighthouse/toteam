<template>
  <AppModal ref="modal" class="feedback-modal" title="Feedback geben" @close="close">
    <template v-if="!submitted">
      <div class="feedback-type_group">
        <button
          type="button"
          class="feedback-type_option"
          :class="{ 'feedback-type_option--active': type === 'BugReport' }"
          @click="type = 'BugReport'"
        >
          <img :src="iconBug" alt="" class="feedback-type_icon">
          <span>Bug melden</span>
        </button>
        <button
          type="button"
          class="feedback-type_option"
          :class="{ 'feedback-type_option--active': type === 'FeatureRequest' }"
          @click="type = 'FeatureRequest'"
        >
          <img :src="iconFeature" alt="" class="feedback-type_icon">
          <span>Feature wünschen</span>
        </button>
      </div>

      <label class="feedback-field">
        <span class="feedback-field_label">Titel</span>
        <input v-model="title" type="text" class="feedback-field_input" placeholder="Kurze Zusammenfassung" required>
      </label>

      <label class="feedback-field">
        <span class="feedback-field_label">Beschreibung</span>
        <textarea v-model="description" class="feedback-field_textarea" rows="5" placeholder="Was ist passiert bzw. was wünschst du dir?" required></textarea>
      </label>

      <AppToggle v-model="notifyByEmail" label="Per E-Mail über Status-Updates informieren" field-class="feedback-checkbox" />

      <p v-if="error" class="feedback-error">{{ error }}</p>

      <AppButton type="button" variant="primary" :disabled="sending || !canSubmit" @click="submitFeedback">
        {{ sending ? 'Wird gesendet...' : 'Feedback senden' }}
      </AppButton>
    </template>

    <template v-else>
      <div class="feedback-success">
        <p class="feedback-success_title">Danke für dein Feedback!</p>
        <p class="feedback-success_text">Wir kümmern uns darum.</p>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import confetti from 'canvas-confetti'
import { apiPost } from '@utils/api'
import AppModal from '@components/AppModal.vue'
import AppButton from '@components/AppButton.vue'
import AppToggle from '@components/AppToggle.vue'
import iconBug from '../../../icons/feedback_admin.svg'
import iconFeature from '../../../icons/featurerequest.svg'

const modal = ref(null)
const type = ref('BugReport')
const title = ref('')
const description = ref('')
const notifyByEmail = ref(false)
const sending = ref(false)
const submitted = ref(false)
const error = ref('')

const canSubmit = computed(() => title.value.trim() !== '' && description.value.trim() !== '')

function open() {
  type.value = 'BugReport'
  title.value = ''
  description.value = ''
  notifyByEmail.value = false
  sending.value = false
  submitted.value = false
  error.value = ''
  modal.value?.open()
}

function close() {
  modal.value?.close()
}

async function submitFeedback() {
  if (!canSubmit.value || sending.value) return

  sending.value = true
  error.value = ''

  try {
    await apiPost('/feedback/submit', {
      Title: title.value.trim(),
      Description: description.value.trim(),
      Type: type.value,
      URL: window.location.href,
      NotifyByEmail: notifyByEmail.value,
    })

    submitted.value = true
    fireConfetti()
  } catch (err) {
    console.error('Feedback konnte nicht gesendet werden:', err)
    error.value = 'Feedback konnte nicht gesendet werden. Bitte versuche es erneut.'
  } finally {
    sending.value = false
  }
}

function fireConfetti() {
  confetti({
    particleCount: 120,
    spread: 70,
    origin: { y: 0.6 },
    colors: ['#3f567c', '#C7DCFC', '#FDDFB2'],
  })
}

defineExpose({ open, close })
</script>
