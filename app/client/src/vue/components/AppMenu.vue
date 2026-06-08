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
                <router-link to="/announcements" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Announcements' }" @click="closeAllMenus">
                    <div class="nav_icon">
                        <img
                        :src="$route.name === 'Announcements' ? nachrichtenTotem : nachrichtenTotemInactive"
                        alt="Nachrichten Icon"
                        class="nav_image"
                        >
                        <p v-if="announcementsStore.unreadCount > 0" class="nav_badge">{{ announcementsStore.unreadCount }}</p>
                    </div>
                </router-link>
            </li>

            <li>
                <router-link to="/" class="nav_link nav_link--dashboard" :class="{ 'nav_link--active': $route.name === 'Dashboard' }" @click="closeAllMenus">
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
                        <p class="nav_title">Essen <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li>
                    <router-link to="/links" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Links' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="downloadsTotem" alt="Links Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Links & Downloads <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li>
                    <router-link to="/map" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Map' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="kartenTotem" alt="Karte Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Lagepläne <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li>
                    <router-link to="/organizations" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Organizations' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="organizationsTotem" alt="Organisationen Icon" class="nav_image">
                        </div>
                        <p class="nav_title">Organisationen</p>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- Profile Menu -->
        <div class="profilenav">
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
                        <p class="nav_subtitle">Profil ansehen →</p>
                    </div>
                </router-link>
                <button @click="handleLogout" class="nav_logout" title="Abmelden">
                    <div class="nav_icon nav_icon--logout">
                        <img :src="actionLogout" alt="Abmelden Icon" class="logout_image">
                    </div>
                </button>
                <button @click="openSettings" class="nav_settings" title="Einstellungen">
                    <div class="nav_icon nav_icon--settings">
                        <img :src="actionSettings" alt="Einstellungen Icon" class="settings_image">
                    </div>
                </button>
            </div>
            <p class="version_note"><i>ToTeam Vue</i> <kbd>BETA</kbd></p>
        </div>
    </header>

    <SettingsModal ref="settingsModal" />
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useAnnouncementsStore } from '@stores/announcements'
import SettingsModal from '@components/SettingsModal.vue'

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
import organizationsTotem from '../../../icons/totems/organizations_totem.png'
import actionLogout from '../../../icons/actions/action_logout.svg'
import actionSettings from '../../../icons/actions/action_settings.svg'
const router = useRouter()
const authStore = useAuthStore()
const announcementsStore = useAnnouncementsStore()
const isSecondaryMenuOpen = ref(false)
const isProfileMenuOpen = ref(false)
const settingsModal = ref(null)

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

function openSettings() {
    closeAllMenus()
    settingsModal.value?.open()
}

async function handleLogout() {
    await authStore.logout()
    closeSecondaryMenu()
    closeProfileMenu()
    router.push({ name: 'Login' })
}

onMounted(() => {
    // Restore saved theme
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('theme--dark')
    }

    if (authStore.isAuthenticated) {
        announcementsStore.fetchAnnouncements().catch(err => {
            console.warn('Could not fetch announcements for badge:', err)
        })
    }
})

onUnmounted(() => {
    // Clean up body class when component is destroyed
    closeSecondaryMenu()
    closeProfileMenu()
})
</script>
