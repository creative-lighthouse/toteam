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
          <img v-if="org.LogoURL" :src="org.LogoURL" :alt="org.Title" class="org-manage_logo" />
          <div>
            <h2 class="hl2 org-manage_title">{{ org.Title }}</h2>
            <p class="org-manage_subtitle">Organisation verwalten</p>
          </div>
        </div>

        <!-- Rollen & Berechtigungen -->
        <section v-if="org.CanManageRoles" class="org-manage_section">
          <div class="org-manage_section-heading">
            <h3 class="hl3">Rollen &amp; Berechtigungen</h3>
            <button class="button button--primary" @click="openCreateRole">+ Neue Rolle</button>
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
                <button class="button button--secondary" @click="openEditRole(role)">Bearbeiten</button>
                <button class="button button--danger" @click="removeRole(role)">Löschen</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Mitglieder -->
        <section v-if="org.CanManageMembers || org.CanManageRoles" class="org-manage_section">
          <h3 class="hl3">Mitglieder</h3>

          <div class="org-manage_members">
            <div v-for="m in org.Members" :key="m.MembershipID" class="org-manage_member">
              <img :src="m.Avatar" :alt="m.Name" class="org-manage_member-avatar" />
              <div class="org-manage_member-info">
                <span class="org-manage_member-name">{{ m.Name }}</span>
                <span class="org-manage_member-roles">
                  <span v-for="r in m.Roles" :key="r.ID" class="org-manage_role-chip">{{ r.Title }}</span>
                  <span v-if="!m.Roles.length" class="org-manage_role-chip org-manage_role-chip--empty">Keine Rolle</span>
                </span>
              </div>
              <button class="button button--secondary" @click="openMemberRoles(m)">Rollen zuweisen</button>
            </div>
          </div>
        </section>
      </template>
    </div>

    <RoleEditModal
      v-if="org"
      ref="roleModal"
      :organization-id="org.ID"
      :role="editingRole"
      @saved="onRoleSaved"
    />
    <MemberRolesModal
      v-if="org"
      ref="memberRolesModal"
      :membership-id="editingMember?.MembershipID ?? 0"
      :member-name="editingMember?.Name ?? ''"
      :current-role-ids="editingMember?.Roles?.map(r => r.ID) ?? []"
      :available-roles="orgRolesStore.roles.map(r => ({ ID: r.ID, Title: r.Title }))"
      @saved="onMemberRolesSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePageHeaderStore } from '@stores/pageHeader'
import { useOrgRolesStore } from '@stores/orgRoles'
import { apiGet } from '@utils/api'
import RoleEditModal from '@components/RoleEditModal.vue'
import MemberRolesModal from '@components/MemberRolesModal.vue'

const route = useRoute()
const orgRolesStore = useOrgRolesStore()

const org = ref(null)
const loading = ref(true)
const roleModal = ref(null)
const memberRolesModal = ref(null)
const editingRole = ref(null)
const editingMember = ref(null)

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

onMounted(() => {
  usePageHeaderStore().setHeader('Organisation verwalten', '')
  loadOrg()
})
</script>
