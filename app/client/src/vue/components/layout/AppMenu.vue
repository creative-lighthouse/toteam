<template>
    <!-- Steht im DOM nach dem Seiteninhalt (siehe App.vue), damit man per Tab zuerst im Inhalt landet -->
    <nav class="AppMenu" :class="uiStore.navStyle" aria-label="Hauptnavigation" @keydown.esc="onEscape" @keydown="onMenuKeydown" @focusout="onFocusOut">
        <!-- Primary Menu -->
        <ul class="primary_menu">
            <li>
                <button
                    ref="profileToggle"
                    type="button"
                    class="nav_link nav_toggle"
                    aria-label="Profilmenü"
                    :aria-expanded="isProfileMenuOpen"
                    aria-controls="app-menu-profile"
                    @click="toggleProfileMenu"
                >
                    <div class="nav_icon nav_icon--profile">
                        <AppAvatar
                            :src="authStore.user?.Avatar"
                            alt=""
                            img-class="profile_image"
                        />
                    </div>
                </button>
            </li>

            <li v-if="authStore.hasTotem('announcements')">
                <router-link to="/announcements" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Announcements' }" data-primary-item="announcements" :tabindex="primaryTabindex('announcements')" @click="closeAllMenus" @focus="onPrimaryFocus('announcements')">
                    <div class="nav_icon">
                        <img
                        :src="$route.name === 'Announcements' ? nachrichtenTotem : nachrichtenTotemInactive"
                        alt="Nachrichten"
                        class="nav_image"
                        >
                    </div>
                </router-link>
            </li>

            <li>
                <router-link to="/" class="nav_link nav_link--dashboard" :class="{ 'nav_link--active': $route.name === 'Dashboard' }" data-primary-item="dashboard" :tabindex="primaryTabindex('dashboard')" @click="closeAllMenus" @focus="onPrimaryFocus('dashboard')">
                    <div class="nav_icon">
                        <img
                        class="nav_image"
                        :src="$route.name === 'Dashboard' ? dashboardTotem : dashboardTotemInactive"
                        alt="Dashboard"
                        >
                    </div>
                </router-link>
            </li>

            <li v-if="authStore.hasTotem('calendar')">
                <router-link to="/calendar" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Calendar' }" data-primary-item="calendar" :tabindex="primaryTabindex('calendar')" @click="closeAllMenus" @focus="onPrimaryFocus('calendar')">
                    <div class="nav_icon">
                        <img
                        class="nav_image"
                        :src="$route.name === 'Calendar' ? kalenderTotem : kalenderTotemInactive"
                        alt="Kalender"
                        >
                    </div>
                </router-link>
            </li>

            <li>
                <button
                    ref="secondaryToggle"
                    type="button"
                    class="nav_link nav_toggle"
                    aria-label="Weitere Bereiche"
                    :aria-expanded="isSecondaryMenuOpen"
                    aria-controls="app-menu-secondary"
                    data-primary-item="more"
                    :tabindex="primaryTabindex('more')"
                    @click="toggleSecondaryMenu"
                    @focus="onPrimaryFocus('more')"
                >
                    <div class="nav_icon">
                        <div class="nav_button" :class="{ active: isSecondaryMenuOpen }" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </button>
            </li>
        </ul>

        <!-- Secondary Menu -->
        <!-- Geschlossen: inert, damit Tab und Screenreader die ausgeblendeten Links überspringen -->
        <div id="app-menu-secondary" ref="secondaryMenu" class="secondarynav" :inert="!isSecondaryMenuOpen">
            <ul class="secondary_menu">
                <li v-if="authStore.hasTotem('calendar')">
                    <router-link data-secondary-item to="/events" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Events' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="kalenderTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Events <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('food')">
                    <router-link data-secondary-item to="/food" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Food' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="essenTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Essen <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('links')">
                    <router-link data-secondary-item to="/links" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Links' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="downloadsTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Links & Downloads</p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('map')">
                    <router-link data-secondary-item to="/map" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Map' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="kartenTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Lagepläne <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('tasks')">
                    <router-link data-secondary-item to="/tasks" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Tasks' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="todosTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Aufgaben <span class="nav_alpha">Beta</span></p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('skript')">
                    <router-link data-secondary-item to="/skript" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Skript' || $route.name === 'SkriptDetail' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="skriptTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Skript</p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('marketing')">
                    <router-link data-secondary-item to="/marketing" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Marketing' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="marketingTotem" alt="" class="nav_image">
                        </div>
                        <p class="nav_title">Marketing <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li v-if="authStore.hasTotem('inventory')">
                    <router-link data-secondary-item to="/inventory" :aria-label="pendingRentals ? `Inventar, ${pendingRentals === 1 ? '1 offener Antrag' : `${pendingRentals} offene Anträge`}` : undefined" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Inventory' || $route.name === 'InventoryRentals' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="inventarTotem" alt="" class="nav_image">
                            <span v-if="pendingRentals" class="nav_badge" aria-hidden="true">{{ formatBadge(pendingRentals) }}</span>
                        </div>
                        <p class="nav_title">Inventar <span class="nav_alpha">Alpha</span></p>
                    </router-link>
                </li>

                <li>
                    <router-link data-secondary-item to="/money" :aria-label="pendingEntries ? `Geld, ${pendingEntries === 1 ? '1 Buchung zu genehmigen' : `${pendingEntries} Buchungen zu genehmigen`}` : undefined" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Money' || $route.name === 'MoneyAccountDetail' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="geldTotem" alt="" class="nav_image">
                            <span v-if="pendingEntries" class="nav_badge" aria-hidden="true">{{ formatBadge(pendingEntries) }}</span>
                        </div>
                        <p class="nav_title">Geld</p>
                    </router-link>
                </li>

                <li>
                    <router-link data-secondary-item to="/organizations" :aria-label="pendingApplicants ? `Organisationen, ${pendingApplicants === 1 ? '1 offene Bewerbung' : `${pendingApplicants} offene Bewerbungen`}` : undefined" class="nav_link" :class="{ 'nav_link--active': $route.name === 'Organizations' }" @click="closeAllMenus">
                        <div class="nav_icon">
                            <img :src="organizationsTotem" alt="" class="nav_image">
                            <span v-if="pendingApplicants" class="nav_badge" aria-hidden="true">{{ formatBadge(pendingApplicants) }}</span>
                        </div>
                        <p class="nav_title">Organisationen</p>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- Profile Menu -->
        <div id="app-menu-profile" ref="profileMenu" class="profilenav" :inert="!isProfileMenuOpen">
            <div class="nav_profile_wrap">
                <router-link to="/profile" class="nav_profile" @click="closeAllMenus">
                    <div class="nav_icon nav_icon--profile">
                        <AppAvatar
                            :src="authStore.user?.Avatar"
                            alt=""
                            img-class="profile_image"
                        />
                    </div>
                    <div class="nav_text">
                        <p class="nav_title">{{ authStore.user?.FirstName }}</p>
                        <p class="nav_subtitle">Profil ansehen →</p>
                    </div>
                </router-link>
                <button type="button" @click="handleLogout" class="nav_logout" title="Abmelden" aria-label="Abmelden">
                    <div class="nav_icon nav_icon--logout">
                        <span class="icon-mask" :style="logoutIconStyle" aria-hidden="true"></span>
                    </div>
                </button>
                <button type="button" @click="openFeedback" class="nav_feedback" title="Feedback geben" aria-label="Feedback geben">
                    <div class="nav_icon nav_icon--settings">
                        <img :src="actionFeedback" alt="" class="settings_image">
                    </div>
                </button>
                <button type="button" @click="openSettings" class="nav_settings" title="Einstellungen" aria-label="Einstellungen">
                    <div class="nav_icon nav_icon--settings">
                        <img :src="actionSettings" alt="" class="settings_image">
                    </div>
                </button>
            </div>
            <p class="version_note"><i>ToTeam Vue</i> <kbd>BETA</kbd></p>
        </div>
    </nav>

    <SettingsModal ref="settingsModal" />
    <FeedbackModal ref="feedbackModal" />
