import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost, apiDelete } from '@utils/api'

/**
 * Feed des Mitteilungs-Totems (FeedPost) — Beiträge als Person oder Organisation,
 * öffentlich oder intern, optional geplant und mit Ablaufdatum. Ersetzt die früheren
 * Mitteilungen; der Store-Name ist geblieben.
 */
export const useAnnouncementsStore = defineStore('announcements', () => {
  // Aktuelle Beiträge, neueste zuerst (eigene geplante inklusive)
  const posts = ref([])
  // Auswahl fürs Eingabeformular: eigene Organisationen bzw. die, für die man posten darf
  const feedOrganizations = ref([])
  const postAsOrganizations = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Abgelaufene Beiträge — erst bei Bedarf geladen
  const archivedPosts = ref([])
  const archiveLoaded = ref(false)
  const archiveLoading = ref(false)
  const archiveError = ref(null)

  async function fetchFeed() {
    try {
      loading.value = !posts.value.length
      error.value = null
      const response = await apiGet('/announcements/feed', false)
      posts.value = response.posts || []
      feedOrganizations.value = response.organizations || []
      postAsOrganizations.value = response.postAsOrganizations || []
    } catch (err) {
      console.error('Failed to fetch feed:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchArchive() {
    try {
      archiveLoading.value = true
      archiveError.value = null
      const response = await apiGet('/announcements/feed?archive=1', false)
      archivedPosts.value = response.posts || []
      archiveLoaded.value = true
    } catch (err) {
      console.error('Failed to fetch feed archive:', err)
      archiveError.value = err.message
    } finally {
      archiveLoading.value = false
    }
  }

  /** Ein Beitrag (Detailseite) — aus den geladenen Listen oder frisch vom Server */
  async function fetchPost(id) {
    const known = posts.value.find(p => p.ID === id) ?? archivedPosts.value.find(p => p.ID === id)
    if (known) return known
    const response = await apiGet(`/announcements/post/${id}`, false)
    if (response?.success === false) throw new Error(response.error || 'Beitrag nicht gefunden')
    return response.post
  }

  /** data: { Content, PostAs, OrganizationID?, Visibility, InternalOrganizationID?, ReleaseDate?, ExpiryDate? } */
  async function createPost(data) {
    const response = await apiPost('/announcements/feedStore', data)
    if (!response?.success) throw new Error(response?.error || 'Beitrag konnte nicht veröffentlicht werden')
    posts.value = [response.data.post, ...posts.value]
      .sort((a, b) => (b.SortDate ?? '').localeCompare(a.SortDate ?? ''))
    return response.data.post
  }

  async function deletePost(id) {
    const response = await apiDelete(`/announcements/feedRemove/${id}`)
    if (!response?.success) throw new Error(response?.error || 'Beitrag konnte nicht gelöscht werden')
    posts.value = posts.value.filter(p => p.ID !== id)
    archivedPosts.value = archivedPosts.value.filter(p => p.ID !== id)
  }

  /** Personen für @-Markierungen: [{ ID, Name, Username, Avatar }] */
  async function searchMentions(query) {
    const response = await apiGet(`/announcements/mentionSearch?q=${encodeURIComponent(query)}`, false)
    return response.members || []
  }

  async function refresh() {
    await fetchFeed()
    if (archiveLoaded.value) await fetchArchive()
  }

  return {
    posts,
    feedOrganizations,
    postAsOrganizations,
    loading,
    error,
    archivedPosts,
    archiveLoaded,
    archiveLoading,
    archiveError,
    fetchFeed,
    fetchArchive,
    fetchPost,
    createPost,
    deletePost,
    searchMentions,
    refresh,
  }
})
