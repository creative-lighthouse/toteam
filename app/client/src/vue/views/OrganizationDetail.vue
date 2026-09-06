<template>
    <section class="section--OrganizationDetailPage">
        <div v-if="loading" class="section_infobox">
            <p>Organisation wird geladen …</p>
        </div>

        <div v-else-if="notFound" class="section_infobox org-detail-notfound">
            <p>Organisation <strong>@{{ route.params.username }}</strong> nicht gefunden.</p>
        </div>

        <template v-else-if="org">
            <div class="org-detail-hero">
                <div class="org-detail-hero_cover">
                <img v-if="org.CoverURL" :src="org.CoverURL" :alt="`${org.Title} Cover`" class="org-detail-hero_cover-img">
                <div v-else class="org-detail-hero_cover-placeholder" />
                </div>

                <div class="org-detail-hero_logo-wrap">
                <img v-if="org.LogoURL" :src="org.LogoURL" :alt="`${org.Title} Logo`" class="org-detail-hero_logo">
                <div v-else class="org-detail-hero_logo-placeholder">{{ org.Title?.charAt(0) ?? '?' }}</div>
                </div>
            </div>

            <div class="section_content org-detail-content">
                <div class="org-detail-header">
                    <div>
                        <h1 class="hl1 org-detail-title">{{ org.Title }}</h1>
                        <p v-if="org.Username" class="org-detail-username">@{{ org.Username }}</p>
                    </div>

                    <div class="org-detail-header_actions">
                        <span class="org-detail-count">{{ org.MemberCount }} {{ org.MemberCount === 1 ? 'Mitglied' : 'Mitglieder' }}</span>

                        <AppButton
                        v-if="org.CanManageMembers || org.CanManageRoles"
                        :to="`/organizations/${org.Username}/manage`"
                        variant="secondary"
                        >
                        Organisation verwalten
                        </AppButton>

                        <AppButton
                        v-if="!org.MembershipStatus && org.JoinMode !== 'invite_only'"
                        variant="primary"
                        :disabled="joining"
                        @click="handleJoin"
                        >
                        {{ joining ? '…' : (org.JoinMode === 'open' ? 'Beitreten' : 'Bewerben') }}
                        </AppButton>

                        <span v-else-if="org.MembershipStatus" class="org-detail-membership-badge" :class="`org-detail-membership-badge--${org.MembershipStatus}`">
                        {{ membershipLabel }}
                        </span>
                    </div>
                </div>

                <p v-if="org.Description" class="org-detail-description">{{ org.Description }}</p>

                <section v-if="org.Members?.length" class="org-detail-members">
                    <h2 class="org-detail-members_title">Mitglieder</h2>
                    <div class="org-detail-members_grid">
                        <RouterLink
                        v-for="m in org.Members"
                        :key="m.MemberID"
                        :to="m.Username ? `/profile/${m.Username}` : '#'"
                        class="org-detail-member"
                        :class="{ 'org-detail-member--no-link': !m.Username }"
                        >
                            <AppAvatar :src="m.Avatar" :alt="m.Name" img-class="org-detail-member_avatar" />
                            <span class="org-detail-member_name">{{ m.Name }}</span>
                            <span class="org-detail-member_role">
                                <span v-if="m.Roles?.length">{{ m.Roles.map(r => r.Title).join(', ') }}</span>
                                <span v-else>Mitglied</span>
                            </span>
                        </RouterLink>
                    </div>
                </section>
            </div>
        </template>
    </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { RouterLink } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { apiGet, apiPost } from '@utils/api'
import AppButton from '@components/AppButton.vue'
import AppAvatar from '@components/AppAvatar.vue'

const route    = useRoute()
const loading  = ref(true)
const notFound = ref(false)
const joining  = ref(false)
const org      = ref(null)

const membershipLabel = computed(() => {
  const labels = { member: 'Mitglied', applicant: 'Bewerbung ausstehend' }
  return labels[org.value?.MembershipStatus] ?? ''
})

onMounted(async () => {
  usePageHeaderStore().setHeader('Organisation', '')
  try {
    const data = await apiGet(`/organizations/detail?username=${encodeURIComponent(route.params.username)}`, false)
    if (data.organization) {
      org.value = data.organization
      usePageHeaderStore().setHeader(org.value.Title, org.value.Username ? `@${org.value.Username}` : '')
    } else {
      notFound.value = true
    }
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

async function handleJoin() {
  if (joining.value || !org.value) return
  joining.value = true
  try {
    const response = await apiPost(`/organizations/join/${org.value.ID}`, {})
    if (response.success) {
      org.value.MembershipStatus = response.data.MembershipStatus
      if (response.data.MembershipStatus === 'member') {
        org.value.MemberCount++
      }
    } else {
      alert(response.error ?? 'Fehler beim Beitreten.')
    }
  } catch {
    alert('Fehler beim Beitreten.')
  } finally {
    joining.value = false
  }
}
</script>
