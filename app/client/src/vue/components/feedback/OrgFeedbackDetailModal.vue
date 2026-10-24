<template>
  <AppModal
    ref="modal"
    class="org-feedback-detail-modal"
    :title="feedback?.Title || 'Feedback'"
    :menu="menuItems"
    @close="close"
  >
    <div v-if="loading && !feedback" class="org-feedback-detail-modal_loading">Lädt…</div>
    <div v-else-if="loadError" class="app-modal_error">{{ loadError }}</div>

    <template v-else-if="feedback">
      <div class="org-feedback-detail-modal_meta">
        <span class="org-feedback-detail-modal_sentiment" :class="`org-feedback-detail-modal_sentiment--${feedback.Sentiment.toLowerCase()}`">
          {{ SENTIMENT_LABELS[feedback.Sentiment] }}
        </span>
        <span v-if="feedback.Event" class="org-feedback-detail-modal_context">
          Event:
          <router-link :to="{ name: 'EventDetail', params: { segment: feedback.Event.URLSegment } }" @click="close">{{ feedback.Event.Title }}</router-link>
        </span>
        <span v-else-if="feedback.Organization" class="org-feedback-detail-modal_context">
          Allgemein · {{ feedback.Organization.Title }}
        </span>
        <AppIconButton
          v-if="feedback.CanEdit"
          variant="primary"
          class="org-feedback-detail-modal_edit"
          aria-label="Feedback bearbeiten"
          title="Bearbeiten"
          :disabled="busy"
          @click="emit('edit', feedback)"
        >
          <span class="icon-mask" :style="editIconStyle" aria-hidden="true" />
        </AppIconButton>
      </div>

      <div class="org-feedback-detail-modal_author">
        <AppAvatar
          :src="feedback.Author?.Avatar"
          :alt="feedback.Author?.Name || ''"
          :name="feedback.Author?.Name || '?'"
          img-class="org-feedback-detail-modal_avatar"
        />
        <div>
          <p class="org-feedback-detail-modal_author-name">{{ feedback.Author?.Name || 'Unbekannt' }}</p>
          <p class="org-feedback-detail-modal_date">
            <time :datetime="feedback.Created">{{ formatFeedbackDate(feedback.Created) }}</time>
          </p>
        </div>
        <!-- +1 rechts in der Autorenzeile; die Zahl öffnet, wer wann +1 gegeben hat -->
        <OrgFeedbackVoteButton
          class="org-feedback-detail-modal_votes"
          :feedback="feedback"
          show-voters
          @update="onVote"
          @error="actionError = $event"
          @show-voters="votersModal?.open(feedbackVoters(feedback), { title: '+1 von', empty: 'Noch niemand hat +1 gegeben.' })"
        />
      </div>

      <!-- Bereinigt vom Server (Allowlist ohne Attribute) -->
      <div v-if="feedback.Content" class="org-feedback-detail-modal_content" v-html="feedback.Content"></div>
      <p v-else class="org-feedback-detail-modal_empty">Kein weiterer Text.</p>


      <div v-if="feedback.LinkedTask" class="org-feedback-detail-modal_task">
        Aufgabe:
        <router-link :to="{ name: 'TaskDetail', params: { hash: feedback.LinkedTask.Hash } }" @click="close">
          {{ feedback.LinkedTask.Title }}
        </router-link>
      </div>

      <section class="org-feedback-detail-modal_comments" aria-label="Kommentare">
        <h3 class="hl3">Kommentare<span v-if="feedback.Comments.length"> ({{ feedback.Comments.length }})</span></h3>
        <ul v-if="feedback.Comments.length" class="org-feedback-detail-modal_comment-list">
          <li v-for="comment in feedback.Comments" :key="comment.ID" class="org-feedback-detail-modal_comment">
            <AppAvatar
              :src="comment.Author?.Avatar"
              :alt="comment.Author?.Name || ''"
              :name="comment.Author?.Name || '?'"
              img-class="org-feedback-detail-modal_comment-avatar"
            />
            <div class="org-feedback-detail-modal_comment-body">
              <p class="org-feedback-detail-modal_comment-head">
                <strong>{{ comment.Author?.Name || 'Unbekannt' }}</strong>
                <time :datetime="comment.Created">{{ formatFeedbackDate(comment.Created) }}</time>
              </p>
              <AppLinkifiedText :text="comment.Content" class="org-feedback-detail-modal_comment-text" />
            </div>
            <div class="org-feedback-detail-modal_comment-actions">
              <button
                type="button"
                class="org-feedback-detail-modal_comment-like"
                :class="{ 'org-feedback-detail-modal_comment-like--active': comment.HasLiked }"
                :aria-pressed="!!comment.HasLiked"
                :aria-label="comment.HasLiked ? 'Daumen hoch zurücknehmen' : 'Daumen hoch geben'"
                :title="likeTitle(comment)"
                :disabled="likeBusy.has(comment.ID)"
                @click="toggleCommentLike(comment)"
              >
                <span class="icon-mask" :style="likeIconStyle" aria-hidden="true" />
                <span v-if="comment.LikeCount" class="org-feedback-detail-modal_comment-like-count">{{ comment.LikeCount }}</span>
              </button>
              <AppIconButton
                v-if="comment.CanDelete"
                variant="ghost"
                aria-label="Kommentar löschen"
                title="Kommentar löschen"
                :disabled="busy"
                @click="deleteComment(comment)"
              >✕</AppIconButton>
            </div>
          </li>
        </ul>
        <p v-else class="org-feedback-detail-modal_empty">Noch keine Kommentare.</p>

        <form class="org-feedback-detail-modal_comment-form" @submit.prevent="submitComment">
          <textarea
            ref="commentInput"
            v-model="commentText"
            class="input org-feedback-detail-modal_comment-input"
            rows="1"
            maxlength="5000"
            placeholder="Kommentar schreiben…"
            aria-label="Kommentar"
            @keydown.enter="onCommentEnter"
          ></textarea>
          <AppIconButton
            variant="primary"
            class="org-feedback-detail-modal_comment-send"
            aria-label="Kommentar senden"
            title="Senden (Enter)"
            :disabled="busy || !commentText.trim()"
            @click="submitComment"
          >
            <span class="icon-mask" :style="sendIconStyle" aria-hidden="true" />
          </AppIconButton>
        </form>
      </section>

      <p v-if="actionError" class="app-modal_error">{{ actionError }}</p>
    </template>

  </AppModal>

  <MemberListModal ref="votersModal" />
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useOrgFeedbackStore } from '@stores/orgFeedback'
import { SENTIMENT_LABELS, formatFeedbackDate, feedbackVoters } from '@utils/orgFeedback'
import AppModal from '@components/ui/AppModal.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppLinkifiedText from '@components/ui/AppLinkifiedText.vue'
import OrgFeedbackVoteButton from '@components/feedback/OrgFeedbackVoteButton.vue'
import MemberListModal from '@components/ui/MemberListModal.vue'
import actionEdit from '../../../../icons/actions/action_edit.svg'
import actionSend from '../../../../icons/actions/action_send.svg'
import actionLike from '../../../../icons/actions/action_like.svg'

