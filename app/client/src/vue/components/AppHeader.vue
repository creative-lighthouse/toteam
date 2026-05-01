<template>
    <div class="AppHeader">
        <div class="AppHeader_backbutton">
            <router-link v-if="$route.name !== 'Dashboard'" to="/dashboard" class="back_link">
                <div class="nav_icon nav_icon--back">
                    <img :src="actionBack" alt="Zurück Icon" class="back_image">
                </div>
            </router-link>
        </div>
        <div class="AppHeader_content">
            <h1 class="AppHeader_title">{{ title }}</h1>
            <slot></slot>
        </div>
        <div class="AppHeader_actions">
            <div v-if="description" class="action_icon action_icon--info" @click.stop="toggleInfo">
                <img :src="actionInfo" alt="Info Icon" class="infobutton_image">
                <Transition name="info-popup">
                    <div v-if="infoVisible" class="AppHeader_info_popup" @click.stop>
                        <p>{{ description }}</p>
                    </div>
                </Transition>
            </div>
            <div class="action_icon action_icon--notifications" @click.stop="toggleNotifications">
                <img :src="actionNotification" alt="Notifications Icon" class="notifications_image">
                <span v-if="notificationsStore.unreadCount > 0" class="AppHeader_badge">
                    {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
                </span>
            </div>
        </div>
    </div>

    <Transition name="notifications-backdrop">
        <div v-if="notificationsOpen" class="AppNotifications_backdrop" @click="notificationsOpen = false"></div>
    </Transition>
    <AppNotifications :open="notificationsOpen" @close="notificationsOpen = false" />
</template>

<script setup>
    import { ref, onMounted, onUnmounted } from 'vue'
    import { useNotificationsStore } from '../stores/notifications'
    import AppNotifications from './AppNotifications.vue'
    import actionBack from '../../../icons/actions/action_back.svg'
    import actionInfo from '../../../icons/actions/action_help.svg'
    import actionNotification from '../../../icons/actions/action_notifications.svg'

    defineProps({
        title: {
            type: String,
            required: true
        },
        description: {
            type: String,
            default: ''
        }
    })

    const notificationsStore = useNotificationsStore()
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

    onMounted(() => document.addEventListener('click', closeInfo))
    onUnmounted(() => document.removeEventListener('click', closeInfo))
</script>
