<template>
    <header class="AppMenu">
        <!-- Primary Menu -->
        <ul class="primary_menu">
            <li>
                <div class="nav_link" @click="toggleProfileMenu">
                    <div class="nav_icon nav_icon--profile">
                        <img
                            v-if="authStore.user?.ProfileImage"
                            :src="authStore.user.ProfileImage.URL"
                            :alt="`Profilbild von ${authStore.user.FirstName}`"
                            class="profile_image"
                        >
                        <img
                            v-else
                            :src="authStore.user?.Gravatar"
                            alt="Standard Profilbild"
                            class="profile_image"
                        >
                    </div>
                </div>
            </li>

            <li>
                <router-link to="/notices" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Notices' }" @click="closeAllMenus">
                    <div class="nav_icon">
                        <img
                        :src="$route.name === 'Notices' ? nachrichtenTotem : nachrichtenTotemInactive"
                        alt="Nachrichten Icon"
                        class="nav_image"
                        >
                        <p v-if="noticesStore.unreadCount > 0" class="nav_badge">{{ noticesStore.unreadCount }}</p>
                    </div>
                </router-link>
            </li>

            <li>
                <router-link to="/" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Dashboard' }" @click="closeAllMenus">
                    <div class="nav_icon">
                        <img
                        class="nav_image"
                        :src="$route.name === 'Dashboard' ? dashboardTotem : dashboardTotemInactive"
                        alt="ToTeam Logo - Zum Dashboard"
                        >
                    </div>
                </router-link>
            </li>

            <li>
                <router-link to="/calendar" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Calendar' }" @click="closeAllMenus">
                    <div class="nav_icon">
                        <img
                        class="nav_image"
                        :src="$route.name === 'Calendar' ? kalenderTotem : kalenderTotemInactive"
                        alt="Kalender Icon"
                        >
                    </div>
                </router-link>
            </li>

            <li>
                <div class="nav_link" @click="toggleSecondaryMenu">
                    <div class="nav_icon">
                        <div class="nav_button" :class="{ active: isSecondaryMenuOpen }">
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <!-- Secondary Menu -->
        <div class="secondarynav">
            <ul class="secondary_menu">
                <li>
                    <router-link to="/food" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Food' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="essenTotem" alt="Essen Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Essen</p>
                    </router-link>
                </li>

                <li>
                    <router-link to="/links" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Links' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="downloadsTotem" alt="Links Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Links & Downloads</p>
                    </router-link>
                </li>

                <li>
                    <router-link to="/map" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Map' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="kartenTotem" alt="Karte Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Lagepläne</p>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- Profile Menu -->
        <div class="profilenav">
            <p class="version_note"><i>ToTeam Vue</i> <kbd>BETA</kbd></p>
            <div class="nav_profile_wrap">
                <router-link to="/profile" class="nav_profile" @click="closeAllMenus">
                    <div class="nav_icon nav_icon--profile">
                        <img
                            v-if="authStore.user?.ProfileImage"
                            :src="authStore.user.ProfileImage.URL"
                            :alt="`Profilbild von ${authStore.user.FirstName}`"
                            class="profile_image"
                        >
                        <img
                            v-else
                            :src="authStore.user?.Gravatar"
                            alt="Standard Profilbild"
                            class="profile_image"
                        >
                    </div>
                    <div class="nav_text">
                        <p class="nav_title">{{ authStore.user?.FirstName }}</p>
                        <p class="nav_subtitle">Profil ansehen</p>
                    </div>
                </router-link>
                <button @click="handleLogout" class="nav_logout" title="Abmelden">
                    <div class="nav_icon nav_icon--logout">
                        <img :src="actionLogout" alt="Abmelden Icon" class="logout_image">
                    </div>
                </button>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useNoticesStore } from '@stores/notices'

// Import totem icons
import dashboardTotem from '../../../icons/totems/dashboard_totem.png'
import dashboardTotemInactive from '../../../icons/totems/dashboard_totem_inactive.png'
import nachrichtenTotem from '../../../icons/totems/nachrichten_totem.png'
import nachrichtenTotemInactive from '../../../icons/totems/nachrichten_totem_inactive.png'
import kalenderTotem from '../../../icons/totems/kalender_totem.png'
import kalenderTotemInactive from '../../../icons/totems/kalender_totem_inactive.png'
import essenTotem from '../../../icons/totems/essen_totem.png'
import downloadsTotem from '../../../icons/totems/downloads_totem.png'
import kartenTotem from '../../../icons/totems/karten_totem.png'
import actionLogout from '../../../icons/actions/action_logout.svg'

const router = useRouter()
const authStore = useAuthStore()
const noticesStore = useNoticesStore()
const isSecondaryMenuOpen = ref(false)
const isProfileMenuOpen = ref(false)

function toggleSecondaryMenu() {
    // Toggle body class like the original JavaScript does
    document.body.classList.toggle('secnav--open');
    isSecondaryMenuOpen.value = document.body.classList.contains('secnav--open');
    closeProfileMenu(); // Ensure profile menu is closed when opening secondary menu
}

function closeSecondaryMenu() {
    document.body.classList.remove('secnav--open');
    isSecondaryMenuOpen.value = false;
}

function toggleProfileMenu() {
    // Toggle body class like the original JavaScript does
    document.body.classList.toggle('profilenav--open');
    isProfileMenuOpen.value = document.body.classList.contains('profilenav--open');
    closeSecondaryMenu();
}

function closeProfileMenu() {
    document.body.classList.remove('profilenav--open');
    isProfileMenuOpen.value = false;
}

function closeAllMenus() {
    closeSecondaryMenu();
    closeProfileMenu();
}

async function handleLogout() {
    await authStore.logout()
    closeSecondaryMenu()
    closeProfileMenu()
    router.push({ name: 'Login' })
}

onMounted(() => {
    if (authStore.isAuthenticated) {
        // Load unread count for badge - but don't fail if it errors
        noticesStore.fetchNotices().catch(err => {
        console.warn('Could not fetch notices for badge:', err)
        })
    }
})

onUnmounted(() => {
    // Clean up body class when component is destroyed
    closeSecondaryMenu()
    closeProfileMenu()
})
</script>
