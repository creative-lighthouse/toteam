import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiGet, apiPost } from '@utils/api'

const PAGE_SIZE = 20

// Die Liste enthält nur ungelesene Benachrichtigungen — als gelesen markierte
// verschwinden sofort (auch serverseitig liefert /inbox nur ungelesene).
export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref([])
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    const hasMore = ref(false)
    // Gesamtzahl ungelesener (auch noch nicht nachgeladener) Benachrichtigungen
    const unreadCount = ref(0)

    function applyPage(data, append) {
        const page = data.notifications || []
        if (append) {
            // Doppelte vermeiden, falls zwischendurch neue dazugekommen sind
            const known = new Set(notifications.value.map(n => n.id))
            notifications.value.push(...page.filter(n => !known.has(n.id)))
        } else {
            notifications.value = page
        }
        hasMore.value = data.hasMore ?? false
        unreadCount.value = data.total ?? notifications.value.length
    }

    async function fetchNotifications() {
        try {
            loading.value = notifications.value.length === 0
            error.value = null

            const data = await apiGet(`/notifications/inbox?limit=${PAGE_SIZE}&offset=0`, false)
            if (!data?.notifications) throw new Error(data?.error || 'Benachrichtigungen konnten nicht geladen werden')
            applyPage(data, false)
        } catch (err) {
            console.error('Failed to fetch notifications:', err)
            error.value = err.message
        } finally {
            loading.value = false
        }
    }

    async function loadMore() {
        if (loadingMore.value || !hasMore.value) return

        try {
            loadingMore.value = true
            // Offset = bereits geladene, weil gelesene serverseitig aus der Liste fallen
            const data = await apiGet(`/notifications/inbox?limit=${PAGE_SIZE}&offset=${notifications.value.length}`, false)
            if (data?.notifications) applyPage(data, true)
        } catch (err) {
            console.error('Failed to load more notifications:', err)
        } finally {
            loadingMore.value = false
        }
    }

    async function markAsRead(id) {
        const index = notifications.value.findIndex(n => n.id === id)
        if (index === -1) return

        // Sofort ausblenden, bei Fehler wieder einfügen
        const [removed] = notifications.value.splice(index, 1)
        unreadCount.value = Math.max(0, unreadCount.value - 1)

        try {
            const response = await apiPost(`/notifications/${id}/mark-read`)
            if (!response?.success) throw new Error(response?.error || 'Fehler')
        } catch (err) {
            console.error('Failed to mark notification as read:', err)
            notifications.value.splice(index, 0, removed)
            unreadCount.value++
            return
        }

        // Liste nachfüllen, damit das Panel nicht leer wirkt, solange es noch weitere gibt
        if (hasMore.value && notifications.value.length < PAGE_SIZE / 2) {
            loadMore()
        }
    }

    async function markAllAsRead() {
        const previous = notifications.value
        const previousCount = unreadCount.value
        const previousHasMore = hasMore.value
        notifications.value = []
        unreadCount.value = 0
        hasMore.value = false

        try {
            const response = await apiPost('/notifications/mark-all-read')
            if (!response?.success) throw new Error(response?.error || 'Fehler')
        } catch (err) {
            console.error('Failed to mark all notifications as read:', err)
            notifications.value = previous
            unreadCount.value = previousCount
            hasMore.value = previousHasMore
        }
    }

    function reset() {
        notifications.value = []
        unreadCount.value = 0
        hasMore.value = false
        error.value = null
    }

    return {
        notifications,
        loading,
        loadingMore,
        error,
        hasMore,
        unreadCount,
        fetchNotifications,
        loadMore,
        markAsRead,
        markAllAsRead,
        reset
    }
})
