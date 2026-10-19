<template>
  <div class="section section--OrganizationManagePage">
    <div class="section_content">

      <div v-if="loading" class="section_infobox"><p>Lade Organisation…</p></div>

      <div v-else-if="!org" class="section_infobox"><p>Organisation nicht gefunden.</p></div>

      <template v-else-if="!org.CanManageRoles && !org.CanManageMembers">
        <div class="section_infobox"><p>Keine Berechtigung, diese Organisation zu verwalten.</p></div>
      </template>

      <template v-else>
        <div class="org-manage_header">
          <AppOrgLogo :src="org.LogoURL" :alt="org.Title" :size="56" class="org-manage_logo" />
          <div class="org-manage_header-text">
            <h2 class="hl2 org-manage_title">{{ org.Title }}</h2>
            <p class="org-manage_subtitle">Organisation verwalten</p>
          </div>
          <label v-if="org.Permissions?.includes('ORG_MANAGE_SETTINGS')" class="button org-manage_logo-btn">
            Logo ändern
            <input
              ref="logoFileInputEl"
              type="file"
              accept="image/jpeg,image/png"
              class="file-input-hidden"
              @change="onLogoFileSelected"
            >
          </label>
        </div>
        <p v-if="logoError" class="org-manage_logo-error">{{ logoError }}</p>

        <!-- Rollen & Berechtigungen -->
        <section v-if="org.CanManageRoles" class="org-manage_section">
          <div class="org-manage_section-heading">
            <h3 class="hl3">Rollen &amp; Berechtigungen</h3>
            <AppButton variant="primary" @click="openCreateRole">+ Neue Rolle</AppButton>
          </div>

          <div v-if="orgRolesStore.loading" class="section_infobox"><p>Lade Rollen…</p></div>

          <div v-else class="org-manage_roles">
            <div v-for="role in orgRolesStore.roles" :key="role.ID" class="org-manage_role-card">
              <div class="org-manage_role-info">
                <span class="org-manage_role-title">{{ role.Title }}</span>
                <span class="org-manage_role-meta">
                  {{ role.Permissions.length }} Berechtigung{{ role.Permissions.length !== 1 ? 'en' : '' }}
                  · {{ role.MemberCount }} {{ role.MemberCount === 1 ? 'Person' : 'Personen' }}
                </span>
              </div>
              <div class="org-manage_role-actions">
                <AppIconButton variant="primary" :aria-label="`Rolle „${role.Title}“ bearbeiten`" @click="openEditRole(role)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </AppIconButton>
                <AppIconButton variant="danger" :aria-label="`Rolle „${role.Title}“ löschen`" @click="removeRole(role)">×</AppIconButton>
              </div>
            </div>
          </div>
        </section>

        <!-- Mitglieder -->
        <section v-if="org.CanManageMembers || org.CanManageRoles" class="org-manage_section">
          <div class="org-manage_section-heading">
            <h3 class="hl3">Mitglieder</h3>
            <AppButton v-if="org.CanManageMembers" variant="primary" @click="inviteModal?.open()">+ Person einladen</AppButton>
          </div>
          <p v-if="inviteNotice" class="org-manage_notice" role="status">{{ inviteNotice }}</p>

          <div class="org-manage_members">
            <div v-for="m in org.Members" :key="m.MembershipID" class="org-manage_member">
              <AppAvatar :src="m.Avatar" alt="" img-class="org-manage_member-avatar" />
              <div class="org-manage_member-info">
                <span class="org-manage_member-name">{{ m.Name }}</span>
                <span class="org-manage_member-roles">
                  <span v-for="r in m.Roles" :key="r.ID" class="org-manage_role-chip">{{ r.Title }}</span>
                  <span v-if="!m.Roles.length" class="org-manage_role-chip org-manage_role-chip--empty">Keine Rolle</span>
                </span>
              </div>
              <div class="org-manage_member-actions">
                <AppIconButton variant="primary" :aria-label="`Rollen von ${m.Name} zuweisen`" @click="openMemberRoles(m)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </AppIconButton>
                <AppIconButton
                  v-if="canRemove(m)"
                  variant="danger"
                  :aria-label="`${m.Name} aus der Organisation entfernen`"
                  title="Aus der Organisation entfernen"
                  :disabled="removingId === m.MembershipID"
                  @click="removeMember(m)"
                >
                  <span class="icon-mask" :style="trashIconStyle" />
                </AppIconButton>
              </div>
            </div>
          </div>

          <!-- Offene Einladungen: warten auf Annahme durch die Person (Dashboard) -->
          <template v-if="org.CanManageMembers && org.Invitations?.length">
            <h4 class="org-manage_subheading">Offene Einladungen</h4>
            <div class="org-manage_members">
              <div v-for="inv in org.Invitations" :key="inv.MembershipID" class="org-manage_member org-manage_member--invited">
                <AppAvatar :src="inv.Avatar" alt="" img-class="org-manage_member-avatar" />
                <div class="org-manage_member-info">
                  <span class="org-manage_member-name">{{ inv.Name }}</span>
                  <span class="org-manage_member-meta">
                    <template v-if="inv.Username">@{{ inv.Username }} · </template>eingeladen<template v-if="inv.InvitedBy"> von {{ inv.InvitedBy }}</template>
                  </span>
                </div>
                <AppButton size="small" variant="secondary" :disabled="cancellingId === inv.MembershipID" @click="cancelInvitation(inv)">
                  Zurückziehen
                </AppButton>
              </div>
            </div>
          </template>
        </section>
      </template>
    </div>

    <OrgMemberInviteModal v-if="org" ref="inviteModal" :organization-id="org.ID" @invited="onInvited" />

    <OrgRoleEditModal
      v-if="org"
      ref="roleModal"
      :organization-id="org.ID"
      :role="editingRole"
      @saved="onRoleSaved"
    />
    <OrgMemberRolesModal
      v-if="org"
      ref="memberRolesModal"
      :membership-id="editingMember?.MembershipID ?? 0"
      :member-name="editingMember?.Name ?? ''"
      :current-role-ids="editingMember?.Roles?.map(r => r.ID) ?? []"
      :available-roles="orgRolesStore.roles.map(r => ({ ID: r.ID, Title: r.Title }))"
      @saved="onMemberRolesSaved"
    />
    <ImageCropModal
      v-if="org"
      ref="logoCropModal"
      title="Logo zuschneiden"
      :upload-url="`/organizations/uploadLogo/${org.ID}`"
      response-field="LogoURL"
      shape="square"
      @saved="onLogoSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useOrgRolesStore } from '@stores/orgRoles'
