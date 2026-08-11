<template>
  <Teleport to="body">
    <dialog ref="dialogEl" class="feedback-modal" @cancel.prevent="close">
      <div class="feedback-modal_content" @click.stop>
        <div class="feedback-modal_header">
          <h2 class="hl2 feedback-modal_title">Feedback geben</h2>
          <AppIconButton variant="ghost" aria-label="Schließen" @click="close">✕</AppIconButton>
        </div>

        <div class="feedback-modal_body">
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

            <label class="feedback-checkbox">
              <input type="checkbox" v-model="notifyByEmail">
              <span>Per E-Mail über Status-Updates informieren</span>
            </label>

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
        </div>
      </div>
    </dialog>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import confetti from 'canvas-confetti'
import { apiPost } from '@utils/api'
import AppIconButton from '@components/AppIconButton.vue'
import AppButton from '@components/AppButton.vue'
import iconBug from '../../../icons/feedback_admin.svg'
import iconFeature from '../../../icons/featurerequest.svg'

const dialogEl = ref(null)
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
  dialogEl.value?.showModal()
}

function close() {
  dialogEl.value?.close()
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