</template>

<script setup>
import { ref, computed, nextTick, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { useUiStore } from '@stores/ui'
import { useInventoryStore } from '@stores/inventory'
import { useMoneyStore } from '@stores/money'
import { useOrganizationsStore } from '@stores/organizations'
import AppAvatar from '@components/ui/AppAvatar.vue'
import SettingsModal from '@components/layout/SettingsModal.vue'
import FeedbackModal from '@components/layout/FeedbackModal.vue'

// Import totem icons
import dashboardTotem from '../../../../icons/totems/dashboard_totem.png'
import dashboardTotemInactive from '../../../../icons/totems/dashboard_totem_inactive.png'
import nachrichtenTotem from '../../../../icons/totems/nachrichten_totem.png'
import nachrichtenTotemInactive from '../../../../icons/totems/nachrichten_totem_inactive.png'
import kalenderTotem from '../../../../icons/totems/kalender_totem.png'
import kalenderTotemInactive from '../../../../icons/totems/kalender_totem_inactive.png'
import essenTotem from '../../../../icons/totems/essen_totem.png'
import downloadsTotem from '../../../../icons/totems/downloads_totem.png'
import todosTotem from '../../../../icons/totems/todos_totem.png'
import skriptTotem from '../../../../icons/totems/skript_totem.png'
import marketingTotem from '../../../../icons/totems/marketing_totem.png'
import inventarTotem from '../../../../icons/totems/inventar_totem.png'
import geldTotem from '../../../../icons/totems/geld_totem.png'
import kartenTotem from '../../../../icons/totems/karten_totem.png'
import organizationsTotem from '../../../../icons/totems/organizations_totem.png'
import actionLogout from '../../../../icons/actions/action_logout.svg'
import actionSettings from '../../../../icons/actions/action_settings.svg'
import actionFeedback from '../../../../icons/feedback_admin.svg'

const logoutIconStyle = { maskImage: `url("${actionLogout}")`, WebkitMaskImage: `url("${actionLogout}")` }
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const inventoryStore = useInventoryStore()
const moneyStore = useMoneyStore()
const organizationsStore = useOrganizationsStore()

// Offene Ausleih-Anträge, über die man entscheiden darf (der Server zählt nur die)
const pendingRentals = computed(() => (authStore.hasTotem('inventory') ? inventoryStore.pendingRentals : 0))
// Buchungen, die man noch genehmigen muss (MONEY_APPROVE_ENTRIES)
const pendingEntries = computed(() => moneyStore.pendingEntries)
// Bewerbungen, die man annehmen darf (ORG_MANAGE_MEMBERS)
const pendingApplicants = computed(() => organizationsStore.pendingApplicants)

function formatBadge(count) {
    return count > 9 ? '9+' : count
}
const isSecondaryMenuOpen = ref(false)
const isProfileMenuOpen = ref(false)
const settingsModal = ref(null)
const feedbackModal = ref(null)
const profileToggle = ref(null)
const secondaryToggle = ref(null)
const profileMenu = ref(null)
const secondaryMenu = ref(null)

// ── Pfeiltasten-Navigation ──────────────────────────────────────────────────
// Die Hauptpunkte (ohne Profil) sind ein Tab-Stopp: links/rechts wechselt
// zwischen ihnen. Auf "Weitere Bereiche" klappt das Untermenü automatisch auf,
// hoch/runter führt hinein und wechselt dort zwischen den Einträgen.

const ROUTE_TO_PRIMARY = { Announcements: 'announcements', Dashboard: 'dashboard', Calendar: 'calendar' }
const currentPrimary = ref(null)

function primaryItems() {
    return [...document.querySelectorAll('.AppMenu [data-primary-item]')]
}

function secondaryItems() {
    return [...(secondaryMenu.value?.querySelectorAll('[data-secondary-item]') ?? [])]
}

// Tab-Stopp: zuletzt fokussierter Hauptpunkt, sonst der zur aktuellen Seite, sonst Dashboard
function primaryTabindex(key) {
    const active = currentPrimary.value ?? ROUTE_TO_PRIMARY[router.currentRoute.value.name] ?? 'dashboard'
    return key === active ? 0 : -1
}

let openedByArrow = false

function onPrimaryFocus(key) {
    currentPrimary.value = key
}

function focusPrimary(index) {
    const items = primaryItems()
    const target = items[(index + items.length) % items.length]
    if (!target) return
    const key = target.dataset.primaryItem
    currentPrimary.value = key
    target.focus()
    // "Weitere Bereiche" per Pfeiltaste erreicht: Untermenü aufklappen, Fokus bleibt auf dem Schalter
    if (key === 'more' && !isSecondaryMenuOpen.value) {
        openSecondaryMenu()
        openedByArrow = true
    } else if (key !== 'more' && isSecondaryMenuOpen.value) {
        closeSecondaryMenu()
    }
}

function onMenuKeydown(event) {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return
    const primary = primaryItems()
    const secondary = secondaryItems()
    const primaryIndex = primary.indexOf(event.target)
    const secondaryIndex = secondary.indexOf(event.target)

    if (primaryIndex !== -1) {
        const moreFocused = event.target.dataset.primaryItem === 'more'
        if (event.key === 'ArrowLeft') focusPrimary(primaryIndex - 1)
        else if (event.key === 'ArrowRight') focusPrimary(primaryIndex + 1)
        else if (event.key === 'Home') focusPrimary(0)
        else if (event.key === 'End') focusPrimary(primary.length - 1)
        else if (moreFocused && secondary.length) {
            // Hoch/runter auf "Weitere Bereiche": ins Untermenü (es liegt über der Leiste)
            if (!isSecondaryMenuOpen.value) openSecondaryMenu()
            nextTick(() => {
                const list = secondaryItems()
                ;(event.key === 'ArrowUp' ? list[list.length - 1] : list[0])?.focus()
            })
        } else {
            return
        }
        event.preventDefault()
        return
    }

    if (secondaryIndex !== -1) {
        const last = secondary.length - 1
        if (event.key === 'ArrowUp') secondary[secondaryIndex === 0 ? last : secondaryIndex - 1].focus()
        else if (event.key === 'ArrowDown') secondary[secondaryIndex === last ? 0 : secondaryIndex + 1].focus()
        else if (event.key === 'Home') secondary[0].focus()
        else if (event.key === 'End') secondary[last].focus()
        else {
            // Links/rechts im Untermenü: zurück in die Hauptleiste, von "Weitere Bereiche" aus weiter
            const moreIndex = primary.findIndex(el => el.dataset.primaryItem === 'more')
            focusPrimary(moreIndex + (event.key === 'ArrowLeft' ? -1 : 1))
        }
        event.preventDefault()
    }
}

// Verlässt der Tastatur-Fokus das Menü (Tab), klappt ein per Pfeiltaste geöffnetes Untermenü zu
function onFocusOut(event) {
    const next = event.relatedTarget
    if (!next || event.currentTarget.contains(next)) return
    if (openedByArrow && isSecondaryMenuOpen.value) closeSecondaryMenu()
}

// Beim Öffnen per Tastatur/Screenreader direkt in das Menü springen
async function focusFirstIn(menuEl) {
    await nextTick()
    menuEl?.querySelector('a[href], button')?.focus({ preventScroll: true })
}

// Esc schließt ein offenes Menü und bringt den Fokus zurück zu seinem Schalter
function onEscape() {
    if (isSecondaryMenuOpen.value) {
        closeSecondaryMenu()
        secondaryToggle.value?.focus()
    } else if (isProfileMenuOpen.value) {
        closeProfileMenu()
        profileToggle.value?.focus()
    }
}

function toggleSecondaryMenu() {
    // Per Pfeiltaste schon aufgeklappt: Enter/Klick springt hinein statt zuzuklappen
    if (openedByArrow && isSecondaryMenuOpen.value) {
        openedByArrow = false
        focusFirstIn(secondaryMenu.value)
        return
    }
    // Toggle body class like the original JavaScript does
    document.body.classList.toggle('secnav--open');
    isSecondaryMenuOpen.value = document.body.classList.contains('secnav--open');
    closeProfileMenu(); // Ensure profile menu is closed when opening secondary menu
    openedByArrow = false;
    if (isSecondaryMenuOpen.value) focusFirstIn(secondaryMenu.value)
}

function openSecondaryMenu() {
    document.body.classList.add('secnav--open');
    isSecondaryMenuOpen.value = true;
    closeProfileMenu();
}

function closeSecondaryMenu() {
    document.body.classList.remove('secnav--open');
    isSecondaryMenuOpen.value = false;
    openedByArrow = false;
}

function toggleProfileMenu() {
    // Toggle body class like the original JavaScript does
    document.body.classList.toggle('profilenav--open');
    isProfileMenuOpen.value = document.body.classList.contains('profilenav--open');
    closeSecondaryMenu();
    if (isProfileMenuOpen.value) focusFirstIn(profileMenu.value)
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

function openFeedback() {
    closeAllMenus()
    feedbackModal.value?.open()
}

async function handleLogout() {
    await authStore.logout()
    closeSecondaryMenu()
    closeProfileMenu()
    router.push({ name: 'Login' })
}

onUnmounted(() => {
    // Clean up body class when component is destroyed
    closeSecondaryMenu()
    closeProfileMenu()
})
</script>