import { apiGet, apiPost } from '@utils/api'
import OrgRoleEditModal from '@components/organizations/OrgRoleEditModal.vue'
import OrgMemberRolesModal from '@components/organizations/OrgMemberRolesModal.vue'
import OrgMemberInviteModal from '@components/organizations/OrgMemberInviteModal.vue'
import { useOrganizationsStore } from '@stores/organizations'
import { useAuthStore } from '@stores/auth'
import actionTrash from '../../../icons/actions/action_trash.svg'
import AppButton from '@components/ui/AppButton.vue'
import AppIconButton from '@components/ui/AppIconButton.vue'
import AppAvatar from '@components/ui/AppAvatar.vue'
import AppOrgLogo from '@components/ui/AppOrgLogo.vue'
import ImageCropModal from '@components/ui/ImageCropModal.vue'

const route = useRoute()
const orgRolesStore = useOrgRolesStore()

const org = ref(null)
const loading = ref(true)
const roleModal = ref(null)
const memberRolesModal = ref(null)
const editingRole = ref(null)
const editingMember = ref(null)

// ── Logo ───────────────────────────────────────────
const logoFileInputEl = ref(null)
const logoCropModal = ref(null)
const logoError = ref(null)

function onLogoFileSelected(e) {
  logoError.value = null
  const file = e.target.files[0]
  if (logoFileInputEl.value) logoFileInputEl.value.value = ''
  if (!file) return

  if (!['image/jpeg', 'image/png'].includes(file.type)) {
    logoError.value = 'Nur PNG und JPEG sind erlaubt.'
    return
  }
  if (file.size > 25 * 1024 * 1024) {
    logoError.value = 'Das Bild darf maximal 25 MB groß sein.'
    return
  }

  logoCropModal.value?.open(file)
}

