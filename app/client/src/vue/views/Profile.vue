<template>
    <section class="section--ProfilePage">
        <div class="section_content">
            <div class="section_profilecard" v-if="authStore.user">
                <button
                    v-if="authStore.user.Username"
                    class="profilecard-qr-btn"
                    aria-label="QR-Code anzeigen"
                    @click="qrModal?.open()"
                >
                    <img src="/app/client/icons/actions/action_qrcode.svg" alt="">
                </button>

                <div class="profile-image">
                    <img
                        v-if="authStore.user.ProfileImage"
                        :src="authStore.user.ProfileImage.URL"
                        :alt="`Profilbild von ${authStore.user.FirstName}`"
                    >
                    <img
                        v-else
                        :src="authStore.user.Gravatar"
                        alt="Standard Profilbild"
                    >
                </div>

                <h2 class="hl2">{{ authStore.userName }}</h2>
                <p v-if="authStore.user.Username" class="profile-username">@{{ authStore.user.Username }}</p>

                <table class="profile-details">
                    <tbody>
                        <tr>
                            <th>Vorname</th>
                            <td>{{ authStore.user.FirstName }}</td>
                        </tr>
                        <tr>
                            <th>Nachname</th>
                            <td>{{ authStore.user.Surname }}</td>
                        </tr>
                        <tr>
                            <th>E-Mail</th>
                            <td>{{ authStore.user.Email }}</td>
                        </tr>
                        <tr>
                            <th>Essenspräferenz</th>
                            <td>{{ foodLabel(authStore.user.FoodPreference) }}</td>
                        </tr>
                        <tr v-if="authStore.user.DateOfBirth">
                            <th>Alter</th>
                            <td>{{ calcAge(authStore.user.DateOfBirth) }} Jahre</td>
                        </tr>
                        <tr v-if="authStore.user.Joindate">
                            <th>Mitglied seit</th>
                            <td>{{ formatDate(authStore.user.Joindate) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="orgs.length > 0" class="profile-orgs">
                    <h3 class="profile-orgs_title">Organisationen</h3>
                    <component
                        :is="org.Username ? RouterLink : 'div'"
                        v-for="org in orgs"
                        :key="org.MembershipID"
                        :to="org.Username ? `/organizations/${org.Username}` : undefined"
                        class="profile-org-item"
                        :class="{ 'profile-org-item--link': org.Username }"
                    >
                        <img v-if="org.LogoURL" :src="org.LogoURL" class="profile-org-item_logo" alt="">
                        <div class="profile-org-item_text">
                            <strong>{{ org.Title }}</strong>
                            <span class="profile-org-item_role">{{ roleLabel(org.Role) }}</span>
                        </div>
                    </component>
                </div>
            </div>

            <div class="profile-actions">
                <button class="button" @click="editModal?.open()">
                    Profil bearbeiten
                </button>
                <button @click="authStore.logout()" class="button button--danger">
                    Abmelden
                </button>
            </div>
        </div>

        <EditProfileModal ref="editModal" @updated="loadOrgs" />
        <QrCodeModal v-if="authStore.user?.Username" ref="qrModal" :username="authStore.user.Username" />
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@stores/auth'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet } from '@utils/api'
import EditProfileModal from '@components/EditProfileModal.vue'
import QrCodeModal from '@components/QrCodeModal.vue'

const authStore = useAuthStore()
usePageHeaderStore().setHeader('Profil', 'Verwalte dein Profil und deine Einstellungen.')

const editModal = ref(null)
const qrModal   = ref(null)
const orgs = ref([])

onMounted(loadOrgs)

async function loadOrgs() {
  try {
    const data = await apiGet('/profile', false)
    if (data.success && data.profile) {
      orgs.value = data.profile.Organizations ?? []
    }
  } catch (err) {
    console.error('Organisationen laden fehlgeschlagen:', err)
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '–'
  const d = new Date(dateStr + 'T00:00:00')
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function calcAge(dateStr) {
  if (!dateStr) return '–'
  const birth = new Date(dateStr + 'T00:00:00')
  const today = new Date()
  let age = today.getFullYear() - birth.getFullYear()
  const m = today.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--
  return age
}

function foodLabel(pref) {
  return { Vegetarian: 'Vegetarisch', Vegan: 'Vegan' }[pref] ?? 'Keine Besonderheiten'
}

function roleLabel(role) {
  return { member: 'Mitglied', moderator: 'Moderator', admin: 'Administrator', applicant: 'Bewerber' }[role] ?? role
}
</script>
