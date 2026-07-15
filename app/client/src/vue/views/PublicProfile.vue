<template>
    <section class="section--ProfilePage">
        <div class="section_content">

        <div v-if="loading" class="profile-state">Profil wird geladen …</div>
            <div v-else-if="notFound" class="profile-state profile-state--notfound">
                <p>Profil <strong>@{{ route.params.username }}</strong> nicht gefunden.</p>
            </div>

            <div v-else-if="profile" class="section_profilecard">
                <AppIconButton
                    v-if="profile.Username"
                    variant="ghost"
                    class="profilecard-qr-btn"
                    aria-label="QR-Code anzeigen"
                    @click="qrModal?.open()"
                >
                    <img src="/app/client/icons/actions/action_qrcode.svg" alt="">
                </AppIconButton>

                <div class="profile-image">
                    <img
                        v-if="profile.ProfileImage"
                        :src="profile.ProfileImage.URL"
                        :alt="`Profilbild von ${displayName}`"
                    >
                    <img
                        v-else
                        :src="profile.Gravatar"
                        alt="Standard Profilbild"
                    >
                </div>

                <p v-if="profile.Username && profile.NameVisibility !== 'username'" class="profile-username">
                    @{{ profile.Username }}
                </p>

                <table class="profile-details">
                    <tbody>
                        <tr v-if="profile.FirstName">
                            <th>Vorname</th>
                            <td>{{ profile.FirstName }}</td>
                        </tr>
                        <tr v-if="profile.Surname">
                            <th>Nachname</th>
                            <td>{{ profile.Surname }}</td>
                        </tr>
                        <tr>
                            <th>Essenspräferenz</th>
                            <td>{{ foodLabel(profile.FoodPreference) }}</td>
                        </tr>
                        <tr v-if="profile.DateOfBirth">
                            <th>Alter</th>
                            <td>{{ calcAge(profile.DateOfBirth) }} Jahre</td>
                        </tr>
                        <tr v-if="profile.Joindate">
                            <th>Mitglied seit</th>
                            <td>{{ formatDate(profile.Joindate) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="profile.Organizations?.length > 0" class="profile-orgs">
                    <h3 class="profile-orgs_title">Organisationen</h3>
                    <component
                        :is="org.Username ? RouterLink : 'div'"
                        v-for="org in profile.Organizations"
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

        <QrCodeModal v-if="profile?.Username" ref="qrModal" :username="profile.Username" />
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet } from '@utils/api'
import QrCodeModal from '@components/QrCodeModal.vue'
import AppIconButton from '@components/AppIconButton.vue'

const route    = useRoute()
const loading  = ref(true)
const notFound = ref(false)
const profile  = ref(null)
const qrModal  = ref(null)

const displayName = computed(() => {
  if (!profile.value) return ''
  const vis = profile.value.NameVisibility
  if (vis === 'username') return `@${profile.value.Username}`
  if (vis === 'first')    return profile.value.FirstName
  return `${profile.value.FirstName} ${profile.value.Surname}`
})

onMounted(async () => {
  usePageHeaderStore().setHeader('Profil', '')
  try {
    const data = await apiGet(`/profile/user/${encodeURIComponent(route.params.username)}`, false)
    if (data.success && data.profile) {
      profile.value = data.profile
      usePageHeaderStore().setHeader(displayName.value, profile.value.Username ? `@${profile.value.Username}` : '')
    } else {
      notFound.value = true
    }
  } catch (err) {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

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