function onLogoSaved(logoUrl) {
  if (org.value) org.value.LogoURL = logoUrl
}

async function loadOrg() {
  loading.value = true
  try {
    const data = await apiGet(`/organizations/detail?username=${encodeURIComponent(route.params.username)}`, false)
    org.value = data.organization || null
    if (org.value) {
      usePageHeaderStore().setHeader('Organisation verwalten', org.value.Title)
      if (org.value.CanManageRoles) {
        await orgRolesStore.fetchRoles(org.value.ID)
      }
    }
  } finally {
    loading.value = false
  }
}

function openCreateRole() {
  editingRole.value = null
  roleModal.value?.open()
}

function openEditRole(role) {
  editingRole.value = role
  roleModal.value?.open()
}

async function removeRole(role) {
  if (!confirm(`Rolle "${role.Title}" wirklich löschen?`)) return
  const response = await orgRolesStore.deleteRole(role.ID)
  if (!response.success) {
    alert(response.error || 'Fehler beim Löschen der Rolle.')
  }
}

function onRoleSaved() {
  roleModal.value?.close()
}

function openMemberRoles(member) {
  editingMember.value = member
  memberRolesModal.value?.open()
}

function onMemberRolesSaved(membership) {
  memberRolesModal.value?.close()
  const member = org.value.Members.find(m => m.MembershipID === membership.ID)
  if (member) member.Roles = membership.Roles
}

// ── Einladungen ───────────────────────────────────
const organizationsStore = useOrganizationsStore()
const inviteModal = ref(null)
const inviteNotice = ref(null)
const cancellingId = ref(null)

// Nach dem Einladen neu laden — die Person steht dann unter "Offene Einladungen"
// (bzw. direkt bei den Mitgliedern, wenn sie sich schon beworben hatte)
async function onInvited(message) {
  inviteNotice.value = message
  await loadOrg()
  organizationsStore.fetchPendingCount()
}

async function cancelInvitation(invitation) {
  if (!confirm(`Einladung an ${invitation.Name} zurückziehen?`)) return
  cancellingId.value = invitation.MembershipID
  try {
    const response = await organizationsStore.cancelInvitation(invitation.MembershipID)
    if (!response.success) {
      alert(response.error || 'Einladung konnte nicht zurückgezogen werden.')
      return
    }
    org.value.Invitations = org.value.Invitations.filter(i => i.MembershipID !== invitation.MembershipID)
    inviteNotice.value = null
  } finally {
    cancellingId.value = null
  }
}

// ── Mitglieder entfernen ──────────────────────────
// Nicht sich selbst; Administratoren nur als Administrator (prüft auch der Server)
const authStore = useAuthStore()
const removingId = ref(null)
const trashIconStyle = { maskImage: `url("${actionTrash}")`, WebkitMaskImage: `url("${actionTrash}")` }

function canRemove(m) {
  if (!org.value?.CanManageMembers || m.MemberID === authStore.user?.ID) return false
  return !m.IsAdmin || !!org.value.Permissions?.includes('ORG_ADMIN')
}

async function removeMember(m) {
  if (!confirm(`${m.Name} wirklich aus „${org.value.Title}“ entfernen?\n\nDie Person verliert sofort den Zugriff auf alle Inhalte der Organisation. Sie kann später erneut eingeladen werden.`)) return
  removingId.value = m.MembershipID
  try {
    const response = await apiPost(`/organizations/removeMember/${m.MembershipID}`, {})
    if (!response.success) {
      alert(response.error || 'Mitglied konnte nicht entfernt werden.')
      return
    }
    org.value.Members = org.value.Members.filter(x => x.MembershipID !== m.MembershipID)
    inviteNotice.value = response.message
    // Rollen zeigen die Zahl ihrer Mitglieder
    if (org.value.CanManageRoles) orgRolesStore.fetchRoles(org.value.ID)
  } finally {
    removingId.value = null
  }
}

onMounted(() => {
  usePageHeaderStore().setHeader('Organisation verwalten', '')
  loadOrg()
})
</script>
