import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const NOTIFICATIONS_BASE = '/api/notifications'
const PAGE_SIZE = 20

async function notificationsRequest(endpoint, options = {}) {
    const response = await fetch(`${NOTIFICATIONS_BASE}${endpoint}`, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        },
        credentials: 'same-origin'
    })

    if (!response.ok) {
        throw new Error(`API Error: ${response.status} ${response.statusText}`)
    }

    return response.json()
}

export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref([])
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    const hasMore = ref(false)
    const offset = ref(0)

    const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length)

    async function fetchNotifications() {
        try {
            loading.value = true
            error.value = null
            offset.value = 0

            const data = await notificationsRequest(`/inbox?limit=${PAGE_SIZE}&offset=0`)
            notifications.value = data.notifications || []
            hasMore.value = data.hasMore ?? false
            offset.value = data.notifications?.length ?? 0
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

            const data = await notificationsRequest(`/inbox?limit=${PAGE_SIZE}&offset=${offset.value}`)
            notifications.value.push(...(data.notifications || []))
            hasMore.value = data.hasMore ?? false
            offset.value += data.notifications?.length ?? 0
        } catch (err) {
            console.error('Failed to load more notifications:', err)
        } finally {
            loadingMore.value = false
        }
    }

    async function markAsRead(id) {
        try {
            await notificationsRequest(`/${id}/mark-read`, { method: 'POST' })
            const notification = notifications.value.find(n => n.id === id)
            if (notification) {
                notification.isRead = true
                // Move to end of list (after unread), stable sort
                notifications.value = [
                    ...notifications.value.filter(n => !n.isRead),
                    ...notifications.value.filter(n => n.isRead)
                ]
            }
        } catch (err) {
            console.error('Failed to mark notification as read:', err)
        }
    }

    async function markAllAsRead() {
        try {
            await notificationsRequest('/mark-all-read', { method: 'POST' })
            notifications.value.forEach(n => { n.isRead = true })
        } catch (err) {
            console.error('Failed to mark all notifications as read:', err)
        }
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
        markAllAsRead
    }
})
