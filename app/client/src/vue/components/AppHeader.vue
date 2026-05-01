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
            <div class="action_icon">
                <img :src="actionNotification" alt="Notifications Icon" class="notifications_image"></img>
            </div>
        </div>
    </div>
</template>

<script setup>
    import { ref, onMounted, onUnmounted } from 'vue'
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

    const infoVisible = ref(false)

    function toggleInfo() {
        infoVisible.value = !infoVisible.value
    }

    function closeInfo() {
        infoVisible.value = false
    }

    onMounted(() => document.addEventListener('click', closeInfo))
    onUnmounted(() => document.removeEventListener('click', closeInfo))
</script>
