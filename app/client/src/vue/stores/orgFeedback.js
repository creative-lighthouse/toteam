import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '@utils/api'

// Feedback-Totem (api/v1/orgfeedback) — nicht zu verwechseln mit dem Feedback an die
// Entwickler (FeedbackModal, api/v1/feedback)
export const useOrgFeedbackStore = defineStore('orgFeedback', () => {
  // Kurzform für die Liste: ID, Title, Sentiment, VoteCount, HasVoted, CommentCount, Event, Organization
  const items = ref([])
  // Organisationen mit aktivem Feedback-Totem und deren Events (für Filter und Formular)
  const organizations = ref([])
  const events = ref([])
  const loading = ref(false)
  const error = ref(null)

  /** Wirft bei { success: false } mit der Meldung des Servers */
  function unwrap(response, fallback) {
    if (!response?.success) throw new Error(response?.error || fallback)
    return response.data
  }

  async function fetchList({ organization = null, event = null, sort = 'votes' } = {}) {
    loading.value = items.value.length === 0
    error.value = null
    try {
      const params = new URLSearchParams()
      if (organization) params.set('organization', organization)
      if (event) params.set('event', event)
      if (sort) params.set('sort', sort)
      const response = await apiGet(`/orgfeedback?${params}`, false)
      items.value = response.feedback || []
      organizations.value = response.organizations || []
      events.value = response.events || []
    } catch (err) {
      console.error('Failed to fetch feedback:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchDetail(id) {
    return unwrap(await apiGet(`/orgfeedback/detail/${id}`, false), 'Feedback nicht gefunden').feedback
  }

  /** Übernimmt geänderte Felder in die Liste (falls das Feedback dort steht) */
  function patchItem(id, fields) {
    const item = items.value.find(i => i.ID === id)
    if (item) Object.assign(item, fields)
  }

  function summaryOf(feedback) {
    const { ID, Title, Sentiment, Created, VoteCount, HasVoted, CommentCount, Event, Organization } = feedback
    return { ID, Title, Sentiment, Created, VoteCount, HasVoted, CommentCount, Event, Organization }
  }

  async function create(data) {
    const feedback = unwrap(await apiPost('/orgfeedback/store', data), 'Fehler beim Speichern').feedback
    items.value = [summaryOf(feedback), ...items.value]
    return feedback
  }

  async function update(id, data) {
    const feedback = unwrap(await apiPut(`/orgfeedback/update/${id}`, data), 'Fehler beim Speichern').feedback
    patchItem(id, summaryOf(feedback))
    return feedback
  }

  async function remove(id) {
    unwrap(await apiDelete(`/orgfeedback/remove/${id}`), 'Fehler beim Löschen')
    items.value = items.value.filter(i => i.ID !== id)
  }

  /**
   * "+1" umschalten: ein erneuter Klick nimmt es zurück. `vote` ist der gewünschte
   * neue Zustand, damit schnelle Doppelklicks nicht durcheinanderkommen.
   * @returns {{ VoteCount: number, HasVoted: boolean }}
   */
  async function setVote(id, vote) {
    const result = unwrap(await apiPost(`/orgfeedback/vote/${id}`, { Vote: vote }), 'Fehler beim Abstimmen')
    patchItem(id, result)
    return result
  }

  async function addComment(id, content) {
    const { comment } = unwrap(await apiPost(`/orgfeedback/comment/${id}`, { Content: content }), 'Fehler beim Kommentieren')
    const item = items.value.find(i => i.ID === id)
    if (item) item.CommentCount++
    return comment
  }

  async function removeComment(feedbackId, commentId) {
    unwrap(await apiDelete(`/orgfeedback/commentRemove/${commentId}`), 'Fehler beim Löschen')
    const item = items.value.find(i => i.ID === feedbackId)
    if (item) item.CommentCount = Math.max(0, item.CommentCount - 1)
  }

  /** Daumen hoch zu einem Kommentar setzen/zurücknehmen — liefert den aktualisierten Kommentar */
  async function setCommentLike(commentId, like) {
    const data = unwrap(await apiPost(`/orgfeedback/commentLike/${commentId}`, { Like: like }), 'Fehler beim Speichern')
    return data.comment
  }

  /** Feedback mit ähnlichem Titel in derselben Organisation */
  async function findSimilar({ title, organization, event = null, exclude = null }) {
    const params = new URLSearchParams({ title, organization })
    if (event) params.set('event', event)
    if (exclude) params.set('exclude', exclude)
    const response = await apiGet(`/orgfeedback/similar?${params}`, false)
    return response?.success ? response.data.similar : []
  }

  /** Macht aus dem Feedback eine Aufgabe — gibt { task, feedback } zurück */
  async function createTask(id, title) {
    return unwrap(await apiPost(`/orgfeedback/createTask/${id}`, { Title: title }), 'Fehler beim Erstellen der Aufgabe')
  }

  /** Feedback eines Events (Event-Seite) — { feedback, CanCreate } */
  async function fetchEventFeedback(eventId) {
    return unwrap(await apiGet(`/orgfeedback/eventFeedback/${eventId}`, false), 'Feedback konnte nicht geladen werden')
  }

  return {
    items,
    organizations,
    events,
    loading,
    error,
    fetchList,
    fetchDetail,
    create,
    update,
    remove,
    setVote,
    addComment,
    removeComment,
    setCommentLike,
    findSimilar,
    createTask,
    fetchEventFeedback,
  }
})
