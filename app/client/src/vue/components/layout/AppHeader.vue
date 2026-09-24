<template>
    <div ref="headerEl" class="AppHeader">
        <div class="AppHeader_backbutton">
            <button v-if="$route.name !== 'Dashboard'" type="button" class="back_link" aria-label="Zurück" @click="goBack">
                <span class="nav_icon nav_icon--back">
                    <img :src="actionBack" alt="" class="back_image">
                </span>
            </button>
        </div>
        <div class="AppHeader_content">
            <h1 ref="titleEl" class="AppHeader_title">{{ title }}</h1>
            <slot></slot>
        </div>
        <div class="AppHeader_actions">
            <div v-if="description" class="AppHeader_info">
                <button
                    type="button"
                    class="action_icon action_icon--info"
                    aria-label="Info zu dieser Seite"
                    :aria-expanded="infoVisible"
                    aria-controls="AppHeader_info_popup"
                    @click.stop="toggleInfo"
                >
                    <img :src="actionInfo" alt="" class="infobutton_image">
                </button>
                <Transition name="info-popup">
                    <div v-if="infoVisible" id="AppHeader_info_popup" class="AppHeader_info_popup" role="status" @click.stop>
                        <p>{{ description }}</p>
                    </div>
                </Transition>
            </div>
            <button
                type="button"
                class="action_icon action_icon--notifications"
                :aria-label="notificationsLabel"
                :aria-expanded="notificationsOpen"
                @click.stop="toggleNotifications"
            >
                <img :src="actionNotification" alt="" class="notifications_image">
                <span v-if="notificationsStore.unreadCount > 0" class="AppHeader_badge" aria-hidden="true">
                    {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
                </span>
            </button>
        </div>
    </div>

    <Transition name="notifications-backdrop">
        <div v-if="notificationsOpen" class="AppNotifications_backdrop" @click="notificationsOpen = false"></div>
    </Transition>
    <AppNotifications :open="notificationsOpen" @close="notificationsOpen = false" />
</template>

<script setup>
    import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
    import { useRouter } from 'vue-router'
    import { useNotificationsStore } from '../../stores/notifications'
    import AppNotifications from './AppNotifications.vue'
    import actionBack from '../../../../icons/actions/action_back.svg'
    import actionInfo from '../../../../icons/actions/action_help.svg'
    import actionNotification from '../../../../icons/actions/action_notifications.svg'

    const props = defineProps({
        title: {
            type: String,
            required: true
        },
        description: {
            type: String,
            default: ''
        }
    })

    const router = useRouter()
    const notificationsStore = useNotificationsStore()

    function goBack() {
        if (window.history.length > 1) {
            router.back()
        } else {
            router.push({ name: 'Dashboard' })
        }
    }
    notificationsStore.fetchNotifications()

    const infoVisible = ref(false)
    const notificationsOpen = ref(false)

    function toggleInfo() {
        infoVisible.value = !infoVisible.value
    }

    function toggleNotifications() {
        notificationsOpen.value = !notificationsOpen.value
    }

    function closeInfo() {
        infoVisible.value = false
    }

    const notificationsLabel = computed(() => {
        const count = notificationsStore.unreadCount
        if (count === 1) return 'Benachrichtigungen, 1 ungelesen'
        if (count > 1) return `Benachrichtigungen, ${count} ungelesen`
        return 'Benachrichtigungen'
    })

    function onKeydown(event) {
        if (event.key !== 'Escape') return
        closeInfo()
        notificationsOpen.value = false
    }

    // Titel muss einzeilig bleiben: Schrift bis MIN_TITLE_FONT_SIZE verkleinern,
    // danach per CSS (text-overflow: ellipsis) abschneiden.
    const MIN_TITLE_FONT_SIZE = 12
    const headerEl = ref(null)
    const titleEl = ref(null)
    let resizeObserver = null

    function fitTitle() {
        const el = titleEl.value
        if (!el) return

        el.style.fontSize = ''
        const baseSize = parseFloat(getComputedStyle(el).fontSize)
        let size = baseSize

        // Textbreite skaliert nahezu linear mit der Schriftgröße; ein paar Korrekturschritte fangen Rundungen ab
        for (let i = 0; i < 5 && el.scrollWidth > el.clientWidth && size > MIN_TITLE_FONT_SIZE; i++) {
            size = Math.max(MIN_TITLE_FONT_SIZE, Math.floor(size * el.clientWidth / el.scrollWidth * 10) / 10)
            el.style.fontSize = `${size}px`
        }
    }

    watch(() => props.title, () => nextTick(fitTitle))

    onMounted(() => {
        document.addEventListener('click', closeInfo)
        document.addEventListener('keydown', onKeydown)
        resizeObserver = new ResizeObserver(() => fitTitle())
        resizeObserver.observe(headerEl.value)
        document.fonts?.ready.then(fitTitle)
        fitTitle()
    })
    onUnmounted(() => {
        document.removeEventListener('click', closeInfo)
        document.removeEventListener('keydown', onKeydown)
        resizeObserver?.disconnect()
    })
</script>
