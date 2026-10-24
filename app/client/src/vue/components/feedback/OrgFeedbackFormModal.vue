<template>
  <AppModal ref="modal" class="org-feedback-form-modal" :title="editId ? 'Feedback bearbeiten' : 'Feedback geben'" @close="close">
    <form id="org-feedback-form" class="modalform" @submit.prevent="submit">

      <div class="field">
        <span id="org-feedback-sentiment-label">Art *</span>
        <AppButtonGroup
          :options="SENTIMENT_OPTIONS"
          :model-value="form.Sentiment"
          label="Art des Feedbacks"
          @select="form.Sentiment = $event"
        />
      </div>

      <!-- Schrittweise: Art → Organisation → Wozu → Rest (beim Bearbeiten alles auf einmal) -->
      <div v-if="showOrgStep && !fixedEvent && store.organizations.length > 1" class="field org-feedback-form-modal_step">
        <label>Organisation *</label>
        <OrganizationPicker v-model="form.OrganizationID" :orgs="store.organizations" />
      </div>

      <template v-if="showEventStep">
        <p v-if="fixedEvent" class="field org-feedback-form-modal_hint org-feedback-form-modal_step">
          Zum Event <strong>{{ fixedEvent.Title }}</strong>
        </p>
        <label v-else class="field org-feedback-form-modal_step">
          Wozu? *
          <select v-model="form.EventID" class="input">
            <option :value="null" disabled>Bitte auswählen…</option>
            <option :value="0">Allgemein zur Organisation</option>
            <option v-for="ev in orgEvents" :key="ev.ID" :value="ev.ID">{{ ev.Title }}</option>
          </select>
        </label>
      </template>

      <template v-if="showDetails">
        <label class="field org-feedback-form-modal_step">
          Titel *
          <input
            v-model="form.Title"
            type="text"
            maxlength="255"
            placeholder="z.B. Mehr Pausen bei der Generalprobe"
            required
            @input="scheduleSimilar"
          />
        </label>

        <div v-if="similar.length" class="field org-feedback-form-modal_similar" role="status">
          <p class="org-feedback-form-modal_similar-title">Ähnliches Feedback gibt es schon – vielleicht reicht ein +1?</p>
          <ul class="org-feedback-form-modal_similar-list">
            <li v-for="item in similar" :key="item.ID" class="org-feedback-form-modal_similar-item">
              <button type="button" class="org-feedback-form-modal_similar-link" @click="emit('open', item)">
                {{ item.Title }}
                <span v-if="item.Event" class="org-feedback-form-modal_similar-event">· {{ item.Event.Title }}</span>
              </button>
              <OrgFeedbackVoteButton :feedback="item" @update="Object.assign(item, $event)" @error="error = $event" />
            </li>
          </ul>
        </div>

        <div class="field org-feedback-form-modal_step">
          <span>Text</span>
          <AppRichTextEditor v-model="form.Content" label="Text" placeholder="Was ist dir aufgefallen?" />
        </div>
      </template>

      <div v-if="error" class="field app-modal_error">{{ error }}</div>
    </form>

    <template #actions>
      <AppButton variant="secondary" :disabled="saving" @click="close">Abbrechen</AppButton>
      <AppButton type="submit" form="org-feedback-form" variant="primary" :disabled="!canSubmit">
        {{ saving ? 'Speichern…' : (editId ? 'Speichern' : 'Absenden') }}
      </AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import { SENTIMENT_OPTIONS } from '@utils/orgFeedback'
import AppModal from '@components/ui/AppModal.vue'
import AppButton from '@components/ui/AppButton.vue'
import AppButtonGroup from '@components/ui/AppButtonGroup.vue'
import AppRichTextEditor from '@components/ui/AppRichTextEditor.vue'
import OrganizationPicker from '@components/ui/OrganizationPicker.vue'
import OrgFeedbackVoteButton from '@components/feedback/OrgFeedbackVoteButton.vue'

// saved: das gespeicherte Feedback (Detailform); open: ähnliches Feedback öffnen
const emit = defineEmits(['saved', 'open'])
const store = useOrgFeedbackStore()

