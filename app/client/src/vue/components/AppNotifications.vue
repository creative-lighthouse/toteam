<template>
    <Transition name="notifications-panel">
        <aside v-if="open" class="AppNotifications" @click.stop>
            <div class="AppNotifications_header">
                <AppIconButton variant="ghost" class="AppNotifications_close" aria-label="Schließen" @click="$emit('close')">
                    <img :src="actionBack" alt="">
                </AppIconButton>
                <h2 class="AppNotifications_title">Benachrichtigungen</h2>
                <button
                    v-if="store.unreadCount > 0"
                    class="AppNotifications_markall"
                    @click="store.markAllAsRead()"
                >
                    Alle gelesen
                </button>
                <span v-else class="AppNotifications_markall AppNotifications_markall--placeholder"></span>
            </div>

            <div class="AppNotifications_body">
                <div v-if="store.loading" class="AppNotifications_loading">
                    Lade...
                </div>

                <p v-else-if="store.notifications.length === 0" class="AppNotifications_empty">
                    Keine Benachrichtigungen vorhanden.
                </p>

                <ul v-else class="AppNotifications_list">
                    <li
                        v-for="notification in store.notifications"
                        :key="notification.id"
                        class="AppNotifications_item"
                        :class="{
                            'AppNotifications_item--read': notification.isRead,
                            'AppNotifications_item--linked': notification.url
                        }"
                        @click="navigateTo(notification)"
                    >
                        <div class="AppNotifications_item_content">
                            <span class="AppNotifications_item_title">{{ notification.title }}</span>
                            <span class="AppNotifications_item_body">{{ notification.body }}</span>
                            <span class="AppNotifications_item_date">{{ formatDate(notification.created) }}</span>
                        </div>
                        <AppIconButton
                            v-if="!notification.isRead"
                            variant="ghost"
                            class="AppNotifications_item_close"
                            aria-label="Als gelesen markieren"
                            @click.stop="store.markAsRead(notification.id)"
                        >×</AppIconButton>
                    </li>

                    <!-- Infinite scroll sentinel -->
                    <li v-if="store.hasMore" ref="sentinel" class="AppNotifications_sentinel">
                        <span v-if="store.loadingMore" class="AppNotifications_loading_more">Lade...</span>
                    </li>
                </ul>
            </div>
        </aside>
    </Transition>
</template>

<script setup>
    import { ref, watch } from 'vue'
    import { useRouter } from 'vue-router'
    import { useNotificationsStore } from '../stores/notifications'
    import actionBack from '../../../icons/actions/action_back.svg'
    import AppIconButton from '@components/AppIconButton.vue'

    const props = defineProps({
        open: {
            type: Boolean,
            default: false
        }
    })

    const emit = defineEmits(['close'])

    const store = useNotificationsStore()
    const router = useRouter()
    const sentinel = ref(null)
    let observer = null

    function setupObserver() {
        if (observer) observer.disconnect()
        observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                store.loadMore()
            }
        }, { threshold: 0.1 })
    }

    watch(sentinel, (el) => {
        if (el) {
            if (!observer) setupObserver()
            observer.observe(el)
        }
    })

    watch(() => props.open, (isOpen) => {
        if (isOpen) {
            store.fetchNotifications()
            setupObserver()
        } else {
            observer?.disconnect()
        }
    })

    async function navigateTo(notification) {
        if (!notification.isRead) {
            await store.markAsRead(notification.id)
        }
        if (notification.url) {
            // Strip the /app prefix since Vue router already knows it
            const path = notification.url.replace(/^\/app/, '') || '/'
            emit('close')
            router.push(path)
        }
    }

    function formatDate(dateString) {
        if (!dateString) return ''
        const date = new Date(dateString)
        return date.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        })
    }
</script>