const editIconStyle = { maskImage: `url("${actionEdit}")`, WebkitMaskImage: `url("${actionEdit}")` }
const sendIconStyle = { maskImage: `url("${actionSend}")`, WebkitMaskImage: `url("${actionSend}")` }
const likeIconStyle = { maskImage: `url("${actionLike}")`, WebkitMaskImage: `url("${actionLike}")` }

// edit: Feedback bearbeiten (Formular öffnet der Aufrufer); changed: +1, Kommentare,
// Aufgabe — Kurzform für Listen außerhalb des Stores; deleted: ID; closed: Modal zu
const emit = defineEmits(['edit', 'changed', 'deleted', 'closed'])
const store = useOrgFeedbackStore()

const modal = ref(null)
const feedback = ref(null)
const loading = ref(false)
const loadError = ref(null)
const actionError = ref(null)
const busy = ref(false)
const commentText = ref('')
const commentInput = ref(null)
const votersModal = ref(null)

// Kommentarfeld startet einzeilig und wächst mit dem Text (bis max-height im SCSS, dann scrollt es)
function fitCommentInput() {
  const el = commentInput.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${el.scrollHeight + el.offsetHeight - el.clientHeight}px`
}
watch(commentText, () => nextTick(fitCommentInput))

// Seltene Aktionen im Drei-Punkte-Menü oben rechts
const menuItems = computed(() => [
  feedback.value?.CanConvertToTask && !feedback.value.LinkedTask && !busy.value
    && { label: 'Zur Aufgabe machen', onClick: convertToTask },
  feedback.value?.CanDelete && !busy.value
    && { label: 'Feedback löschen', onClick: deleteFeedback, danger: true },
])

/** Öffnet das Feedback (ID oder Objekt mit ID) und lädt die Details */
async function open(idOrFeedback) {
  const id = typeof idOrFeedback === 'object' ? idOrFeedback.ID : Number(idOrFeedback)
  feedback.value = null
  loadError.value = null
  actionError.value = null
  commentText.value = ''
  modal.value?.open()
  await load(id)
}

async function load(id) {
  loading.value = true
  try {
    feedback.value = await store.fetchDetail(id)
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

/** Nach dem Bearbeiten den neuen Stand übernehmen */
function setFeedback(updated) {
  feedback.value = updated
}

function close() {
  modal.value?.close()
  emit('closed')
}

function emitChanged() {
  const { ID, VoteCount, HasVoted, CommentCount } = feedback.value
  emit('changed', { ID, VoteCount, HasVoted, CommentCount })
}

async function onVote(result) {
  Object.assign(feedback.value, result)
  emitChanged()
  // Wer +1 gegeben hat, kommt nur mit den Details
  try {
    const fresh = await store.fetchDetail(feedback.value.ID)
    feedback.value.Voters = fresh.Voters
  } catch {
    // Zähler stimmt trotzdem
  }
}

async function run(action) {
  busy.value = true
  actionError.value = null
  try {
    await action()
  } catch (e) {
    actionError.value = e.message
  } finally {
    busy.value = false
  }
}

// Wie im Chat: Enter sendet, Umschalt+Enter macht eine neue Zeile
// (nicht während einer Eingabemethode wie bei japanischer Tastatur noch Zeichen zusammensetzt)
function onCommentEnter(event) {
  if (event.shiftKey || event.isComposing) return
  event.preventDefault()
  submitComment()
}

// ── Daumen hoch auf Kommentaren (erneuter Klick nimmt ihn zurück) ─────────────
const likeBusy = ref(new Set())

function likeTitle(comment) {
  const names = comment.Likers?.length ? `\n${comment.Likers.join(', ')}` : ''
  return (comment.HasLiked ? 'Daumen hoch zurücknehmen' : 'Daumen hoch') + names
}

async function toggleCommentLike(comment) {
  if (likeBusy.value.has(comment.ID)) return
  likeBusy.value.add(comment.ID)
  actionError.value = null
  try {
    const updated = await store.setCommentLike(comment.ID, !comment.HasLiked)
    Object.assign(comment, { LikeCount: updated.LikeCount, HasLiked: updated.HasLiked, Likers: updated.Likers })
  } catch (e) {
    actionError.value = e.message
  } finally {
    likeBusy.value.delete(comment.ID)
  }
}

function submitComment() {
  const text = commentText.value.trim()
  if (!text || busy.value) return
  run(async () => {
    const comment = await store.addComment(feedback.value.ID, text)
    feedback.value.Comments.push(comment)
    feedback.value.CommentCount = feedback.value.Comments.length
    commentText.value = ''
    emitChanged()
  })
}

function deleteComment(comment) {
  if (!confirm('Kommentar wirklich löschen?')) return
  run(async () => {
    await store.removeComment(feedback.value.ID, comment.ID)
    feedback.value.Comments = feedback.value.Comments.filter(c => c.ID !== comment.ID)
    feedback.value.CommentCount = feedback.value.Comments.length
    emitChanged()
  })
}

function convertToTask() {
  if (!confirm(`Aus „${feedback.value.Title}“ eine Aufgabe erstellen? Du wirst als verantwortlich eingetragen.`)) return
  run(async () => {
    const result = await store.createTask(feedback.value.ID)
    feedback.value = result.feedback
  })
}

function deleteFeedback() {
  if (!confirm(`Feedback „${feedback.value.Title}“ wirklich löschen? Auch alle +1 und Kommentare gehen verloren.`)) return
  run(async () => {
    const id = feedback.value.ID
    await store.remove(id)
    emit('deleted', id)
    close()
  })
}

defineExpose({ open, close, setFeedback })
</script>