const modal = ref(null)
const saving = ref(false)
const error = ref(null)
const editId = ref(null)
// Von der Event-Seite: Event steht fest ({ ID, Title, OrganizationID })
const fixedEvent = ref(null)
const similar = ref([])

const defaultForm = () => ({
  Sentiment: null,
  Title: '',
  Content: '',
  OrganizationID: store.organizations.length === 1 ? store.organizations[0].ID : null,
  // null = noch nicht gewählt ("Bitte auswählen…"), 0 = allgemein zur Organisation
  EventID: null,
})

const form = reactive(defaultForm())

const orgEvents = computed(() => store.events.filter(e => e.OrganizationID === Number(form.OrganizationID)))

// Die Schritte bauen aufeinander auf; beim Bearbeiten ist schon alles gewählt
const showOrgStep = computed(() => !!form.Sentiment)
const showEventStep = computed(() => showOrgStep.value && !!form.OrganizationID)
const showDetails = computed(() => showEventStep.value && form.EventID !== null)

const canSubmit = computed(() => !saving.value && showDetails.value && !!form.Title.trim())

// Event einer anderen Organisation passt nach dem Wechsel nicht mehr
watch(() => form.OrganizationID, () => {
  if (!fixedEvent.value && form.EventID && !orgEvents.value.some(e => e.ID === form.EventID)) {
    form.EventID = null
  }
  scheduleSimilar()
})
watch(() => form.EventID, () => scheduleSimilar())

/**
 * Öffnet das leere Formular. Mit `event` ({ ID, Title, OrganizationID }) ist das Event
 * fest vorgegeben (Event-Seite), sonst gelten `eventId`/`organizationId` als Vorauswahl.
 */
function open({ event = null, eventId = 0, organizationId = 0 } = {}) {
  editId.value = null
  fixedEvent.value = event
  Object.assign(form, defaultForm())
  if (event) {
    form.OrganizationID = event.OrganizationID
    form.EventID = event.ID
  } else {
    if (organizationId) form.OrganizationID = organizationId
    if (eventId) {
      const ev = store.events.find(e => e.ID === eventId)
      if (ev) {
        form.OrganizationID = ev.OrganizationID
        form.EventID = ev.ID
      }
    }
  }
  reset()
  modal.value?.open()
}

/** Zum Bearbeiten (Detailform des Feedbacks) */
function openForEdit(feedback) {
  editId.value = feedback.ID
  fixedEvent.value = null
  Object.assign(form, {
    Sentiment: feedback.Sentiment,
    Title: feedback.Title,
    Content: feedback.Content || '',
    OrganizationID: feedback.Organization?.ID || 0,
    EventID: feedback.Event?.ID || 0,
  })
  reset()
  modal.value?.open()
}

function reset() {
  error.value = null
  similar.value = []
  clearTimeout(similarTimer)
}

function close() {
  clearTimeout(similarTimer)
  modal.value?.close()
}

// ── Ähnliche Titel ────────────────────────────────────────────────────────────
let similarTimer = null
let similarRequest = 0

function scheduleSimilar() {
  clearTimeout(similarTimer)
  similarTimer = setTimeout(loadSimilar, 400)
}

async function loadSimilar() {
  const title = form.Title.trim()
  if (title.length < 3 || !form.OrganizationID) {
    similar.value = []
    return
  }
  const request = ++similarRequest
  try {
    const result = await store.findSimilar({
      title,
      organization: form.OrganizationID,
      event: form.EventID || null,
      exclude: editId.value,
    })
    // Nur die Antwort auf die letzte Anfrage zählt
    if (request === similarRequest) similar.value = result
  } catch {
    // Ohne Hinweis geht es auch
  }
}

async function submit() {
  if (!canSubmit.value) return
  saving.value = true
  error.value = null
  const data = {
    Sentiment: form.Sentiment,
    Title: form.Title.trim(),
    Content: form.Content,
    OrganizationID: Number(form.OrganizationID),
    EventID: Number(form.EventID) || 0,
  }
  try {
    const feedback = editId.value ? await store.update(editId.value, data) : await store.create(data)
    emit('saved', feedback)
    close()
  } catch (err) {
    error.value = err.message || 'Unbekannter Fehler.'
  } finally {
    saving.value = false
  }
}

defineExpose({ open, openForEdit, close })
</script>
